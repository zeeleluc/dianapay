<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewResponse;
use Illuminate\Http\RedirectResponse;

class ArticleController extends Controller
{
    public function show(string $slug1, ?string $slug2 = null, ?string $slug3 = null): ViewResponse|RedirectResponse
    {
        $slugs = array_filter([$slug1, $slug2, $slug3], fn($v) => !is_null($v));
        $slugPath = implode('/', $slugs);

        $contentView = "articles.content.$slugPath";
        $sidebarView = "articles.sidebar.$slugPath";

        $hasContent = View::exists($contentView);
        $hasSidebar = View::exists($sidebarView);

        if (! $hasContent && ! $hasSidebar) {
            return redirect()->route('home');
        }

        $title = $this->getSlugLabel(end($slugs));

        $viewData = [
            'slug1' => $slug1,
            'slug2' => $slug2,
            'slug3' => $slug3,
            'slugs' => $slugs,
            'slug' => $slugPath,
            'title' => $title,
            'breadcrumbs' => $this->resolveBreadcrumbs($slugs),
        ];

        return view('articles.article', [
            ...$viewData,
            'content' => $hasContent ? view($contentView, $viewData)->render() : '',
            'sidebar' => $hasSidebar ? view($sidebarView, $viewData)->render() : '',
        ]);
    }

    /**
     * @param array $slugParts Parts of the slug, e.g. ['foo', 'bar', 'baz']
     * @return array Breadcrumbs as ['Label' => 'URL or null']
     */
    private function resolveBreadcrumbs(array $slugs): array
    {
        $breadcrumbs = [
            [
                'label' => translate('Main Page'),
                'url' => route('home'),
            ],
        ];

        $lastIndex = count($slugs) - 1;
        $accumulatedSlugs = [];

        foreach ($slugs as $index => $slug) {
            $accumulatedSlugs[] = $slug;

            $params = [];
            for ($i = 0; $i < 3; $i++) {
                $params['slug' . ($i + 1)] = $accumulatedSlugs[$i] ?? null;
            }

            $breadcrumbs[] = [
                'label' => $this->getSlugLabel($slug),
                'url' => $index === $lastIndex ? null : route('articles.show', array_filter($params)),
            ];
        }

        return $breadcrumbs;
    }

    private function getSlugLabel(string $slug): string
    {
        $tokenMap = [
            'eth' => 'Ether (ETH)',
            'usdc' => 'USD Coin (USDC)',
            'usdt' => 'Tether (USDT)',
            'dai' => 'Dai (DAI)',
            'brett' => 'Brett (BRETT)',
            'toshi' => 'Toshi (TOSHI)',
            'link' => 'Chainlink (LINK)',
            'degen' => 'Degen (DEGEN)',
            'miggles' => 'Mister Miggles (MIGGLES)',
            'base' => 'Base',
        ];

        $blockchains = [
            'ethereum' => 'Ethereum Mainnet',
            'polygon' => 'Polygon (Matic)',
            'arbitrum' => 'Arbitrum One',
            'optimism' => 'Optimism',
            'bsc' => 'Binance Smart Chain',
            'avalanche' => 'Avalanche C-Chain',
            'fantom' => 'Fantom Opera',
            'base' => 'Coinbase Base',
            'linea' => 'Linea zkEVM',
            'bitcoin' => 'Bitcoin',
            'xrpl' => 'XRPL',
            'solana' => 'Solana',
            'cardano' => 'Cardano',
            'algorand' => 'Algorand',
            'stellar' => 'Stellar Lumens',
            'tezos' => 'Tezos',
        ];

        $slugLower = strtolower($slug);

        if (isset($tokenMap[$slugLower])) {
            return $tokenMap[$slugLower];
        }

        if (isset($blockchains[$slugLower])) {
            return $blockchains[$slugLower];
        }

        // fallback: humanize slug
        return ucfirst(str_replace('-', ' ', $slug));
    }

}
