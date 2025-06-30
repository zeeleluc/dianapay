<?php

return [
    'coingecko' => [
        'base' => [
            'currencies' => [
                'crypto_ids' => [
                    'usd-coin',
                    'ethereum',
                    'tether',
                    'dai',
                    'brett',
                    'toshi',
                    'cardano',
                    'litecoin',

                    // New Base L2 notable tokens
                    'axelar',
                    'balancer',
                    'yearn-finance',
                    'cyberconnect',
                    'optimism',
                    'chainlink',
                    'ski-token',
                    'pepe',
                    'degen',
                    'miggless',
                    'keycat',
                ],
                'fiat' => [
                    'usd', 'eur', 'jpy', 'gbp', 'cny', 'cad', 'aud', 'chf', 'xcg',
                ],
            ],
            'crypto_map' => [
                'usd-coin'       => 'USDC',
                'ethereum'       => 'ETH',
                'tether'         => 'USDT',
                'dai'            => 'DAI',
                'brett'          => 'BRETT',
                'toshi'          => 'TOSHI',
                'cardano'        => 'cbADA',
                'litecoin'       => 'cbLTC',

                // New Base L2 mappings
                'axelar'         => 'AXL',
                'balancer'       => 'BAL',
                'yearn-finance'  => 'YFI',
                'cyberconnect'   => 'CYBER',
                'optimism'       => 'OP',
                'chainlink'      => 'LINK',
                'ski-token'      => 'SKI',
                'pepe'           => 'PEPE',
                'degen'          => 'DEGEN',
                'miggless'       => 'MIGGLES',
                'keycat'         => 'KEYCAT',
            ],
        ],
    ],
];
