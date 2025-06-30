<?php

return [
    'coingecko' => [
        'base' => [
            'currencies' => [
                'crypto_ids' => ['usd-coin', 'ethereum', 'tether', 'dai', 'brett', 'toshi', 'cardano', 'litecoin'], // CoinGecko IDs
                'fiat' => ['usd', 'eur', 'jpy', 'gbp', 'cny', 'cad', 'aud', 'chf', 'xcg'], // Major fiat + XCG
            ],
            'crypto_map' => [
                'usd-coin' => 'USDC',
                'ethereum' => 'ETH',
                'tether' => 'USDT',
                'dai' => 'DAI',
                'brett' => 'BRETT',
                'toshi' => 'TOSHI',
                'cardano' => 'cbADA', // Wrapped Cardano on Base
                'litecoin' => 'cbLTC', // Wrapped Litecoin on Base
            ],
        ],
    ],
];
