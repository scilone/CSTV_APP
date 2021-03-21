<?php

namespace App\Domain\Repository;

use App\Infrastructure\CacheItem;
use App\Infrastructure\SqlConnection;

class People
{
    private const TABLE_NAME = 'app_people';

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

    public function create(string $name, string $role): int {
        $name = $this->connection->get()->real_escape_string($name);

        $this->connection->get()->query(
            "INSERT INTO " . self::TABLE_NAME . " (`name`, role) VALUES ('$name', '$role');"
        );

        if (!empty($this->connection->get()->error)) {
            var_dump($this->connection->get()->error);
            var_dump("INSERT INTO " . self::TABLE_NAME . " (`name`, role) VALUES ('$name', '$role');");
            exit;
        }

        return $this->connection->get()->insert_id;
    }

    public function getFromNameAndRole(string $name, string $role): array
    {
        $cacheKey   = "repo_people_$name-$role";
        $cachedData = $this->cacheItem->get($cacheKey);

        if ($cachedData !== null && isset($cachedData["$name-$role"])) {
            return $cachedData["$name-$role"];
        }

        $result =  $this->connection->get()->query(
            'SELECT * FROM `' . self::TABLE_NAME . '` WHERE `name` = "' . $name . '" AND role = "' . $role . '"'
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
        $cachedData["$name-$role"] = $result;

        $this->cacheItem->set($cacheKey, $result);

        return $result;
    }
}
