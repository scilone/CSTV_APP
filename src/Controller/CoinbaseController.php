<?php

namespace App\Controller;

use App\Application\Twig;
use App\Domain\Repository\CoinbaseHisto as CoinbaseRepository;
use App\Infrastructure\Coinbase;
use App\Infrastructure\SuperglobalesOO;

class CoinbaseController
{
    /**
     * @var Twig
     */
    private $twig;

    /**
     * @var SuperglobalesOO
     */
    private $superglobales;

    /**
     * @var Coinbase
     */
    private $coinbase;

    /**
     * @var CoinbaseRepository
     */
    private $coinbaseRepository;

    public function __construct(
        Twig $twig,
        SuperglobalesOO $superglobales,
        Coinbase $coinbase,
        CoinbaseRepository $coinbaseRepository
    ) {
        $this->twig               = $twig;
        $this->superglobales      = $superglobales;
        $this->coinbase           = $coinbase;
        $this->coinbaseRepository = $coinbaseRepository;
    }

    public function addAssetsStats()
    {
        var_dump('START');

        $assetsStats = $this->coinbase->getAssetsStats();

        foreach ($assetsStats as $assetsStat) {
            $this->coinbaseRepository->add(
                date('Y-m-d H:i:s'),
                $assetsStat['base'],
                $assetsStat['latest_price'] ?? null,
                $assetsStat['percent_holding'] ?? null,
                $assetsStat['trading_activity'] ?? null,
                $assetsStat['percent_change_hour'] ?? null,
                $assetsStat['percent_change_day'] ?? null,
                $assetsStat['percent_change_week'] ?? null,
                $assetsStat['percent_change_month'] ?? null,
                $assetsStat['percent_change_year'] ?? null
            );
        }

        var_dump('END');
    }
}
