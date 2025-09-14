import { Connection, Keypair, PublicKey, VersionedTransaction, TransactionMessage, ComputeBudgetProgram } from '@solana/web3.js';
import { getAssociatedTokenAddress, getAccount, TOKEN_PROGRAM_ID, ASSOCIATED_TOKEN_PROGRAM_ID } from '@solana/spl-token';
import { BN } from 'bn.js';
import fetch from 'node-fetch';
import https from 'node:https';
import fs from 'fs';
import bs58 from 'bs58';
import dotenv from 'dotenv';
dotenv.config();

const httpsAgent = new https.Agent({ rejectUnauthorized: false });
const LARAVEL_LOG_PATH = './storage/logs/laravel.log';
const PUMP_FUN_PROGRAM = new PublicKey('6EF8rrecthR5Dkzon8Nwu78hRvfCKubJ14M5uBEwF6P');
const SOL_MINT = new PublicKey('So11111111111111111111111111111111111111112');

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

async function createSolanaCallOrder({ solana_call_id, type, tx_signature = null, dex_used = null, amount_sol = null, amount_foreign = null, error = null }) {
    try {
        await fetch(`${process.env.APP_URL}/api/solana-call-orders`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ solana_call_id, type, tx_signature, dex_used, amount_sol, amount_foreign, error })
        });
    } catch (e) {
        logToLaravel('error', 'Failed to create SolanaCallOrder record: ' + (e.message || e));
    }
}

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

function deriveBondingCurve(mint) { return PublicKey.findProgramAddressSync([Buffer.from('bonding-curve'), mint.toBuffer()], PUMP_FUN_PROGRAM)[0]; }
function deriveAssociatedBondingCurve(bondingCurve, mint) { return PublicKey.findProgramAddressSync([bondingCurve.toBuffer(), TOKEN_PROGRAM_ID.toBuffer(), mint.toBuffer()], ASSOCIATED_TOKEN_PROGRAM_ID)[0]; }

async function jupiterSwap(connection, wallet, inputMint, outputMint, amount, slippageBps = 500) {
    const quoteUrl = 'https://quote-api.jup.ag/v6/quote';
    const swapUrl = 'https://quote-api.jup.ag/v6/swap';
    const amountStr = BN.isBN(amount) ? amount.toString() : String(amount);
    const qParams = new URLSearchParams({ inputMint: inputMint.toString(), outputMint: outputMint.toString(), amount: amountStr, slippageBps: String(slippageBps) });
    const qRes = await fetch(`${quoteUrl}?${qParams}`, { agent: httpsAgent });
    const qData = await qRes.json();
    if (!qRes.ok) throw new Error('Jupiter quote failed: ' + JSON.stringify(qData));
    const sRes = await fetch(swapUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ quoteResponse: qData, userPublicKey: wallet.publicKey.toString(), wrapAndUnwrapSol: true, computeUnitPriceMicroLamports: 1000000 }),
        agent: httpsAgent
    });
    const sData = await sRes.json();
    if (!sRes.ok) throw new Error('Jupiter swap failed: ' + JSON.stringify(sData));

    const tx = VersionedTransaction.deserialize(Buffer.from(sData.swapTransaction, 'base64'));
    tx.sign([wallet]);
    const sig = await connection.sendTransaction(tx, { skipPreflight: false, maxRetries: 3 });
    await connection.confirmTransaction(sig, 'finalized');
    return { signature: sig, outAmount: qData.outAmount / 1e9 }; // Return outAmount in SOL (adjusted for 9 decimals)
}

async function executeSell(connection, wallet, mint, tokenAmountBN, solanaCallId) {
    try {
        const bc = deriveBondingCurve(mint);
        const abc = deriveAssociatedBondingCurve(bc, mint);
        const userATA = await getAssociatedTokenAddress(mint, wallet.publicKey);

        let txSig, dexUsed, amountSolReceived;

        // Check bonding curve state (placeholder)
        const state = null;

        if (!state || state.complete) {
            dexUsed = 'jupiter';
            const { signature, outAmount } = await jupiterSwap(connection, wallet, mint, SOL_MINT, tokenAmountBN);
            txSig = signature;
            amountSolReceived = outAmount; // Use the outAmount from Jupiter quote response

            // Attempt to fetch transaction details for verification
            try {
                const txDetails = await connection.getParsedTransaction(txSig, {
                    commitment: 'finalized',
                    maxSupportedTransactionVersion: 0 // Support legacy transactions
                });
                if (txDetails) {
                    const postBalances = txDetails.meta.postTokenBalances || [];
                    const preBalances = txDetails.meta.preTokenBalances || [];
                    for (const balance of postBalances) {
                        if (balance.mint === SOL_MINT.toString() && balance.owner === wallet.publicKey.toString()) {
                            const preBalance = preBalances.find(
                                (b) => b.mint === SOL_MINT.toString() && b.owner === wallet.publicKey.toString()
                            );
                            if (preBalance) {
                                const txAmountSol = (balance.uiTokenAmount.uiAmount - preBalance.uiTokenAmount.uiAmount) || 0;
                                if (txAmountSol > 0) {
                                    amountSolReceived = txAmountSol; // Override with transaction amount if valid
                                }
                            }
                            break;
                        }
                    }
                }
            } catch (txError) {
                logToLaravel('warning', `Failed to fetch transaction details for verification: ${txError.message}`);
                // Continue with outAmount from Jupiter response
            }
        } else {
            // Bonding curve sell logic (TODO: implement if needed)
            throw new Error('Bonding curve sell not implemented');
        }

        if (amountSolReceived <= 0) {
            throw new Error('Could not determine SOL received from transaction');
        }

        await createSolanaCallOrder({
            solana_call_id: solanaCallId,
            type: 'sell',
            amount_foreign: bnToNumberSafe(tokenAmountBN),
            amount_sol: amountSolReceived,
            dex_used: dexUsed,
            tx_signature: txSig
        });

        logToLaravel('info', `Sell complete: ${bnToNumberSafe(tokenAmountBN)} tokens, received ${amountSolReceived} SOL via ${dexUsed}, tx ${txSig}`);
        return { txSig, dexUsed };

    } catch (e) {
        const errorMsg = e.logs ? e.logs.join('\n') : e.message || String(e);
        logToLaravel('error', 'Sell failed: ' + errorMsg);
        await createSolanaCallOrder({ solana_call_id: solanaCallId, type: 'failed', error: errorMsg });
        return { txSig: null, dexUsed: null };
    }
}

if (import.meta.url === `file://${process.argv[1]}`) {
    (async () => {
        const args = process.argv.slice(2);
        const tokenAddress = args.find(a => a.startsWith('--token='))?.split('=')[1];
        const solanaCallId = args.find(a => a.startsWith('--identifier='))?.split('=')[1];
        const amount = args.find(a => a.startsWith('--amount='))?.split('=')[1];

        if (!tokenAddress || !solanaCallId || !amount) {
            console.error('Usage: node solana-auto-sell.js --identifier=ID --token=ADDRESS --amount=NUMBER');
            process.exit(1);
        }

        const keyString = process.env.SOLANA_PRIVATE_KEY.trim();
        const secretKey = keyString.length > 50 ? bs58.decode(keyString) : Uint8Array.from(JSON.parse(keyString));
        const wallet = Keypair.fromSecretKey(secretKey);
        const connection = new Connection(process.env.SOLANA_RPC_URL || 'https://api.mainnet-beta.solana.com', 'confirmed');
        const mint = new PublicKey(tokenAddress);
        const tokenAmountBN = toBN(amount);

        await executeSell(connection, wallet, mint, tokenAmountBN, solanaCallId);
    })();
}

export { executeSell };
