<?php

namespace App\Domain\Repository;

use App\Infrastructure\CacheItem;
use App\Infrastructure\SqlConnection;

class Category
{
    private const TABLE_NAME = 'app_category';

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

    public function create(
        int $id,
        string $name,
        string $type
    ): int {
        $name = $this->connection->get()->real_escape_string($name);

        $this->connection->get()->query(
            "INSERT INTO " . self::TABLE_NAME . " (`id`, `name`, `type`) VALUES ($id, '$name', '$type');"
        );

        if (!empty($this->connection->get()->error)) {
            var_dump($this->connection->get()->error);
            var_dump("INSERT INTO " . self::TABLE_NAME . " (`id`, `name`, `type`) VALUES ($id, '$name', '$type');");
            exit;
        }

        return $this->connection->get()->insert_id;
    }

    public function getFromId(int $id): array
    {
        $cacheKey   = "repo_category";
        $cachedData = $this->cacheItem->get($cacheKey);

        if ($cachedData !== null && isset($cachedData["$id"])) {
            return $cachedData["$id"];
        }

        $result =  $this->connection->get()->query(
            'SELECT * FROM `' . self::TABLE_NAME . '` WHERE `id` = ' . $id
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
        $cachedData["$id"] = $result;

        $this->cacheItem->set($cacheKey, $result);

        return $result;
    }

    public function getFromType(string $type): array
    {
        $result =  $this->connection->get()->query(
            'SELECT * FROM `' . self::TABLE_NAME . '` WHERE `type` = "' . $type . '"'
        );

        if ($result === false) {
            return [];
        }

        $result = $result->fetch_all(MYSQLI_ASSOC);

        if ($result === null) {
            return [];
        }

        return $result;
    }
}
