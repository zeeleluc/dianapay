import { Connection, Keypair, PublicKey, VersionedTransaction, TransactionMessage, ComputeBudgetProgram, SystemProgram } from '@solana/web3.js';
import { getAssociatedTokenAddress, getAccount, TOKEN_PROGRAM_ID, ASSOCIATED_TOKEN_PROGRAM_ID, getMint, createAssociatedTokenAccountInstruction } from '@solana/spl-token';
import { BN } from 'bn.js';
import fetch from 'node-fetch';
import https from 'node:https';
import fs from 'fs';
import bs58 from 'bs58';
import dotenv from 'dotenv';
dotenv.config();

const httpsAgent = new https.Agent({ rejectUnauthorized: false });
const LARAVEL_LOG_PATH = './storage/logs/laravel.log';
const SOL_MINT = new PublicKey('So11111111111111111111111111111111111111112');

// ----- Logging -----
async function logToLaravel(level, message) {
    const timestamp = new Date().toISOString();
    const line = `[${timestamp}] ${level.toUpperCase()}: ${message}\n`;
    try { fs.appendFileSync(LARAVEL_LOG_PATH, line); } catch (_) {}
    console.log(line.trim());
    if (process.env.APP_URL) {
        try {
            await fetch(`${process.env.APP_URL}/api/logs`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ level, message }),
            });
        } catch (_) {}
    }
}

// ----- Create Order Record -----
async function createSolanaCallOrder({ solana_call_id, type, tx_signature = null, dex_used = null, amount_sol = null, amount_foreign = null, error = null }) {
    try {
        await fetch(`${process.env.APP_URL}/api/solana-call-orders`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ solana_call_id, type, tx_signature, dex_used, amount_sol, amount_foreign, error })
        });
    } catch (e) {
        logToLaravel('error', `Failed to create SolanaCallOrder record: ${e.message || e}`);
    }
}

// ----- BN Helpers -----
function toBN(value) {
    if (BN.isBN(value)) return value;
    if (typeof value === 'bigint') return new BN(value.toString());
    if (typeof value === 'number') return new BN(Math.floor(value));
    if (typeof value === 'string') return new BN(value.split('.')[0]);
    return new BN(String(value));
}

function bnToNumberSafe(bn) {
    if (!BN.isBN(bn)) bn = toBN(bn);
    const max = new BN(Number.MAX_SAFE_INTEGER.toString());
    if (bn.gt(max)) throw new Error('BN too large to convert safely');
    return bn.toNumber();
}

// ----- Jupiter Swap with Retry Logic -----
async function jupiterSwap(connection, wallet, inputMint, outputMint, amount, slippageBps = 500, retries = 3) {
    const quoteUrl = 'https://quote-api.jup.ag/v6/quote';
    const swapUrl = 'https://quote-api.jup.ag/v6/swap';
    const amountStr = BN.isBN(amount) ? amount.toString() : String(amount);

    for (let attempt = 1; attempt <= retries; attempt++) {
        try {
            const currentSlippage = slippageBps + (attempt - 1) * 500; // Increase slippage by 5% per retry
            const qParams = new URLSearchParams({
                inputMint: inputMint.toString(),
                outputMint: outputMint.toString(),
                amount: amountStr,
                slippageBps: String(currentSlippage)
            });

            const qRes = await fetch(`${quoteUrl}?${qParams}`, { agent: httpsAgent });
            const qData = await qRes.json();
            if (!qRes.ok) throw new Error(`Jupiter quote failed: ${JSON.stringify(qData)}`);

            const sRes = await fetch(swapUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    quoteResponse: qData,
                    userPublicKey: wallet.publicKey.toString(),
                    wrapAndUnwrapSol: true,
                    computeUnitPriceMicroLamports: 2000000 // Increased for priority
                }),
                agent: httpsAgent
            });
            const sData = await sRes.json();
            if (!sRes.ok) throw new Error(`Jupiter swap failed: ${JSON.stringify(sData)}`);

            const tx = VersionedTransaction.deserialize(Buffer.from(sData.swapTransaction, 'base64'));

            // Add compute budget instructions
            const instructions = [
                ComputeBudgetProgram.setComputeUnitLimit({ units: 800_000 }), // Increased compute units
                ComputeBudgetProgram.setComputeUnitPrice({ microLamports: 2000000 }) // Higher priority fee
            ];

            // Check if WSOL ATA exists, create if not
            const wsolATA = await getAssociatedTokenAddress(SOL_MINT, wallet.publicKey);
            try {
                await getAccount(connection, wsolATA);
            } catch (e) {
                instructions.push(createAssociatedTokenAccountInstruction(
                    wallet.publicKey,
                    wsolATA,
                    wallet.publicKey,
                    SOL_MINT
                ));
            }

            // Compile transaction
            const { blockhash } = await connection.getLatestBlockhash();
            const msg = new TransactionMessage({
                payerKey: wallet.publicKey,
                recentBlockhash: blockhash,
                instructions: [...instructions, ...tx.message.instructions]
            }).compileToV0Message();

            const finalTx = new VersionedTransaction(msg);
            finalTx.sign([wallet]);

            const sig = await connection.sendTransaction(finalTx, { skipPreflight: false, maxRetries: 5 });
            await connection.confirmTransaction(sig, 'finalized');
            return sig;
        } catch (e) {
            const errorMsg = e.message || String(e);
            logToLaravel('warn', `Jupiter swap attempt ${attempt} failed: ${errorMsg}`);
            if (errorMsg.includes('0x1788') && attempt < retries) {
                logToLaravel('info', `Retrying with increased slippage: ${currentSlippage + 500} bps`);
                await new Promise(resolve => setTimeout(resolve, 1000 * attempt)); // Delay before retry
                continue;
            }
            throw e;
        }
    }
    throw new Error('All Jupiter swap attempts failed');
}

// ----- Execute Sell -----
async function executeSell(connection, wallet, mint, amountTokens, solanaCallId) {
    try {
        // Validate input
        if (amountTokens <= 0) throw new Error('Token amount must be positive');

        // Get mint info and token balance
        const mintInfo = await getMint(connection, mint);
        const decimals = mintInfo?.decimals ?? 0;
        const tokenAmountBN = new BN(Math.floor(amountTokens * Math.pow(10, decimals)).toString());

        // Check token balance
        const tokenATA = await getAssociatedTokenAddress(mint, wallet.publicKey);
        let tokenBalanceBN = new BN(0);
        try {
            const tokenAccount = await getAccount(connection, tokenATA);
            tokenBalanceBN = new BN(tokenAccount.amount.toString());
            if (tokenBalanceBN.lt(tokenAmountBN)) {
                throw new Error(`Insufficient token balance: ${tokenBalanceBN.toString()} < ${tokenAmountBN.toString()}`);
            }
        } catch (e) {
            throw new Error(`Failed to fetch token balance: ${e.message || e}`);
        }

        // Execute swap
        const txSig = await jupiterSwap(connection, wallet, mint, SOL_MINT, tokenAmountBN, 1000, 3); // Start with 10% slippage, 3 retries

        // Fetch transaction details
        const tx = await connection.getTransaction(txSig, { commitment: 'finalized', maxSupportedTransactionVersion: 0 });
        if (!tx) throw new Error('Transaction not found: ' + txSig);

        // Compute SOL received
        let amountSolReceived = 0;
        const preSol = tx.meta.preTokenBalances?.find(b => b.mint === SOL_MINT.toBase58() && b.owner === wallet.publicKey.toBase58());
        const postSol = tx.meta.postTokenBalances?.find(b => b.mint === SOL_MINT.toBase58() && b.owner === wallet.publicKey.toBase58());
        if (preSol && postSol) {
            amountSolReceived = parseFloat(postSol.uiTokenAmount.uiAmountString || "0") - parseFloat(preSol.uiTokenAmount.uiAmountString || "0");
        } else {
            // Fallback: lamport difference
            const acctIndex = tx.transaction.message.accountKeys.findIndex(k => k.toBase58() === wallet.publicKey.toBase58());
            amountSolReceived = (tx.meta.postBalances[acctIndex] - tx.meta.preBalances[acctIndex]) / 1e9;
        }

        // Record success
        await createSolanaCallOrder({
            solana_call_id: solanaCallId,
            type: 'sell',
            amount_foreign: bnToNumberSafe(tokenAmountBN),
            amount_sol: amountSolReceived,
            dex_used: 'jupiter',
            tx_signature: txSig
        });

        logToLaravel('info', `Sell complete: ${amountTokens} tokens => ${amountSolReceived} SOL, tx ${txSig}`);
        return { txSig, amountSolReceived };

    } catch (e) {
        const errorMsg = e.logs?.join('\n') || e.message || String(e);
        logToLaravel('error', `Sell failed: ${errorMsg}`);
        await createSolanaCallOrder({
            solana_call_id: solanaCallId,
            type: 'failed',
            error: errorMsg
        });
        return { txSig: null, amountSolReceived: 0 };
    }
}

// ----- CLI -----
if (import.meta.url === `file://${process.argv[1]}`) {
    (async () => {
        try {
            const args = process.argv.slice(2);
            const tokenAddress = args.find(a => a.startsWith('--token='))?.split('=')[1];
            const solanaCallId = args.find(a => a.startsWith('--identifier='))?.split('=')[1];
            const amountArg = args.find(a => a.startsWith('--amount='))?.split('=')[1];
            const amount = parseFloat(amountArg || "0");

            if (!tokenAddress || !solanaCallId || !amount || amount <= 0) {
                console.error('Usage: node solana-auto-sell.js --identifier=ID --token=ADDRESS --amount=NUMBER');
                logToLaravel('error', 'Invalid arguments provided');
                process.exit(1);
            }

            const keyString = process.env.SOLANA_PRIVATE_KEY?.trim();
            if (!keyString) {
                logToLaravel('error', 'Missing SOLANA_PRIVATE_KEY');
                process.exit(1);
            }

            let secretKey;
            try {
                secretKey = keyString.length > 50 ? bs58.decode(keyString) : Uint8Array.from(JSON.parse(keyString));
            } catch (e) {
                logToLaravel('error', `Invalid SOLANA_PRIVATE_KEY format: ${e.message}`);
                process.exit(1);
            }

            const wallet = Keypair.fromSecretKey(secretKey);
            const connection = new Connection(process.env.SOLANA_RPC_URL || 'https://api.mainnet-beta.solana.com', 'confirmed');
            const mint = new PublicKey(tokenAddress);

            const result = await executeSell(connection, wallet, mint, amount, solanaCallId);
            if (!result.txSig) process.exit(1);
            process.exit(0);
        } catch (e) {
            logToLaravel('error', `Script failed: ${e.message || e}`);
            process.exit(1);
        }
    })();
}

export { executeSell };
