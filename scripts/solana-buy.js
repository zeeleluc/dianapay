/*
solana-snipe.js

Full rewrite:
Uses TransactionMessage.compileToV0Message for versioned tx messages

Robust BN / bigint / string handling

Automatic Jupiter fallback if bonding curve is uninitialized or complete

Creates buy/sell records via Laravel API using --identifier

Clear logging to Laravel log path

Updates: Enhanced API retries/logging, verbose mode, env validation, broader Jupiter fallback.

Ensure .env contains:
SOLANA_PRIVATE_KEY=<mnemonic or bs58>
SOLANA_RPC_URL=<rpc url>
APP_URL=<url to api>
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
import bs58 from 'bs58'; // npm i bs58
dotenv.config();

const httpsAgent = new https.Agent({ rejectUnauthorized: false });
const LARAVEL_LOG_PATH = './storage/logs/laravel.log';
const SOL_MINT = new PublicKey('So11111111111111111111111111111111111111112');
const PUMP_FUN_PROGRAM = new PublicKey('6EF8rrecthR5Dkzon8Nwu78hRvfCKubJ14M5uBEwF6P');
const GLOBAL = new PublicKey('4wTV1YmiEkRvAtNtsSGPtUrqRYQMe5SKy2uB4haw8tqK');
const FEE_RECIPIENT = new PublicKey('CebN5WGQ4jvTD3mcN9gUjBGxx3fUXGF9riMtfuGAjNbJ');
const BONDING_CURVE_SEED = Buffer.from('bonding-curve');
const ASSOCIATED_BONDING_CURVE_SEED = Buffer.from('associated-bonding-curve');
const BUY_DISCRIMINATOR = Buffer.from([0x66,0x06,0x3d,0x12,0x01,0xda,0xeb,0xea]);

function deriveGlobal() {
    return PublicKey.findProgramAddressSync([Buffer.from("global")], PUMP_FUN_PROGRAM)[0];
}

async function logToLaravel(level, message, verbose = false) {
    const timestamp = new Date().toISOString();
    const line = `[${timestamp}] ${level.toUpperCase()}: ${message}\n`;
    // Still write to Laravel log file for local debugging
    try { fs.appendFileSync(LARAVEL_LOG_PATH, line); } catch (_) {}

    console.log(line.trim());

    // Send to Laravel API (which forwards to Slack)
    if (process.env.APP_URL) {
        try {
            const res = await fetch(`${process.env.APP_URL}/api/logs`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ level, message }),
            });
            if (verbose) {
                const respBody = await res.text();
                console.log(`[VERBOSE] Log API Response: ${res.status} - ${respBody}`);
            }
            if (!res.ok) throw new Error(`API responded ${res.status}: ${await res.text()}`);
        } catch (e) {
            console.error(`Failed to send log to API: ${e.message}`);
            if (verbose) logToLaravel('error', `Log API detailed error: ${e.stack}`, false);
        }
    } else {
        console.warn('[WARN] APP_URL not set, skipping API logs (no Slack).');
    }
}

const MAX_ERROR_LENGTH = 200; // Reduced for VARCHAR(255) columns; increase post-DB migration
const API_RETRIES = 3;

async function createSolanaCallOrder({ solana_call_id, type, tx_signature=null, dex_used=null, amount_sol=null, amount_foreign=null, error=null }, verbose = false){
    if (!process.env.APP_URL) {
        logToLaravel('error', 'APP_URL not set - cannot create SolanaCallOrder record!', verbose);
        throw new Error('Missing APP_URL');
    }
    try {
        if(error && error.length > MAX_ERROR_LENGTH){
            error = error.slice(0, MAX_ERROR_LENGTH) + '...'; // truncate long errors
        }

        // Detect potential truncation loops and force short error
        if (error && (error.includes('SQLSTATE[22001]') || error.includes('Data too long'))) {
            error = 'DB truncation error (logs too long for column)';
            logToLaravel('warn', `Shortened error for DB: ${error}`, verbose);
        }

        const body = { solana_call_id, type, tx_signature, dex_used, amount_sol, amount_foreign, error };
        let lastErr;
        for (let retry = 1; retry <= API_RETRIES; retry++) {
            try {
                const res = await fetch(`${process.env.APP_URL}/api/solana-call-orders`, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify(body)
                });
                if (verbose) {
                    const respBody = await res.text();
                    logToLaravel('info', `Record API Response (attempt ${retry}): ${res.status} - ${respBody}`, false);
                }
                if (res.ok) {
                    logToLaravel('info', `Created ${type} record for ${solana_call_id}`, verbose);
                    return; // Success
                }
                lastErr = new Error(`API responded ${res.status}: ${await res.text()}`);
                if (retry < API_RETRIES) await new Promise(r => setTimeout(r, 1000 * retry));
            } catch (e) {
                lastErr = e;
                if (retry < API_RETRIES) await new Promise(r => setTimeout(r, 1000 * retry));
            }
        }
        throw lastErr || new Error('Unknown API failure after retries');
    } catch(e){
        const errMsg = `Failed to create ${type} SolanaCallOrder record for ${solana_call_id}: ${e.message}`;
        logToLaravel('error', errMsg, verbose);
        throw new Error(errMsg); // Propagate to caller
    }
}

function toBNLamports(solAmount) {
    // Round up to ensure even tiny amounts actually get sent
    const lamports = Math.ceil(solAmount * LAMPORTS_PER_SOL);
    return new BN(lamports);
}

function bnToNumberSafe(bn){
    if(!BN.isBN(bn)) bn = toBNLamports(bn);
    const max = new BN(Number.MAX_SAFE_INTEGER.toString());
    if(bn.gt(max)) throw new Error('BN too large to convert safely');
    return bn.toNumber();
}

function deriveBondingCurve(mint){ return PublicKey.findProgramAddressSync([BONDING_CURVE_SEED,mint.toBuffer()],PUMP_FUN_PROGRAM)[0]; }
function deriveAssociatedBondingCurve(bondingCurve,mint){ return PublicKey.findProgramAddressSync([bondingCurve.toBuffer(),TOKEN_PROGRAM_ID.toBuffer(),mint.toBuffer()],ASSOCIATED_TOKEN_PROGRAM_ID)[0]; }

async function getMintProgramId(connection, mint) {
    const mintInfo = await connection.getAccountInfo(mint);
    if (!mintInfo) throw new Error('Mint not found');
    return mintInfo.owner;
}

const TOKEN_2022_PROGRAM = new PublicKey('TokenzQdBNbLqP5VEhdkAS6EPFLC1PHnBqCXEpPxuEb');

// Update createBuyInstruction (dynamic token/system, add WSOL ATA)
async function createBuyInstruction(connection, user, mint, bondingCurve, associatedBondingCurve, userATA, solLamportsBN, maxSolLamportsBN) {
    const tokenProgramId = await getMintProgramId(connection, mint);
    const userWSOLATA = await getAssociatedTokenAddress(SOL_MINT, user);

    // Encode lamports directly as BN
    const data = Buffer.concat([
        BUY_DISCRIMINATOR,
        solLamportsBN.toArrayLike(Buffer, 'le', 8),
        maxSolLamportsBN.toArrayLike(Buffer, 'le', 8)
    ]);

    const keys = [
        { pubkey: deriveGlobal(), isSigner: false, isWritable: false },
        { pubkey: FEE_RECIPIENT, isSigner: false, isWritable: true },
        { pubkey: mint, isSigner: false, isWritable: false },
        { pubkey: bondingCurve, isSigner: false, isWritable: true },
        { pubkey: associatedBondingCurve, isSigner: false, isWritable: true },
        { pubkey: userATA, isSigner: false, isWritable: true },
        { pubkey: userWSOLATA, isSigner: false, isWritable: true },
        { pubkey: user, isSigner: true, isWritable: true },
        { pubkey: SOL_MINT, isSigner: false, isWritable: false },
        { pubkey: SystemProgram.programId, isSigner: false, isWritable: false },
        { pubkey: tokenProgramId, isSigner: false, isWritable: false },
        { pubkey: ASSOCIATED_TOKEN_PROGRAM_ID, isSigner: false, isWritable: false },
        { pubkey: PUMP_FUN_PROGRAM, isSigner: false, isWritable: false }
    ];

    return new TransactionInstruction({ programId: PUMP_FUN_PROGRAM, keys, data });
}

async function getBondingCurveState(connection, bondingCurve){
    try {
        const info = await connection.getAccountInfo(bondingCurve);
        if (!info) return null;

        // ✅ NEW: skip if not owned by PumpFun
        if (!info.owner.equals(PUMP_FUN_PROGRAM)) {
            return null; // not a PumpFun bonding curve
        }

        const d = info.data;
        if (!d || d.length < 49) {
            return null; // malformed account data
        }

        return {
            virtualTokenReserves: new BN(d.slice(8,16), 'le'),
            virtualSolReserves:   new BN(d.slice(16,24), 'le'),
            realTokenReserves:    new BN(d.slice(24,32), 'le'),
            realSolReserves:      new BN(d.slice(32,40), 'le'),
            tokenTotalSupply:     new BN(d.slice(40,48), 'le'),
            complete: d[48] === 1
        };
    } catch (e) {
        logToLaravel('error', 'Failed to parse bonding curve: ' + e.message);
        return null;
    }
}


async function fetchWithRateLimit(url,options={},retries=3){
    for(let i=0;i<retries;i++){
        try{
            const res = await fetch(url,{...options,agent:httpsAgent});
            if(res.status===429){ await new Promise(r=>setTimeout(r,20*Math.pow(2,i))); continue; }
            if(!res.ok) throw new Error(await res.text());
            return await res.json();
        }catch(e){ if(i===retries-1) throw e; await new Promise(r=>setTimeout(r,1000*(i+1))); }
    }
}

async function getDexStatus(tokenAddress){
    try{
        const res = await fetchWithRateLimit(`https://api.dexscreener.com/latest/dex/tokens/${tokenAddress}`);
        if(res?.pairs?.length) return {dexId:res.pairs[0].dexId,poolAddress:res.pairs[0].pairAddress,liquidity:res.pairs[0].liquidity?.usd??null, priceUsd: res.pairs[0].priceUsd};
    }catch(e){ logToLaravel('error','Dex fetch failed: '+(e.message||e)); }
    return null;
}

async function jupiterSwap(connection,wallet,inputMint,outputMint,amount,slippageBps=500){
    const quoteUrl='https://quote-api.jup.ag/v6/quote';
    const swapUrl='https://quote-api.jup.ag/v6/swap';
    const amountStr = BN.isBN(amount)?amount.toString():String(amount);
    const qParams = new URLSearchParams({inputMint:inputMint.toString(),outputMint:outputMint.toString(),amount:amountStr,slippageBps:String(slippageBps)});
    const qRes = await fetch(`${quoteUrl}?${qParams}`,{agent:httpsAgent});
    const qData = await qRes.json();
    if(!qRes.ok) throw new Error('Jupiter quote failed: '+JSON.stringify(qData));
    const sRes = await fetch(swapUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({quoteResponse:qData,userPublicKey:wallet.publicKey.toString(),wrapAndUnwrapSol:true,computeUnitPriceMicroLamports:1000000}), agent:httpsAgent});
    const sData = await sRes.json();
    if(!sRes.ok) throw new Error('Jupiter swap failed: '+JSON.stringify(sData));
    const tx = VersionedTransaction.deserialize(Buffer.from(sData.swapTransaction,'base64'));
    tx.sign([wallet]);
    const sig = await connection.sendTransaction(tx,{skipPreflight:false,maxRetries:3});
    await connection.confirmTransaction(sig,'finalized');
    return sig;
}

async function executeBuy(connection, wallet, mint, buyAmountSol, solanaCallId, verbose = false) {
    try {
        // Convert SOL → lamports as BN immediately
        const inLamportsBN = toBNLamports(buyAmountSol);
        const maxSolLamportsBN = inLamportsBN.muln(101).divn(100); // +1% slippage

        const bc = deriveBondingCurve(mint);
        const abc = deriveAssociatedBondingCurve(bc, mint);
        const userATA = await getAssociatedTokenAddress(mint, wallet.publicKey);

        // Fetch bonding curve state
        const state = await getBondingCurveState(connection, bc);
        logToLaravel('info', `Bonding curve state for ${mint.toString()}: ${state ? `complete=${state.complete}, realSolReserves=${state.realSolReserves.toString()}` : 'uninitialized'}`, verbose);

        let txSig, dexUsed;

        if (!state || state.complete) {
            // Use Jupiter swap if curve is uninitialized or complete
            dexUsed = 'jupiter';
            txSig = await jupiterSwap(connection, wallet, SOL_MINT, mint, inLamportsBN.toString(), 1000);
        } else {
            // On-curve buy
            const { blockhash } = await connection.getLatestBlockhash();

            const instructions = [
                ComputeBudgetProgram.setComputeUnitLimit({ units: 600_000 }),
                ComputeBudgetProgram.setComputeUnitPrice({ microLamports: 1_000_000 }),
                createAssociatedTokenAccountInstruction(wallet.publicKey, userATA, wallet.publicKey, mint),
                await createBuyInstruction(connection, wallet.publicKey, mint, bc, abc, userATA, inLamportsBN, maxSolLamportsBN)
            ];

            const msg = new TransactionMessage({
                payerKey: wallet.publicKey,
                recentBlockhash: blockhash,
                instructions
            }).compileToV0Message();

            const tx = new VersionedTransaction(msg);
            tx.sign([wallet]);

            txSig = await connection.sendTransaction(tx, { skipPreflight: false, maxRetries: 3 });
            await connection.confirmTransaction(txSig, 'finalized');
            dexUsed = 'bonding-curve';
        }

        // Post-buy token balance
        let tokenBalanceBN = new BN(0);
        try {
            const acc = await getAccount(connection, userATA);
            tokenBalanceBN = new BN(acc.amount.toString()); // Already integer
            logToLaravel('info', `Post-buy token balance: ${tokenBalanceBN.toString()}`, verbose);
        } catch (e) {
            logToLaravel('warn', `Failed to fetch post-buy balance: ${e.message}`, verbose);
        }

        // Record buy
        await createSolanaCallOrder({
            solana_call_id: solanaCallId,
            type: 'buy',
            amount_sol: buyAmountSol,
            amount_foreign: tokenBalanceBN.toNumber(),
            dex_used: dexUsed,
            tx_signature: txSig
        }, verbose);

        return { txSig, dexUsed, tokenBalanceBN };

    } catch (e) {
        const errorMsg = e.logs ? e.logs.join('\n') : e.message || String(e);
        logToLaravel('error', `Buy failed: ${errorMsg}`, verbose);
        await createSolanaCallOrder({ solana_call_id: solanaCallId, type: 'failed', error: errorMsg }, verbose);
        throw new Error('Buy failed: ' + errorMsg);
    }
}


// ---- Main Snipe with error fallback ----
async function snipeToken(tokenAddress, buyAmountSol, solanaCallId, verbose = false) {
    if (!process.env.APP_URL) {
        throw new Error('Missing APP_URL in .env - required for records/Slack');
    }
    logToLaravel('info', `Using APP_URL: ${process.env.APP_URL} (verbose: ${verbose})`, verbose);

    const keyString = process.env.SOLANA_PRIVATE_KEY?.trim();
    if (!keyString) throw new Error('Missing SOLANA_PRIVATE_KEY');

    let wallet;
    try {
        const secretKey = keyString.length > 50 ? bs58.decode(keyString) : Uint8Array.from(JSON.parse(keyString));
        wallet = Keypair.fromSecretKey(secretKey);
    } catch (e) {
        throw new Error('Invalid SOLANA_PRIVATE_KEY format: ' + e.message);
    }

    const rpc = process.env.SOLANA_RPC_URL || 'https://api.mainnet-beta.solana.com';
    const connection = new Connection(rpc, 'confirmed');
    const mint = new PublicKey(tokenAddress);

    logToLaravel('info', `Sniping ${buyAmountSol} SOL for ${tokenAddress} with wallet ${wallet.publicKey.toString()}`, verbose);

    const balance = await connection.getBalance(wallet.publicKey);
    const requiredLamports = Math.floor((buyAmountSol + 0.005) * LAMPORTS_PER_SOL);
    if (balance < requiredLamports) {
        const errMsg = `Insufficient balance: ${balance / LAMPORTS_PER_SOL} SOL`;
        await createSolanaCallOrder({ solana_call_id: solanaCallId, type: 'failed', error: errMsg }, verbose);
        throw new Error(errMsg);
    }

    let buySig = null;
    let tokenBalanceBN = new BN(0);
    let dexUsed = 'unknown';
    let buyAmountForeign = 0;
    let solBalanceAfterBuy = balance;

    // --- Execute Buy Safely ---
    try {
        const buyResult = await executeBuy(connection, wallet, mint, buyAmountSol, solanaCallId, verbose);
        buySig = buyResult.txSig;
        dexUsed = buyResult.dexUsed;
        tokenBalanceBN = buyResult.tokenBalanceBN;
        buyAmountForeign = bnToNumberSafe(tokenBalanceBN);
        solBalanceAfterBuy = await connection.getBalance(wallet.publicKey);
    } catch (e) {
        const errMsg = e.message || String(e);
        logToLaravel('error', 'Buy failed: ' + errMsg, verbose);
        await createSolanaCallOrder({
            solana_call_id: solanaCallId,
            type: 'buy',
            amount_sol: buyAmountSol,
            amount_foreign: 0,
            dex_used: dexUsed,
            tx_signature: buySig,
            error: errMsg
        }, verbose);
        throw new Error('Buy failed: ' + errMsg);
    }

    return { buySig };
}

// ---- CLI ----
if (import.meta.url === `file://${process.argv[1]}`) {
    (async () => {
        try {
            const args = process.argv.slice(2);
            const tokenAddress = args.find(a => a.startsWith('--token='))?.replace('--token=', '');
            // Extract the amount, default to 0.001 only if flag is missing or invalid
            const amountArg = args.find(a => a.startsWith('--amount='));
            const buyAmountSol = amountArg ? parseFloat(amountArg.replace('--amount=', '')) || 0.001 : 0.001;
            const solanaCallId = args.find(a => a.startsWith('--identifier='))?.replace('--identifier=', '');
            const verbose = args.includes('--verbose');

            if (!tokenAddress || !solanaCallId) {
                logToLaravel('error', 'Usage: node solana-snipe.js --identifier=ID --token=ADDRESS --amount=0.001 [--verbose]');
                process.exit(1);
            }

            const res = await snipeToken(tokenAddress, buyAmountSol, solanaCallId, verbose);
            logToLaravel('info', 'Result: ' + JSON.stringify(res), verbose);
            process.exit(0);
        } catch (e) {
            logToLaravel('error', 'Snipe failed: ' + (e.message || e));
            process.exit(1);
        }
    })();
}

export { snipeToken, toBNLamports };
