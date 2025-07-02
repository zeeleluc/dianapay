<?php

return [
    // EVM-compatible chains
    'ethereum' => [
        'short_name' => 'Ethereum',
        'long_name' => 'Ethereum Mainnet',
        'logo' => null,
        'color_primary' => '#627eea', // Iconic blue-purple
        'color_secondary' => '#b3c2ff', // Softer, lighter blue-purple
        'active' => false,
        'evm' => true,
    ],
    'polygon' => [
        'short_name' => 'Polygon',
        'long_name' => 'Polygon (Matic)',
        'logo' => null,
        'color_primary' => '#8247e5', // Vibrant purple
        'color_secondary' => '#b19cf4', // Softer, lighter purple
        'active' => false,
        'evm' => true,
    ],
    'arbitrum' => [
        'short_name' => 'Arbitrum',
        'long_name' => 'Arbitrum One',
        'logo' => null,
        'color_primary' => '#28a0f0', // Bright blue
        'color_secondary' => '#7cc1f7', // Softer, lighter blue
        'active' => false,
        'evm' => true,
    ],
    'optimism' => [
        'short_name' => 'Optimism',
        'long_name' => 'Optimism',
        'logo' => null,
        'color_primary' => '#ff0420', // Bold red
        'color_secondary' => '#ff6b7e', // Softer, lighter red
        'active' => false,
        'evm' => true,
    ],
    'bsc' => [
        'short_name' => 'BNB Chain',
        'long_name' => 'Binance Smart Chain',
        'logo' => null,
        'color_primary' => '#f3ba2f', // Vibrant yellow
        'color_secondary' => '#f8d381', // Softer, lighter yellow
        'active' => false,
        'evm' => true,
    ],
    'avalanche' => [
        'short_name' => 'Avalanche',
        'long_name' => 'Avalanche C-Chain',
        'logo' => null,
        'color_primary' => '#e84142', // Bold red
        'color_secondary' => '#f28b8c', // Softer, lighter red
        'active' => false,
        'evm' => true,
    ],
    'fantom' => [
        'short_name' => 'Fantom',
        'long_name' => 'Fantom Opera',
        'logo' => null,
        'color_primary' => '#1969ff', // Bright blue
        'color_secondary' => '#74a3ff', // Softer, lighter blue
        'active' => false,
        'evm' => true,
    ],
    'base' => [
        'short_name' => 'Base',
        'long_name' => 'Coinbase Base',
        'logo' => null,
        'color_primary' => '#0052ff', // Vibrant blue
        'color_secondary' => '#80a3ff', // Softer, lighter blue
        'active' => true,
        'evm' => true,
    ],
    'linea' => [
        'short_name' => 'Linea',
        'long_name' => 'Linea zkEVM',
        'logo' => null,
        'color_primary' => '#2f80ed', // Bright blue
        'color_secondary' => '#7fb6f5', // Softer, lighter blue
        'active' => false,
        'evm' => true,
    ],

    // Non-EVM chains
    'bitcoin' => [
        'short_name' => 'Bitcoin',
        'long_name' => 'Bitcoin',
        'logo' => null,
        'color_primary' => '#f7931a', // Iconic orange
        'color_secondary' => '#f8b465', // Softer, lighter orange
        'active' => false,
        'evm' => false,
    ],
    'xrpl' => [
        'short_name' => 'XRPL',
        'long_name' => 'XRPL',
        'logo' => null,
        'color_primary' => '#00A8E0', // Vibrant blue (corrected)
        'color_secondary' => '#66d4ff', // Softer, lighter blue
        'active' => false,
        'evm' => false,
    ],
    'solana' => [
        'short_name' => 'Solana',
        'long_name' => 'Solana',
        'logo' => null,
        'color_primary' => '#9945ff', // Purple as the "real color"
        'color_secondary' => '#c4a1ff', // Softer, lighter purple
        'active' => false,
        'evm' => false,
    ],
    'cardano' => [
        'short_name' => 'Cardano',
        'long_name' => 'Cardano',
        'logo' => null,
        'color_primary' => '#0033ad', // Deep blue
        'color_secondary' => '#6682d9', // Softer, lighter blue
        'active' => false,
        'evm' => false,
    ],
    'algorand' => [
        'short_name' => 'Algorand',
        'long_name' => 'Algorand',
        'logo' => null,
        'color_primary' => '#00C4B4', // Teal (corrected)
        'color_secondary' => '#66e0d6', // Softer, lighter teal
        'active' => false,
        'evm' => false,
    ],
    'stellar' => [
        'short_name' => 'Stellar',
        'long_name' => 'Stellar Lumens',
        'logo' => null,
        'color_primary' => '#08b5e5', // Bright cyan/blue
        'color_secondary' => '#70d6f9', // Softer, lighter cyan
        'active' => false,
        'evm' => false,
    ],
    'tezos' => [
        'short_name' => 'Tezos',
        'long_name' => 'Tezos',
        'logo' => null,
        'color_primary' => '#2c7df7', // Vibrant blue
        'color_secondary' => '#84b3f8', // Softer, lighter blue
        'active' => false,
        'evm' => false,
    ],
];
