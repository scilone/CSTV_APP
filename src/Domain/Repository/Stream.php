<?php

namespace App\Domain\Repository;

use App\Config\Param;
use App\Infrastructure\CacheItem;
use App\Infrastructure\SqlConnection;
use DateTimeImmutable;

class Stream
{
    private const TABLE_NAME = 'app_streams';

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
        string $type,
        DateTimeImmutable $releaseDate,
        int $durationSecs,
        string $lang,
        string $resume,
        string $youtubeTrailer,
        DateTimeImmutable $added,
        int $categoryId,
        string $img,
        float $rating,
        int $videoWidth,
        int $videoHeight,
        string $extension
    ): int {
        $releaseDateFormatted = $releaseDate->format('Y-m-d');
        $addedFormatted = $added->getTimestamp();
        $resume = $this->connection->get()->real_escape_string($resume);
        $name = $this->connection->get()->real_escape_string($name);

        $this->connection->get()->query(
            "INSERT INTO " . self::TABLE_NAME . " (`id`, `name`, `type`, `releasedate`, `duration_secs`, `lang`, `resume`, `youtube_trailer`, `added`, `category_id`, `img`, `rating`, `video_width`, `video_height`, `extension`)
            VALUES ($id, '$name', '$type', '$releaseDateFormatted', '$durationSecs', '$lang', '$resume', '$youtubeTrailer', '$addedFormatted', '$categoryId', '$img', '$rating', '$videoWidth', '$videoHeight', '$extension');"
        );

        if (!empty($this->connection->get()->error)) {
            var_dump($this->connection->get()->error);
            var_dump("INSERT INTO " . self::TABLE_NAME . " (`id`, `name`, `type`, `releasedate`, `duration_secs`, `lang`, `resume`, `youtube_trailer`, `added`, `category_id`, `img`, `rating`, `video_width`, `video_height`, `extension`)
            VALUES ($id, '$name', '$type', '$releaseDateFormatted', '$durationSecs', '$lang', '$resume', '$youtubeTrailer', '$addedFormatted', '$categoryId', '$img', '$rating', '$videoWidth', '$videoHeight', '$extension');");
            exit;
        }

        return $this->connection->get()->insert_id;
    }

    public function getFromIdAndType(int $id, string $type): array
    {
        $cacheKey   = "repo_stream";
        $cachedData = $this->cacheItem->get($cacheKey);

        if ($cachedData !== null && isset($cachedData["$id-$type"])) {
            return $cachedData["$id-$type"];
        }

        $result =  $this->connection->get()->query(
            'SELECT * FROM `' . self::TABLE_NAME . '` WHERE `id` = ' . $id . ' AND type = "' . $type . '"'
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
        $cachedData["$id-$type"] = $result;

        $this->cacheItem->set($cacheKey, $cachedData);

        return $result;
    }

    public function addGenre(int $streamId, int $genreId)
    {
        $this->connection->get()->query(
            "INSERT INTO " . self::TABLE_NAME . "_genre (`stream_id`, `genre_id`) VALUES ($streamId, $genreId);"
        );
    }

    public function addPeople(int $streamId, int $peopleId)
    {
        $this->connection->get()->query(
            "INSERT INTO " . self::TABLE_NAME . "_people (`stream_id`, `people_id`) VALUES ($streamId, $peopleId);"
        );
    }

    public function getFromType(
        string $type,
        ?int $categoryId = null,
        ?int $sorted = 6,
        ?array $streams = null,
        ?string $search = null,
        ?array $advancedSearch = null,
        ?int $offset = 0
    ): array {
        $catFilter = '';
        if ($categoryId !== null) {
            $catFilter = " AND streams.category_id = $categoryId";

        }

        $order = ' ORDER BY ';
        switch ($sorted) {
            case 2:
                $order .= ' streams.name DESC';
                break;
            case 3:
                $order .= ' streams.rating ASC';
                break;
            case 4:
                $order .= ' streams.rating DESC';
                break;
            case 5:
                $order .= ' streams.added ASC';
                break;
            case 6:
                $order .= ' streams.added DESC';
                break;
            default:
                $order .= ' streams.name ASC';
        }

        $streamsFilter = '';
        if (!empty($streams)) {
            $streamsFilter = ' AND streams.id in (' . implode(',', $streams) . ')';
        }

        $searchFilter = '';
        $joinTables   = '';

        if (!empty($advancedSearch)) {
            $joinTables = ' JOIN app_streams_people sp ON sp.stream_id = streams.id JOIN app_people AS people ON people.id = sp.people_id JOIN app_streams_genre AS sg ON sg.stream_id = streams.id JOIN app_genre AS genre ON genre.id = sg.genre_id';

            if (!empty($advancedSearch['title'])) {
                $searchFilter .= " AND streams.name LIKE '%{$advancedSearch['title']}%'";
            }
            if (!empty($advancedSearch['director'])) {
                $searchFilter .= " AND (people.name LIKE '%{$advancedSearch['director']}%' AND role='director')";
            }
            if (!empty($advancedSearch['actor'])) {
                $searchFilter .= " AND (people.name LIKE '%{$advancedSearch['actor']}%' AND role='actor')";
            }
            if (!empty($advancedSearch['genre'])) {
                $searchFilter .= " AND genre.name LIKE '%{$advancedSearch['genre']}%'";
            }
            if (!empty($advancedSearch['releasedate'])) {
                $searchFilter .= " AND YEAR(streams.releasedate) {$advancedSearch['releasedateType']} '{$advancedSearch['releasedate']}'";
            }
            if (!empty($advancedSearch['rate'])) {
                $searchFilter .= " AND streams.rating >= {$advancedSearch['rate']}";
            }
        } elseif (!empty($search)) {
            $joinTables = ' JOIN app_streams_people sp ON sp.stream_id = streams.id JOIN app_people AS people ON people.id = sp.people_id';
            $searchFilter = " AND (streams.name LIKE '%$search%' OR people.name LIKE '%$search%')";
        }

        $result =  $this->connection->get()->query(
            'SELECT streams.* FROM ' . self::TABLE_NAME . ' AS streams ' . $joinTables .
            ' WHERE streams.type = "' . $type . '" ' .
            $streamsFilter .
            $searchFilter . $catFilter .
            ' GROUP BY streams.id' .
            $order .
            " LIMIT " . Param::MAX_CONTENT_PER_PAGE . " OFFSET $offset"
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

    public function countByCategoryAndType(string $type)
    {
        $result =  $this->connection->get()->query(
            'SELECT COUNT(*) AS nb, category_id FROM `' . self::TABLE_NAME . '` WHERE type = "' . $type . '" GROUP BY category_id'
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

    public function getAlternatesByType(string $type, int $id): array
    {
        $result =  $this->connection->get()->query(
            "SELECT  s.name, s.releasedate, s.extension, s.id AS streamId, p.id, REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(s.name, 'BR|', ''), '3D|', ''), 'VO|', ''), '4K|', ''), 'STAR|', ''), 'STEN|', ''), 'IT|', ''), 'PT|', ''), 'TR|', ''), 'VOSTFR|', ''), 'ES|', ''), 'ST|', ''), 'STFR|', ''), 'AR|', ''), 'DE|', ''), 'â™€|', ''), 'EN|', ''), 'FR|', ''), 'NL|', ''), '4K-DV|', ''), '|Â', ''), '| ', ''), '|', '') AS nameFormat  
FROM app_streams s
JOIN app_streams_people sp ON sp.stream_id = s.id
JOIN app_people p ON p.id = sp.people_id
WHERE releasedate IN (
 	SELECT releasedate  
    FROM app_streams
	WHERE id = $id
)
AND s.type = \"$type\"
GROUP BY s.id
HAVING (
    p.id IN (
        SELECT sp.people_id  
        FROM app_streams s
        JOIN app_streams_people sp ON sp.stream_id = s.id
        JOIN app_people p ON p.id = sp.people_id AND role = 'director'
        WHERE s.id = $id
        AND p.name != ''
	) 
    OR
    nameFormat = (
    	SELECT REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name, 'BR|', ''), '3D|', ''), 'VO|', ''), '4K|', ''), 'STAR|', ''), 'STEN|', ''), 'IT|', ''), 'PT|', ''), 'TR|', ''), 'VOSTFR|', ''), 'ES|', ''), 'ST|', ''), 'STFR|', ''), 'AR|', ''), 'DE|', ''), 'â™€|', ''), 'EN|', ''), 'FR|', ''), 'NL|', ''), '4K-DV|', ''), '|Â', ''), '| ', ''), '|', '') AS nameFormat
        FROM app_streams
        WHERE id = $id
        AND name != ''
    )
	OR
	p.id IN (
        SELECT sp.people_id  
        FROM app_streams s
        JOIN app_streams_people sp ON sp.stream_id = s.id
        JOIN app_people p ON p.id = sp.people_id AND role = 'actor'
        WHERE s.id = $id
        AND p.name != ''
	) 
)"
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
