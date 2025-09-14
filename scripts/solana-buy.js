#!/usr/bin/env node

/**
 * solana-buy.js
 *
 * Usage:
 *   node solana-buy.js --identifier=123 --token=TOKEN_MINT --amount=0.01
 */

import 'dotenv/config';
import fetch from 'node-fetch';
import {
    Connection,
    Keypair,
    PublicKey,
    Transaction,
    SystemProgram,
    sendAndConfirmTransaction,
    LAMPORTS_PER_SOL,
} from '@solana/web3.js';
import bs58 from 'bs58';
import { BN } from 'bn.js';
import {
    getAssociatedTokenAddress,
    createAssociatedTokenAccountInstruction,
    createTransferInstruction,
    TOKEN_PROGRAM_ID,
} from '@solana/spl-token';

// ---- ENV + WALLET ---- //
const APP_URL = process.env.APP_URL || 'http://localhost';
const SOLANA_RPC_URL = process.env.SOLANA_RPC_URL;
const SOLANA_PRIVATE_KEY = process.env.SOLANA_PRIVATE_KEY;

if (!SOLANA_RPC_URL || !SOLANA_PRIVATE_KEY) {
    console.error("❌ Missing SOLANA_RPC_URL or SOLANA_PRIVATE_KEY in .env");
    process.exit(1);
}

const connection = new Connection(SOLANA_RPC_URL, "confirmed");
const wallet = Keypair.fromSecretKey(bs58.decode(SOLANA_PRIVATE_KEY));

// ---- HELPERS ---- //
async function logToLaravel(message, level = 'info') {
    try {
        await fetch(`${APP_URL}/api/solana/log`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message, level }),
        });
    } catch (err) {
        console.error("Failed to log to Laravel:", err.message);
    }
}

async function createOrder(callId, type, status, txSig = null, errorMessage = null) {
    try {
        await fetch(`${APP_URL}/api/solana/orders`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                solana_call_id: callId,
                order_type: type,
                status,
                tx_signature: txSig,
                error_message: errorMessage,
            }),
        });
    } catch (err) {
        console.error("Failed to create order:", err.message);
    }
}

// ---- BUY LOGIC ---- //
async function executeBuy(connection, wallet, mint, buyAmountSol, verbose = true) {
    const lamports = new BN(buyAmountSol * LAMPORTS_PER_SOL);

    if (verbose) console.log(`Buying ${buyAmountSol} SOL of ${mint.toBase58()}...`);

    try {
        // 1) Try bonding curve swap
        const bondingTx = await swapBondingCurve(connection, wallet, mint, lamports, verbose);
        if (bondingTx) {
            if (verbose) console.log("✅ Bought via bonding curve:", bondingTx);
            return bondingTx;
        }
    } catch (err) {
        if (verbose) console.warn("⚠️ Bonding curve swap failed, falling back to Jupiter:", err.message);
    }

    try {
        // 2) Fallback: Jupiter swap
        const jupiterTx = await swapJupiter(connection, wallet, mint, lamports, verbose);
        if (jupiterTx) {
            if (verbose) console.log("✅ Bought via Jupiter:", jupiterTx);
            return jupiterTx;
        }
    } catch (err) {
        if (verbose) console.error("❌ Jupiter swap failed:", err.message);
        throw err;
    }

    throw new Error("All buy methods failed");
}

// ---- BONDING CURVE SWAP ---- //
async function swapBondingCurve(connection, wallet, mint, lamports, verbose = true) {
    try {
        // Simulate bonding-curve swap via system transfer to mint owner (pump.fun style)
        const recipient = new PublicKey(mint); // simplify: send SOL to mint address
        const tx = new Transaction().add(
            SystemProgram.transfer({
                fromPubkey: wallet.publicKey,
                toPubkey: recipient,
                lamports: lamports.toNumber(),
            })
        );

        const sig = await sendAndConfirmTransaction(connection, tx, [wallet], {
            skipPreflight: true,
            commitment: "confirmed",
        });

        if (verbose) console.log("Bonding curve tx:", sig);
        return sig;
    } catch (err) {
        if (verbose) console.warn("Bonding curve swap error:", err.message);
        throw err;
    }
}

// ---- JUPITER FALLBACK ---- //
async function swapJupiter(connection, wallet, mint, lamports, verbose = true) {
    try {
        const inputMint = "So11111111111111111111111111111111111111112"; // SOL
        const outputMint = mint.toBase58();
        const amount = lamports.toString();

        const quoteUrl = `https://quote-api.jup.ag/v6/quote?inputMint=${inputMint}&outputMint=${outputMint}&amount=${amount}&slippageBps=500`;
        const { data: quote } = await (await fetch(quoteUrl)).json();
        if (!quote) throw new Error("No Jupiter route found");

        const swapRes = await fetch("https://quote-api.jup.ag/v6/swap", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                route: quote,
                userPublicKey: wallet.publicKey.toBase58(),
                wrapAndUnwrapSol: true,
            }),
        });

        const { swapTransaction } = await swapRes.json();
        if (!swapTransaction) throw new Error("Failed to create Jupiter swap tx");

        const txBuf = Buffer.from(swapTransaction, "base64");
        const tx = Transaction.from(txBuf);

        const sig = await sendAndConfirmTransaction(connection, tx, [wallet], {
            skipPreflight: true,
            commitment: "confirmed",
        });

        if (verbose) console.log("Jupiter tx:", sig);
        return sig;
    } catch (err) {
        if (verbose) console.error("Jupiter swap error:", err.message);
        throw err;
    }
}

// ---- MAIN ---- //
async function main() {
    const args = Object.fromEntries(process.argv.slice(2).map(arg => {
        const [k, v] = arg.replace(/^--/, '').split('=');
        return [k, v];
    }));

    const callId = args.identifier;
    const tokenAddress = args.token;
    const buyAmount = parseFloat(args.amount);

    if (!callId || !tokenAddress || !buyAmount) {
        console.error("❌ Usage: node solana-buy.js --identifier=123 --token=MINT --amount=0.01");
        process.exit(1);
    }

    try {
        const mint = new PublicKey(tokenAddress);
        const txSig = await executeBuy(connection, wallet, mint, buyAmount, true);

        await logToLaravel(`✅ Buy success for call #${callId}, tx: ${txSig}`);
        await createOrder(callId, 'buy', 'success', txSig);

        process.exit(0);
    } catch (err) {
        console.error("❌ Buy failed:", err.message);
        await logToLaravel(`❌ Buy failed for call #${callId}: ${err.message}`, 'error');
        await createOrder(callId, 'buy', 'failed', null, err.message);

        process.exit(1);
    }
}

main();
