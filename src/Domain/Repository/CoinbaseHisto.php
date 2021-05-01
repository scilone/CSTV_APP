<?php

namespace App\Domain\Repository;

use App\Infrastructure\CacheItem;
use App\Infrastructure\SqlConnection;

class CoinbaseHisto
{
    private const TABLE_NAME = 'coinbase_histo';

    /**
     * @var SqlConnection
     */
    private $connection;

    /**
     * @var CacheItem
     */
    private $cacheItem;

    public function __construct(SqlConnection $connection, CacheItem $cacheItem)
    {
        $this->connection = $connection;
        $this->cacheItem  = $cacheItem;
    }

    public function add(
        string $datetime,
        string $base,
        ?string $price = null,
        ?string $percentHolding = null,
        ?string $tradingActivity = null,
        ?string $percentChangeHour = null,
        ?string $percentChangeDay = null,
        ?string $percentChangeWeek = null,
        ?string $percentChangeMonth = null,
        ?string $percentChangeYear = null
    ): int {
        $this->connection->get()->query(
            "INSERT INTO " . self::TABLE_NAME . " (datetime, base, price, percent_holding, trading_activity, percent_change_hour, percent_change_day, percent_change_week, percent_change_month, percent_change_year) VALUES ('$datetime', '$base', '$price', '$percentHolding', '$tradingActivity', '$percentChangeHour', '$percentChangeDay', '$percentChangeWeek', '$percentChangeMonth', '$percentChangeYear');"
        );

        if (!empty($this->connection->get()->error)) {
            var_dump($this->connection->get()->error);
            var_dump("INSERT INTO " . self::TABLE_NAME . " (datetime, base, price, percent_holding, trading_activity, percent_change_hour, percent_change_day, percent_change_week, percent_change_month, percent_change_year) VALUES ('$datetime', '$base', '$price', '$percentHolding', '$tradingActivity', '$percentChangeHour', '$percentChangeDay', '$percentChangeWeek', '$percentChangeMonth', '$percentChangeYear');");
            exit;
        }

        return $this->connection->get()->insert_id;
    }
}
