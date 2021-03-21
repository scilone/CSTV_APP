<?php

namespace App\Domain\Repository;

use App\Infrastructure\CacheItem;
use App\Infrastructure\SqlConnection;

class Genre
{
    private const TABLE_NAME = 'app_genre';

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

    public function create(string $name): int {
        $name = $this->connection->get()->real_escape_string($name);

        $this->connection->get()->query(
            "INSERT INTO " . self::TABLE_NAME . " (`name`) VALUES ('$name');"
        );

        if (!empty($this->connection->get()->error)) {
            var_dump($this->connection->get()->error);
            var_dump("INSERT INTO " . self::TABLE_NAME . " (`name`) VALUES ('$name');");
            exit;
        }

        return $this->connection->get()->insert_id;
    }

    public function getFromName(string $name): array
    {
        $cacheKey   = "repo_genre";
        $cachedData = $this->cacheItem->get($cacheKey);

        if ($cachedData !== null && isset($cachedData["$name"])) {
            return $cachedData["$name"];
        }

        $result =  $this->connection->get()->query(
            'SELECT * FROM `' . self::TABLE_NAME . '` WHERE `name` = "' . $name . '"'
        );

        if ($result === false) {
            return [];
        }

        $result = $result->fetch_assoc();

        if ($result === null) {
            return [];
        }

        if ($cachedData === null) {
            $cachedData = [];
        }
        $cachedData["$name"] = $result;

        $this->cacheItem->set($cacheKey, $result);

        return $result;
    }
}
