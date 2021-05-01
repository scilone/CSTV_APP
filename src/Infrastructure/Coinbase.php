<?php


namespace App\Infrastructure;


use Coinbase\Wallet\Client;
use Coinbase\Wallet\Configuration;

class Coinbase
{
    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var Client
     */
    private $client;

    public function __construct(string $apiKey, string $apiSecret)
    {
        $this->configuration = Configuration::apiKey($apiKey, $apiSecret);
        $this->client = Client::create($this->configuration);
    }

    public function getAssetsStats(): array
    {
        $assets = $this->client->getAssetsPrice();

        $list = [];
        foreach ($assets as $asset) {
            $stats = $this->client->getAssetsStats($asset['base_id']);

            $list[] = [
                'base'                 => $asset['base'],
                'latest_price'         => $asset['prices']['latest'] ?? 0,
                'percent_holding'      => ($stats['signals']['percent_holding']['value'] ?? 0) *100,
                'trading_activity'     => ($stats['signals']['trading_activity']['value'] ?? 0) *100,
                'percent_change_hour'  => ($asset['prices']['latest_price']['percent_change']['hour'] ?? 0) *100,
                'percent_change_day'   => ($asset['prices']['latest_price']['percent_change']['day'] ?? 0) *100,
                'percent_change_week'  => ($asset['prices']['latest_price']['percent_change']['week'] ?? 0) *100,
                'percent_change_month' => ($asset['prices']['latest_price']['percent_change']['month'] ?? 0) *100,
                'percent_change_year'  => ($asset['prices']['latest_price']['percent_change']['year'] ?? 0) *100,
            ];
        }

        return $list;
    }
}
