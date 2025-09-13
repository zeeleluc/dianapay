/**
 * solana-snipe.js
 *
 * Full rewrite:
 * - Uses TransactionMessage.compileToV0Message for versioned tx messages
 * - Robust BN / bigint / string handling
 * - Automatic Jupiter fallback if bonding curve is uninitialized or complete
 * - Creates buy/sell records via Laravel API using --identifier
 * - Clear logging to Laravel log path
 * Updates: Enhanced API retries/logging, verbose mode, env validation, broader Jupiter fallback.
 *
 * Ensure .env contains:
 *  SOLANA_PRIVATE_KEY=<mnemonic or bs58>
 *  SOLANA_RPC_URL=<rpc url>
 *  APP_URL=<url to api>
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
const SELL_DISCRIMINATOR = Buffer.from([0x33,0xe6,0x85,0xa4,0x01,0x7f,0x83,0xad]);

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

function toBN(value){
    if(BN.isBN(value)) return value;
    if(typeof value==='bigint') return new BN(value.toString());
    if(typeof value==='number') return new BN(Math.floor(value));
    if(typeof value==='string') return new BN(value.split('.')[0]);
    return new BN(String(value));
}

function bnToNumberSafe(bn){
    if(!BN.isBN(bn)) bn = toBN(bn);
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
async function createBuyInstruction(connection, user, mint, bondingCurve, associatedBondingCurve, userATA, solAmount, maxSolCost) {
    const tokenProgramId = await getMintProgramId(connection, mint);
    const userWSOLATA = await getAssociatedTokenAddress(SOL_MINT, user);
    const data = Buffer.concat([BUY_DISCRIMINATOR, new BN(solAmount).toArrayLike(Buffer, 'le', 8), new BN(maxSolCost).toArrayLike(Buffer, 'le', 8)]);
    const keys = [
        { pubkey: deriveGlobal(), isSigner: false, isWritable: false },
        { pubkey: FEE_RECIPIENT, isSigner: false, isWritable: true },
        { pubkey: mint, isSigner: false, isWritable: false },
        { pubkey: bondingCurve, isSigner: false, isWritable: true },
        { pubkey: associatedBondingCurve, isSigner: false, isWritable: true },
        { pubkey: userATA, isSigner: false, isWritable: true },
        { pubkey: userWSOLATA, isSigner: false, isWritable: true }, // Fixed: WSOL ATA
        { pubkey: user, isSigner: true, isWritable: true },
        { pubkey: SOL_MINT, isSigner: false, isWritable: false }, // SOL mint
        { pubkey: SystemProgram.programId, isSigner: false, isWritable: false },
        { pubkey: tokenProgramId, isSigner: false, isWritable: false }, // Dynamic token program
        { pubkey: ASSOCIATED_TOKEN_PROGRAM_ID, isSigner: false, isWritable: false },
        { pubkey: PUMP_FUN_PROGRAM, isSigner: false, isWritable: false }
    ];
    return new TransactionInstruction({ programId: PUMP_FUN_PROGRAM, keys, data });
}

// Update createSellInstruction (dynamic token program)
async function createSellInstruction(connection, user, mint, bondingCurve, associatedBondingCurve, userATA, tokenAmount, minSolOut) {
    const tokenProgramId = await getMintProgramId(connection, mint);
    const data = Buffer.concat([SELL_DISCRIMINATOR, new BN(tokenAmount).toArrayLike(Buffer, 'le', 8), new BN(minSolOut).toArrayLike(Buffer, 'le', 8)]);
    const keys = [
        { pubkey: deriveGlobal(), isSigner: false, isWritable: false },
        { pubkey: FEE_RECIPIENT, isSigner: false, isWritable: true },
        { pubkey: mint, isSigner: false, isWritable: false },
        { pubkey: bondingCurve, isSigner: false, isWritable: true },
        { pubkey: associatedBondingCurve, isSigner: false, isWritable: true },
        { pubkey: userATA, isSigner: false, isWritable: true },
        { pubkey: user, isSigner: true, isWritable: true },
        { pubkey: SystemProgram.programId, isSigner: false, isWritable: false },
        { pubkey: tokenProgramId, isSigner: false, isWritable: false }, // Dynamic token program
        { pubkey: ASSOCIATED_TOKEN_PROGRAM_ID, isSigner: false, isWritable: false },
        { pubkey: PUMP_FUN_PROGRAM, isSigner: false, isWritable: false }
    ];
    return new TransactionInstruction({ programId: PUMP_FUN_PROGRAM, keys, data });
}

async function getBondingCurveState(connection,bondingCurve){
    try{
        const info = await connection.getAccountInfo(bondingCurve);
        if(!info) return null;
        const d = info.data;
        return {
            virtualTokenReserves:new BN(d.slice(8,16),'le'),
            virtualSolReserves:new BN(d.slice(16,24),'le'),
            realTokenReserves:new BN(d.slice(24,32),'le'),
            realSolReserves:new BN(d.slice(32,40),'le'),
            tokenTotalSupply:new BN(d.slice(40,48),'le'),
            complete:d[48]===1
        };
    }catch(e){ logToLaravel('error','Failed to parse bonding curve: '+e.message); return null; }
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
        if(res?.pairs?.length) return {dexId:res.pairs[0].dexId,poolAddress:res.pairs[0].pairAddress,liquidity:res.pairs[0].liquidity?.usd??null};
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

// ---- Execute Buy (with broader fallback) ----
async function executeBuy(connection, wallet, mint, buyAmountSol, solanaCallId, verbose = false) {
    const inLamportsBN = new BN(Math.floor(buyAmountSol * LAMPORTS_PER_SOL));
    const bc = deriveBondingCurve(mint);
    const abc = deriveAssociatedBondingCurve(bc, mint);
    const userATA = await getAssociatedTokenAddress(mint, wallet.publicKey);
    const state = await getBondingCurveState(connection, bc);

    logToLaravel('info', `Bonding curve state for ${mint.toString()}: ${state ? `complete=${state.complete}, reserves=${state.realSolReserves.toString()}` : 'null/uninitialized'}`, verbose);

    let txSig, dexUsed;
    try {
        if (!state || state.complete) {
            dexUsed = 'jupiter';
            txSig = await jupiterSwap(connection, wallet, SOL_MINT, mint, inLamportsBN.toString(), 1000);
        } else {
            const maxSol = Math.floor(inLamportsBN.toNumber() * 1.01);
            const { blockhash } = await connection.getLatestBlockhash();
            const instructions = [
                ComputeBudgetProgram.setComputeUnitLimit({ units: 600_000 }),
                ComputeBudgetProgram.setComputeUnitPrice({ microLamports: 1000000 }),
                createAssociatedTokenAccountInstruction(wallet.publicKey, userATA, wallet.publicKey, mint),
                await createBuyInstruction(connection, wallet.publicKey, mint, bc, abc, userATA, inLamportsBN.toNumber(), maxSol) // Async for dynamic ID
            ];
            const msg = new TransactionMessage({ payerKey: wallet.publicKey, recentBlockhash: blockhash, instructions }).compileToV0Message();
            const tx = new VersionedTransaction(msg);
            tx.sign([wallet]);

            txSig = await connection.sendTransaction(tx, { skipPreflight: false, maxRetries: 3 });
            await connection.confirmTransaction(txSig, 'finalized');
            dexUsed = 'bonding-curve';
        }
    } catch (e) {
        const errorMsg = e.logs ? e.logs.join('\n') : e.message || String(e);
        logToLaravel('error', 'Buy failed (Bonding curve tx): ' + errorMsg, verbose);
        await createSolanaCallOrder({ solana_call_id: solanaCallId, type: 'failed', error: errorMsg }, verbose);
        throw new Error('Buy failed: ' + errorMsg);
    }

    let tokenBalanceBN = new BN(0);
    try {
        const acc = await getAccount(connection, userATA);
        tokenBalanceBN = toBN(acc.amount);
        logToLaravel('info', `Post-buy token balance: ${bnToNumberSafe(tokenBalanceBN)}`, verbose);
    } catch (e) {
        logToLaravel('warn', `Failed to fetch post-buy balance: ${e.message}`, verbose);
    }

    await createSolanaCallOrder({
        solana_call_id: solanaCallId,
        type: 'buy',
        amount_sol: buyAmountSol,
        amount_foreign: bnToNumberSafe(tokenBalanceBN),
        dex_used: dexUsed,
        tx_signature: txSig
    }, verbose);

    return { txSig, dexUsed };
}

// Updated executeSell (Jupiter fallback, dynamic createSellInstruction)
// ---- Execute Sell with enhanced error logging ----
async function executeSell(connection,wallet,mint,tokenAmountBN,solanaCallId, verbose = false){
    try {
        logToLaravel('info', `Attempting sell: ${bnToNumberSafe(tokenAmountBN)} tokens`, verbose);
        const bc = deriveBondingCurve(mint);
        const state = await getBondingCurveState(connection,bc);

        // Sell partial to avoid slippage: 80% of balance
        const sellAmountBN = tokenAmountBN.mul(new BN(80)).div(new BN(100));
        const balanceBefore = await connection.getBalance(wallet.publicKey);
        const userATA = await getAssociatedTokenAddress(mint,wallet.publicKey);

        let txSig, dexUsed;

        if(!state || state.complete){
            // For complete curves, always attempt Jupiter sell (no reserve check needed - uses Raydium pool)
            dexUsed = 'jupiter';
            try {
                txSig = await jupiterSwap(connection,wallet,mint,SOL_MINT,sellAmountBN.toString(),1500); // 15% slippage for volatiles
            } catch(e) {
                const errorMsg = e.logs ? e.logs.join('\n') : e.message || String(e);
                logToLaravel('error','Sell failed (Jupiter swap): '+errorMsg, verbose);
                await createSolanaCallOrder({ solana_call_id: solanaCallId, type:'failed', error:errorMsg }, verbose);
                return { txSig:null, dexUsed:null };
            }
        } else {
            // Only check reserves for non-complete (on-curve sells)
            if (state.realSolReserves.lt(new BN(100000000))) { // 0.1 SOL
                const errorMsg = `Insufficient reserves for on-curve sell: ${state.realSolReserves.toString()}`;
                logToLaravel('warn', errorMsg, verbose);
                await createSolanaCallOrder({ solana_call_id: solanaCallId, type: 'failed', error: errorMsg }, verbose);
                return { txSig: null, dexUsed: null };
            }

            const expectedOut = state.virtualSolReserves.mul(sellAmountBN).div(state.virtualTokenReserves.add(sellAmountBN));
            const minOut = expectedOut.mul(new BN(9900)).div(new BN(10000));
            const minNum = bnToNumberSafe(minOut);

            const {blockhash} = await connection.getLatestBlockhash();
            const instructions=[
                ComputeBudgetProgram.setComputeUnitLimit({units:600_000}),
                ComputeBudgetProgram.setComputeUnitPrice({microLamports:1000000}),
                createSellInstruction(wallet.publicKey,mint,bc,deriveAssociatedBondingCurve(bc,mint),userATA,sellAmountBN,minNum)
            ];

            const msg = new TransactionMessage({payerKey:wallet.publicKey,recentBlockhash:blockhash,instructions}).compileToV0Message();
            const tx = new VersionedTransaction(msg);
            tx.sign([wallet]);

            try {
                txSig = await connection.sendTransaction(tx,{skipPreflight:false,maxRetries:3});
                await connection.confirmTransaction(txSig,'finalized');
                dexUsed = 'bonding-curve';
            } catch(e) {
                const errorMsg = e.logs ? e.logs.join('\n') : e.message || String(e);
                logToLaravel('error','Sell failed (Bonding curve tx): '+errorMsg, verbose);
                await createSolanaCallOrder({ solana_call_id: solanaCallId, type:'failed', error:errorMsg }, verbose);
                return { txSig:null, dexUsed:null };
            }
        }

        const balanceAfter = await connection.getBalance(wallet.publicKey);
        const amountSolReceived = (balanceAfter - balanceBefore) / LAMPORTS_PER_SOL;

        await createSolanaCallOrder({
            solana_call_id: solanaCallId,
            type: 'sell',
            amount_foreign: bnToNumberSafe(sellAmountBN),
            amount_sol: amountSolReceived,
            dex_used: dexUsed,
            tx_signature: txSig
        }, verbose);

        return {txSig,dexUsed};

    } catch(e) {
        const errorMsg = e.logs ? e.logs.join('\n') : e.message || String(e);
        logToLaravel('error','Sell failed: '+errorMsg, verbose);
        await createSolanaCallOrder({ solana_call_id: solanaCallId, type: 'failed', error: errorMsg }, verbose);
        return {txSig:null,dexUsed:null};
    }
}

// ---- Main Snipe with error fallback ----
async function snipeToken(tokenAddress, buyAmountSol, solanaCallId, strategy = '5-SEC-SELL', verbose = false) {
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

    // --- Execute Buy Safely ---
    try {
        ({ txSig: buySig, dexUsed } = await executeBuy(connection, wallet, mint, buyAmountSol, solanaCallId, verbose));
        // Attempt to fetch primary ATA first
        let tokenBalanceBN = new BN(0);
        const userATA = await getAssociatedTokenAddress(mint, wallet.publicKey);

        const acc = await getAccount(connection, userATA).catch(() => null);
        if (acc) {
            tokenBalanceBN = toBN(acc.amount);
        } else {
            logToLaravel('warn', 'Primary ATA not found, checking all token accounts...', verbose);
            const tokenAccounts = await connection.getTokenAccountsByOwner(wallet.publicKey, { mint });
            for (const t of tokenAccounts.value) {
                const parsed = t.account.data;
                const amount = parseInt(parsed.parsed?.info?.tokenAmount?.amount || 0);
                tokenBalanceBN = tokenBalanceBN.add(toBN(amount));
            }
        }

        logToLaravel('info', `Token balance after buy: ${bnToNumberSafe(tokenBalanceBN)}`, verbose);

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

    // --- Handle Sell ---
    let sellSig = null;
    let waitSeconds = 5;
    const match = /^(\d+)-SEC-SELL$/i.exec(strategy);
    if (match) waitSeconds = parseInt(match[1], 10);

    logToLaravel('info', `⏳ Waiting ${waitSeconds} seconds before selling (strategy: ${strategy})`, verbose);
    await new Promise(r => setTimeout(r, waitSeconds * 1000));

    if (!tokenBalanceBN.isZero()) {
        try {
            ({ txSig: sellSig } = await executeSell(connection, wallet, mint, tokenBalanceBN, solanaCallId, verbose));
        } catch (e) {
            const errMsg = e.message || String(e);
            logToLaravel('error', 'Sell failed: ' + errMsg, verbose);
            await createSolanaCallOrder({ solana_call_id: solanaCallId, type: 'failed', error: errMsg }, verbose);
        }
    } else {
        logToLaravel('warn', 'Token balance is zero, skipping sell.', verbose);
    }

    logToLaravel('info', 'Snipe complete! Wallet: https://solscan.io/account/' + wallet.publicKey.toString(), verbose);
    return { buySig, sellSig };
}

// ---- CLI ----
if (import.meta.url === `file://${process.argv[1]}`) {
    (async () => {
        try {
            const args = process.argv.slice(2);
            const tokenAddress = args.find(a => a.startsWith('--token='))?.replace('--token=', '');
            const buyAmountSol = parseFloat(args.find(a => a.startsWith('--amount='))?.replace('--amount=', '0.001')) || 0.001;
            const solanaCallId = args.find(a => a.startsWith('--identifier='))?.replace('--identifier=', '');
            const strategy = args.find(a => a.startsWith('--strategy='))?.replace('--strategy=', '') || '5-SEC-SELL';
            const verbose = args.includes('--verbose');

            if (!tokenAddress || !solanaCallId) {
                logToLaravel('error', 'Usage: node solana-snipe.js --identifier=ID --token=ADDRESS --amount=0.001 --strategy=10-SEC-SELL [--verbose]');
                process.exit(1);
            }

            const res = await snipeToken(tokenAddress, buyAmountSol, solanaCallId, strategy, verbose);
            logToLaravel('info', 'Result: ' + JSON.stringify(res), verbose);
            process.exit(0);
        } catch (e) {
            logToLaravel('error', 'Snipe failed: ' + (e.message || e));
            process.exit(1);
        }
    })();
}

export { snipeToken, toBN };
