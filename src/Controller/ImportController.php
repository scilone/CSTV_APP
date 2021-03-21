<?php

namespace App\Controller;

use App\Application\Account;
use App\Application\Iptv;
use App\Application\Twig;
use App\Config\Param;
use App\Domain\Iptv\DTO\Live;
use App\Domain\Iptv\DTO\MovieInfo;
use App\Domain\Iptv\DTO\SerieInfo;
use App\Domain\Repository\Category;
use App\Domain\Repository\Genre;
use App\Domain\Repository\People;
use App\Domain\Repository\Stream;
use App\Infrastructure\CacheRaw;
use App\Infrastructure\SuperglobalesOO;
use DateTime;
use Exception;

class ImportController
{
    /**
     * @var Iptv
     */
    private $iptv;

    /**
     * @var Stream
     */
    private $stream;

    /**
     * @var People
     */
    private $people;

    /**
     * @var Category
     */
    private $category;

    /**
     * @var Genre
     */
    private $genre;

    public function __construct(
        Iptv $iptv,
        Stream $stream,
        People $people,
        Category $category,
        Genre $genre
    ) {
        $this->iptv     = $iptv;
        $this->stream   = $stream;
        $this->people   = $people;
        $this->category = $category;
        $this->genre    = $genre;
    }

    public function movie()
    {
        $movies     = $this->iptv->getMovieStreamsFromApi([]);
        $categories = $this->iptv->getMovieCategoriesFromApi();

        foreach ($movies as $movie) {
            if ($this->stream->getFromIdAndType($movie->getStreamId(), 'movie') !== []) {
                continue;
            }

            try {
                $movieInfo = $this->iptv->getMovieInfo($movie->getStreamId());
            } catch (Exception $e) {
                continue;
            }

            if ($movieInfo instanceof MovieInfo === false || $movieInfo->getStreamId() === 0) {
                continue;
            }

            $this->stream->create(
                $movieInfo->getStreamId(),
                $movieInfo->getName(),
                'movie',
                $movieInfo->getReleaseDate(),
                $movieInfo->getDurationSecondes(),
                '',
                $movieInfo->getPlot(),
                $movieInfo->getYoutubeTrailer(),
                $movieInfo->getAdded(),
                $movieInfo->getCategoryId(),
                $movieInfo->getImage(),
                $movieInfo->getRating(),
                $movieInfo->getVideo()->getWidth(),
                $movieInfo->getVideo()->getHeight(),
                $movieInfo->getContainerExtension()
            );

            var_dump($movieInfo->getStreamId(), $movieInfo->getName());

            if ($this->addCategory($movie->getCategoryId(), $categories, 'movie') === false) {
                continue;
            }

            $this->addGenre($movieInfo->getGenre(), $movieInfo->getStreamId());
            $this->addPeople($movieInfo->getDirector(), 'director', $movieInfo->getStreamId());
            $this->addPeople($movieInfo->getCast(), 'actor', $movieInfo->getStreamId());
        }
    }

    public function serie()
    {
        $series     = $this->iptv->getSerieStreamsFromApi([]);
        $categories = $this->iptv->getSerieCategoriesFromApi();

        foreach ($series as $serie) {
            if ($this->stream->getFromIdAndType($serie->getSerieId(), 'serie') !== []) {
                continue;
            }

            try {
                $serieInfo = $this->iptv->getSerieInfo($serie->getSerieId());
            } catch (Exception $e) {
                continue;
            }

            if ($serieInfo instanceof SerieInfo === false || $serieInfo->getId() === 0) {
                continue;
            }

            $this->stream->create(
                $serieInfo->getId(),
                $serieInfo->getName(),
                'serie',
                $serieInfo->getReleaseDate(),
                0,
                '',
                $serieInfo->getPlot(),
                $serieInfo->getYoutubeTrailer(),
                $serieInfo->getLastModified(),
                $serieInfo->getCategoryId(),
                $serieInfo->getCover(),
                $serieInfo->getRating(),
                0,
                0,
                ''
            );

            var_dump($serieInfo->getId(), $serieInfo->getName());

            if ($this->addCategory($serie->getCategoryId(), $categories, 'serie') === false) {
                continue;
            }

            $this->addGenre($serieInfo->getGenre(), $serieInfo->getId());
            $this->addPeople($serieInfo->getDirector(), 'director', $serieInfo->getId());
            $this->addPeople($serieInfo->getCast(), 'actor', $serieInfo->getId());
        }
    }

    private function addCategory(int $category, array $categories, string $streamType): bool
    {
        if ($this->category->getFromId($category) === []) {
            if (!isset($categories[$category])) {
                return false;
            }
            $this->category->create($category, $categories[$category]->getName(), $streamType);
        }

        return true;
    }

    private function addGenre(string $genreRaw, int $streamId): void
    {
        $genres = explode($this->getDelimiter($genreRaw),  $genreRaw);

        foreach ($genres as $genre) {
            $genre = trim($genre);

            $genreInfo = $this->genre->getFromName($genre);

            if ($genreInfo === []) {
                $this->stream->addGenre($streamId, $this->genre->create($genre));
            } else {
                $this->stream->addGenre($streamId, $genreInfo['id']);
            }
        }
    }

    private function addPeople(string $people, string $role, int $streamId): void
    {
        $peopleExploded = explode($this->getDelimiter($people), $people);
        foreach ($peopleExploded as $persona) {
            $persona = trim($persona);

            $personaInfo = $this->people->getFromNameAndRole($persona, $role);
            if ($personaInfo === []) {
                $this->stream->addPeople($streamId, $this->people->create($persona, $role));
            } else {
                $this->stream->addPeople($streamId, $personaInfo['id']);
            }
        }
    }

    private function getDelimiter(string $text): string
    {
        if (strpos($text, ',') !== false) {
            return ',';
        }

        if (strpos($text, '/') !== false) {
            return '/';
        }

        return ',';
    }
}
