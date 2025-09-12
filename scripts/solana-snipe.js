/**
 * solana-snipe.js
 *
 * Full rewrite:
 * - Uses TransactionMessage.compileToV0Message for versioned tx messages (no TS casts)
 * - Robust BN / bigint / string handling
 * - Jupiter fallback & bonding curve flows preserved
 * - Clear logging to Laravel log path
 *
 * Ensure .env contains:
 *  SOLANA_PRIVATE_KEY=<mnemonic>
 *  SOLANA_RPC_URL=<rpc url>
 */

import { Connection, Keypair, LAMPORTS_PER_SOL, PublicKey, VersionedTransaction, ComputeBudgetProgram, SystemProgram, TransactionInstruction, TransactionMessage } from '@solana/web3.js';
import { getAssociatedTokenAddress, getAccount, TOKEN_PROGRAM_ID, ASSOCIATED_TOKEN_PROGRAM_ID, createAssociatedTokenAccountInstruction } from '@solana/spl-token';
import { BN } from 'bn.js';
import * as bip39 from 'bip39';
import { derivePath } from 'ed25519-hd-key';
import dotenv from 'dotenv';
import fetch from 'node-fetch';
import https from 'node:https';
import fs from 'node:fs';

dotenv.config();

// ---- Config / constants ----
const httpsAgent = new https.Agent({ rejectUnauthorized: false });
const LARAVEL_LOG_PATH = './storage/logs/laravel.log';

const SOL_MINT = new PublicKey('So11111111111111111111111111111111111111112');
const PUMP_FUN_PROGRAM = new PublicKey('6EF8rrecthR5Dkzon8Nwu78hRvfCKubJ14M5uBEwF6P');
const GLOBAL = new PublicKey('4wTV1YmiEkRvAtNtsSGPtUrqRYQMe5SKy2uB4haw8tqK');
const FEE_RECIPIENT = new PublicKey('CebN5WGQ4jvTD3mcN9gUjBGxx3fUXGF9riMtfuGAjNbJ');
const BONDING_CURVE_SEED = Buffer.from('bonding-curve');
const ASSOCIATED_BONDING_CURVE_SEED = Buffer.from('associated-bonding-curve');

// Discriminators (from your original)
const BUY_DISCRIMINATOR = Buffer.from([0x66, 0x06, 0x3d, 0x12, 0x01, 0xda, 0xeb, 0xea]);
const SELL_DISCRIMINATOR = Buffer.from([0x33, 0xe6, 0x85, 0xa4, 0x01, 0x7f, 0x83, 0xad]);

// ---- Helpers ----
function logToLaravel(level, message) {
    const timestamp = new Date().toISOString();
    const logLine = `[${timestamp}] ${level.toUpperCase()}: ${message}\n`;
    try {
        fs.appendFileSync(LARAVEL_LOG_PATH, logLine);
    } catch (_) {
        // If log file not writable, ignore — still print to console
    }
    console.log(logLine.trim());
}

function toBN(value) {
    if (BN.isBN(value)) return value;
    if (typeof value === 'bigint') return new BN(value.toString());
    if (typeof value === 'number') return new BN(Math.floor(value));
    if (typeof value === 'string') {
        // strip fractional part if any (token amounts should be integers)
        const s = value.includes('.') ? value.split('.')[0] : value;
        return new BN(s);
    }
    return new BN(String(value));
}

function bnToNumberSafe(bn) {
    if (!BN.isBN(bn)) bn = toBN(bn);
    const maxSafe = new BN(Number.MAX_SAFE_INTEGER.toString());
    if (bn.gt(maxSafe)) {
        throw new Error('BN too large to convert to number safely');
    }
    return bn.toNumber();
}

// ---- Derivations ----
function deriveBondingCurve(mint) {
    return PublicKey.findProgramAddressSync([BONDING_CURVE_SEED, mint.toBuffer()], PUMP_FUN_PROGRAM)[0];
}

function deriveAssociatedBondingCurve(bondingCurve, mint) {
    return PublicKey.findProgramAddressSync([bondingCurve.toBuffer(), TOKEN_PROGRAM_ID.toBuffer(), mint.toBuffer()], ASSOCIATED_TOKEN_PROGRAM_ID)[0];
}

// ---- Instruction builders ----
function createBuyInstruction(user, mint, bondingCurve, associatedBondingCurve, associatedUser, solAmountLamports, maxSolCost) {
    const data = Buffer.concat([
        BUY_DISCRIMINATOR,
        new BN(solAmountLamports).toArrayLike(Buffer, 'le', 8),
        new BN(maxSolCost).toArrayLike(Buffer, 'le', 8)
    ]);

    const keys = [
        { pubkey: GLOBAL, isSigner: false, isWritable: false },
        { pubkey: FEE_RECIPIENT, isSigner: false, isWritable: true },
        { pubkey: mint, isSigner: false, isWritable: false },
        { pubkey: bondingCurve, isSigner: false, isWritable: true },
        { pubkey: associatedBondingCurve, isSigner: false, isWritable: true },
        { pubkey: associatedUser, isSigner: false, isWritable: true },
        { pubkey: user, isSigner: true, isWritable: true },
        { pubkey: SystemProgram.programId, isSigner: false, isWritable: false },
        { pubkey: TOKEN_PROGRAM_ID, isSigner: false, isWritable: false },
        { pubkey: ASSOCIATED_TOKEN_PROGRAM_ID, isSigner: false, isWritable: false },
        { pubkey: PUMP_FUN_PROGRAM, isSigner: false, isWritable: false }
    ];

    return new TransactionInstruction({
        keys,
        programId: PUMP_FUN_PROGRAM,
        data
    });
}

function createSellInstruction(user, mint, bondingCurve, associatedBondingCurve, associatedUser, tokenAmount, maxSolCost) {
    const data = Buffer.concat([
        SELL_DISCRIMINATOR,
        new BN(tokenAmount).toArrayLike(Buffer, 'le', 8),
        new BN(maxSolCost).toArrayLike(Buffer, 'le', 8)
    ]);

    const keys = [
        { pubkey: GLOBAL, isSigner: false, isWritable: false },
        { pubkey: FEE_RECIPIENT, isSigner: false, isWritable: true },
        { pubkey: mint, isSigner: false, isWritable: false },
        { pubkey: bondingCurve, isSigner: false, isWritable: true },
        { pubkey: associatedBondingCurve, isSigner: false, isWritable: true },
        { pubkey: associatedUser, isSigner: false, isWritable: true },
        { pubkey: user, isSigner: true, isWritable: true },
        { pubkey: SystemProgram.programId, isSigner: false, isWritable: false },
        { pubkey: TOKEN_PROGRAM_ID, isSigner: false, isWritable: false },
        { pubkey: ASSOCIATED_TOKEN_PROGRAM_ID, isSigner: false, isWritable: false },
        { pubkey: PUMP_FUN_PROGRAM, isSigner: false, isWritable: false }
    ];

    return new TransactionInstruction({
        keys,
        programId: PUMP_FUN_PROGRAM,
        data
    });
}

// ---- Bonding curve state parser ----
async function getBondingCurveState(connection, bondingCurve) {
    try {
        const accountInfo = await connection.getAccountInfo(bondingCurve);
        if (!accountInfo) return null;

        const data = accountInfo.data;
        // Layout: discriminator (8 bytes), virtual_token_reserves (8), virtual_sol_reserves (8),
        // real_token_reserves (8), real_sol_reserves (8), token_total_supply (8), complete (1)
        const virtualTokenReserves = new BN(data.slice(8, 16), 'le');
        const virtualSolReserves = new BN(data.slice(16, 24), 'le');
        const realTokenReserves = new BN(data.slice(24, 32), 'le');
        const realSolReserves = new BN(data.slice(32, 40), 'le');
        const tokenTotalSupply = new BN(data.slice(40, 48), 'le');
        const complete = data[48] === 1;

        return {
            virtualTokenReserves,
            virtualSolReserves,
            realTokenReserves,
            realSolReserves,
            tokenTotalSupply,
            complete
        };
    } catch (e) {
        logToLaravel('error', 'Failed to parse bonding curve state: ' + e.message);
        return null;
    }
}

// ---- HTTP helpers ----
async function fetchWithRateLimit(url, options = {}, retries = 3) {
    for (let i = 0; i < retries; i++) {
        try {
            const response = await fetch(url, { ...options, agent: httpsAgent });
            if (response.status === 429) {
                const delay = 20 * Math.pow(2, i);
                logToLaravel('info', `Rate limit hit, waiting ${delay}ms...`);
                await new Promise(r => setTimeout(r, delay));
                continue;
            }
            if (!response.ok) {
                const text = await response.text();
                logToLaravel('error', `HTTP ${response.status}: ${text}`);
                throw new Error(`HTTP ${response.status}: ${text}`);
            }
            return await response.json();
        } catch (e) {
            logToLaravel('error', `Fetch attempt ${i + 1} failed: ${e.message}`);
            if (i === retries - 1) throw e;
            await new Promise(r => setTimeout(r, 1000 * (i + 1)));
        }
    }
}

// ---- Dex status (dexscreener) ----
async function getDexStatus(tokenAddress) {
    try {
        const response = await fetchWithRateLimit(`https://api.dexscreener.com/latest/dex/tokens/${tokenAddress}`);
        if (response && response.pairs && response.pairs.length > 0) {
            const pair = response.pairs[0];
            return { dexId: pair.dexId, poolAddress: pair.pairAddress, liquidity: pair.liquidity?.usd ?? null };
        }
    } catch (e) {
        logToLaravel('error', 'Dexscreener fetch failed: ' + (e.message || e));
    }
    return null;
}

// ---- Jupiter helpers (quote + swap) ----
async function jupiterSwap(connection, wallet, inputMint, outputMint, amount, slippageBps = 500) {
    const amountStr = BN.isBN(amount) ? amount.toString() : String(amount);
    const quoteUrl = 'https://quote-api.jup.ag/v6/quote';
    const quoteParams = new URLSearchParams({
        inputMint: inputMint.toString(),
        outputMint: outputMint.toString(),
        amount: amountStr,
        slippageBps: String(slippageBps)
    });

    const quoteResponse = await fetch(`${quoteUrl}?${quoteParams}`, { agent: httpsAgent });
    const quoteData = await quoteResponse.json();
    if (!quoteResponse.ok) throw new Error('Jupiter quote failed: ' + JSON.stringify(quoteData));

    const swapUrl = 'https://quote-api.jup.ag/v6/swap';
    const swapPayload = {
        quoteResponse: quoteData,
        userPublicKey: wallet.publicKey.toString(),
        wrapAndUnwrapSol: true,
        computeUnitPriceMicroLamports: 1000000
    };

    const swapResponse = await fetch(swapUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(swapPayload),
        agent: httpsAgent
    });
    const swapData = await swapResponse.json();
    if (!swapResponse.ok) throw new Error('Jupiter swap failed: ' + JSON.stringify(swapData));

    // swapData.swapTransaction is base64 serialized versioned tx
    const txBuf = Buffer.from(swapData.swapTransaction, 'base64');
    let tx;
    try {
        tx = VersionedTransaction.deserialize(txBuf);
    } catch (e) {
        throw new Error('Failed to deserialize Jupiter tx: ' + e.message);
    }

    tx.sign([wallet]);
    const sig = await connection.sendTransaction(tx, { skipPreflight: false, maxRetries: 3 });
    await connection.confirmTransaction(sig, 'finalized');
    return sig;
}

// ---- Create buy / sell Versioned TXs for bonding curve using TransactionMessage.compileToV0Message ----
async function createBuyTransaction(connection, wallet, mint, solAmountLamports, slippageBps = 1000) {
    const bondingCurve = deriveBondingCurve(mint);
    const associatedBondingCurve = deriveAssociatedBondingCurve(bondingCurve, mint);
    const userTokenAccount = await getAssociatedTokenAddress(mint, wallet.publicKey);

    const state = await getBondingCurveState(connection, bondingCurve);
    if (!state) throw new Error('Cannot fetch bonding curve state');

    if (state.complete) throw new Error('Bonding curve is complete, use Jupiter instead');

    const maxSolCost = Math.floor(solAmountLamports * (1 + slippageBps / 10000));

    const recent = await connection.getLatestBlockhash();
    const recentBlockhash = recent.blockhash;

    const instructions = [];
    instructions.push(ComputeBudgetProgram.setComputeUnitLimit({ units: 600_000 }));
    instructions.push(ComputeBudgetProgram.setComputeUnitPrice({ microLamports: 1000000 }));
    instructions.push(createAssociatedTokenAccountInstruction(
        wallet.publicKey,
        userTokenAccount,
        wallet.publicKey,
        mint
    ));
    instructions.push(createBuyInstruction(wallet.publicKey, mint, bondingCurve, associatedBondingCurve, userTokenAccount, solAmountLamports, maxSolCost));

    // Use TransactionMessage.compileToV0Message
    const message = new TransactionMessage({
        payerKey: wallet.publicKey,
        recentBlockhash,
        instructions
    }).compileToV0Message();

    const tx = new VersionedTransaction(message);
    tx.sign([wallet]);
    return tx;
}

async function createSellTransaction(connection, wallet, mint, tokenAmount, slippageBps = 1000) {
    const tokenBN = toBN(tokenAmount);

    const bondingCurve = deriveBondingCurve(mint);
    const associatedBondingCurve = deriveAssociatedBondingCurve(bondingCurve, mint);
    const userTokenAccount = await getAssociatedTokenAddress(mint, wallet.publicKey);

    const state = await getBondingCurveState(connection, bondingCurve);
    if (!state) throw new Error('Cannot fetch bonding curve state');

    if (state.complete) throw new Error('Bonding curve is complete, use Jupiter instead');

    const expectedOutBN = state.virtualSolReserves.mul(tokenBN).div(state.virtualTokenReserves.add(tokenBN));
    const minOutBN = expectedOutBN.mul(new BN(10000 - slippageBps)).div(new BN(10000));
    const minOutNum = bnToNumberSafe(minOutBN);

    const recent = await connection.getLatestBlockhash();
    const recentBlockhash = recent.blockhash;

    const instructions = [];
    instructions.push(ComputeBudgetProgram.setComputeUnitLimit({ units: 600_000 }));
    instructions.push(ComputeBudgetProgram.setComputeUnitPrice({ microLamports: 1000000 }));
    instructions.push(createSellInstruction(wallet.publicKey, mint, bondingCurve, associatedBondingCurve, userTokenAccount, tokenBN, minOutNum));

    const message = new TransactionMessage({
        payerKey: wallet.publicKey,
        recentBlockhash,
        instructions
    }).compileToV0Message();

    const tx = new VersionedTransaction(message);
    tx.sign([wallet]);
    return tx;
}

// ---- Main snipe logic ----
async function snipeToken(tokenAddress, buyAmountSol, poll = false) {
    const seedPhrase = process.env.SOLANA_PRIVATE_KEY?.trim();
    if (!seedPhrase || !bip39.validateMnemonic(seedPhrase)) {
        const errorMsg = 'Invalid seed phrase in SOLANA_PRIVATE_KEY. Must be valid 12/24 words.';
        logToLaravel('error', errorMsg);
        throw new Error(errorMsg);
    }

    const seed = await bip39.mnemonicToSeed(seedPhrase);
    const derivedSeed = derivePath("m/44'/501'/0'/0'", seed.toString('hex')).key;
    const wallet = Keypair.fromSeed(derivedSeed);

    const rpc = process.env.SOLANA_RPC_URL || 'https://api.mainnet-beta.solana.com';
    const connection = new Connection(rpc, 'confirmed');
    const tokenMint = new PublicKey(tokenAddress);

    logToLaravel('info', `Sniping ${buyAmountSol} SOL for ${tokenAddress} with wallet ${wallet.publicKey.toString()}...`);

    const balance = await connection.getBalance(wallet.publicKey);
    const requiredLamports = Math.floor((buyAmountSol + 0.005) * LAMPORTS_PER_SOL);
    if (balance < requiredLamports) {
        const errorMsg = `Insufficient balance: ${balance / LAMPORTS_PER_SOL} SOL. Need >${requiredLamports / LAMPORTS_PER_SOL} SOL.`;
        logToLaravel('error', errorMsg);
        throw new Error(errorMsg);
    }
    logToLaravel('info', `Wallet balance: ${balance / LAMPORTS_PER_SOL} SOL OK.`);

    const dexStatus = await getDexStatus(tokenAddress);
    let buySig = null;
    let sellSig = null;

    const inAmountLamportsBN = new BN(Math.floor(buyAmountSol * LAMPORTS_PER_SOL));

    if (dexStatus && dexStatus.dexId === 'pumpswap') {
        logToLaravel('info', `Token on PumpSwap (Pool: ${dexStatus.poolAddress}, Liquidity: $${dexStatus.liquidity})`);
        logToLaravel('info', 'Using Jupiter for buy...');
        try {
            buySig = await jupiterSwap(connection, wallet, SOL_MINT, tokenMint, inAmountLamportsBN.toString());
            logToLaravel('info', `Buy TX (Jupiter): https://explorer.solana.com/tx/${buySig}`);
        } catch (e) {
            logToLaravel('error', 'Jupiter buy failed: ' + e.message);
            throw e;
        }
    } else {
        const bondingCurve = deriveBondingCurve(tokenMint);
        const state = await getBondingCurveState(connection, bondingCurve);
        if (state && state.complete) {
            logToLaravel('info', 'Bonding curve complete, using Jupiter for buy.');
            try {
                buySig = await jupiterSwap(connection, wallet, SOL_MINT, tokenMint, inAmountLamportsBN.toString());
                logToLaravel('info', `Buy TX (Jupiter): https://explorer.solana.com/tx/${buySig}`);
            } catch (e) {
                logToLaravel('error', 'Jupiter buy failed: ' + e.message);
                throw e;
            }
        } else {
            logToLaravel('info', 'Using direct bonding curve buy.');
            try {
                const buyTx = await createBuyTransaction(connection, wallet, tokenMint, inAmountLamportsBN.toNumber());
                buySig = await connection.sendTransaction(buyTx, { skipPreflight: false, maxRetries: 3 });
                await connection.confirmTransaction(buySig, 'finalized');
                logToLaravel('info', `Buy TX (Bonding Curve): https://explorer.solana.com/tx/${buySig}`);
            } catch (e) {
                logToLaravel('error', 'Direct buy failed: ' + e.message);
                logToLaravel('info', 'Falling back to Jupiter...');
                try {
                    buySig = await jupiterSwap(connection, wallet, SOL_MINT, tokenMint, inAmountLamportsBN.toString());
                    logToLaravel('info', `Fallback Buy TX (Jupiter): https://explorer.solana.com/tx/${buySig}`);
                } catch (fallbackErr) {
                    logToLaravel('error', 'Jupiter fallback buy failed: ' + fallbackErr.message);
                    throw fallbackErr;
                }
            }
        }
    }

    logToLaravel('info', 'Waiting 5 seconds before sell...');
    await new Promise(r => setTimeout(r, 5000));

    const userATA = await getAssociatedTokenAddress(tokenMint, wallet.publicKey);
    let tokenBalanceBN = new BN(0);
    try {
        const tokenAcc = await getAccount(connection, userATA);
        tokenBalanceBN = toBN(tokenAcc.amount);
        logToLaravel('info', `Token balance: ${tokenBalanceBN.toString()}`);
    } catch (err) {
        logToLaravel('error', 'No tokens to sell: ' + (err?.message || err));
        return { buySig, sellSig: null };
    }

    if (tokenBalanceBN.isZero()) {
        logToLaravel('info', 'No tokens bought—skipping sell.');
        return { buySig, sellSig: null };
    }

    if (dexStatus && dexStatus.dexId === 'pumpswap') {
        logToLaravel('info', 'Using Jupiter for sell...');
        try {
            sellSig = await jupiterSwap(connection, wallet, tokenMint, SOL_MINT, tokenBalanceBN.toString());
            logToLaravel('info', `Sell TX (Jupiter): https://explorer.solana.com/tx/${sellSig}`);
        } catch (e) {
            logToLaravel('error', 'Jupiter sell failed: ' + e.message);
            sellSig = null;
        }
    } else {
        const bondingCurve = deriveBondingCurve(tokenMint);
        const state = await getBondingCurveState(connection, bondingCurve);
        if (state && state.complete) {
            logToLaravel('info', 'Bonding curve complete, using Jupiter for sell.');
            try {
                sellSig = await jupiterSwap(connection, wallet, tokenMint, SOL_MINT, tokenBalanceBN.toString());
                logToLaravel('info', `Sell TX (Jupiter): https://explorer.solana.com/tx/${sellSig}`);
            } catch (e) {
                logToLaravel('error', 'Jupiter sell failed: ' + e.message);
                sellSig = null;
            }
        } else {
            logToLaravel('info', 'Using direct bonding curve sell.');
            try {
                const sellTx = await createSellTransaction(connection, wallet, tokenMint, tokenBalanceBN);
                sellSig = await connection.sendTransaction(sellTx, { skipPreflight: false, maxRetries: 3 });
                await connection.confirmTransaction(sellSig, 'finalized');
                logToLaravel('info', `Sell TX (Bonding Curve): https://explorer.solana.com/tx/${sellSig}`);
            } catch (e) {
                logToLaravel('error', 'Direct sell failed: ' + e.message);
                logToLaravel('info', 'Falling back to Jupiter for sell...');
                try {
                    sellSig = await jupiterSwap(connection, wallet, tokenMint, SOL_MINT, tokenBalanceBN.toString());
                    logToLaravel('info', `Fallback Sell TX (Jupiter): https://explorer.solana.com/tx/${sellSig}`);
                } catch (fallbackErr) {
                    logToLaravel('error', 'Jupiter fallback sell failed: ' + fallbackErr.message);
                    sellSig = null;
                }
            }
        }
    }

    logToLaravel('info', 'Snipe complete! Check wallet: https://solscan.io/account/' + wallet.publicKey.toString());
    return { buySig, sellSig };
}

// ---- CLI entrypoint ----
if (import.meta.url === `file://${process.argv[1]}`) {
    (async () => {
        try {
            const args = process.argv.slice(2);
            const tokenAddress = args.find(arg => arg.startsWith('--token='))?.replace('--token=', '');
            const buyAmountSol = parseFloat(args.find(arg => arg.startsWith('--amount='))?.replace('--amount=', '0.001')) || 0.001;
            const poll = args.includes('--poll');

            if (!tokenAddress) {
                logToLaravel('error', 'Usage: node solana-snipe.js --token=ADDRESS --amount=0.001 [--poll]');
                process.exit(1);
            }

            const result = await snipeToken(tokenAddress, buyAmountSol, poll);
            logToLaravel('info', `Result: ${JSON.stringify(result)}`);
            process.exit(0);
        } catch (e) {
            logToLaravel('error', 'Snipe failed: ' + (e?.message || e));
            process.exit(1);
        }
    })();
}

export { snipeToken, toBN };
