<?php

namespace App\Controller;

use App\Application\Account;
use App\Application\Iptv;
use App\Application\Twig;
use App\Config\Param;
use App\Domain\Iptv\DTO\Live;
use App\Domain\Iptv\DTO\Serie;
use App\Domain\Iptv\DTO\SerieInfo;
use App\Infrastructure\CacheRaw;
use App\Infrastructure\DTO\ImdbFilter;
use App\Infrastructure\Imdb;
use App\Infrastructure\SuperglobalesOO;
use DateTime;

class StreamsController extends SecurityController
{
    /**
     * @var Twig
     */
    private $twig;
    /**
     * @var Iptv
     */
    private $iptv;

    /**
     * @var CacheRaw
     */
    private $cacheRaw;

    /**
     * @var SuperglobalesOO
     */
    private $superglobales;

    /**
     * @var Account
     */
    private $account;

    /**
     * @var Imdb
     */
    private $imdb;

    private $isAjax = false;

    public function __construct(
        Twig $twig,
        Iptv $iptv,
        CacheRaw $cacheRaw,
        SuperglobalesOO $superglobales,
        Account $account,
        Imdb $imdb
    ) {
        $this->twig          = $twig;
        $this->iptv          = $iptv;
        $this->cacheRaw      = $cacheRaw;
        $this->superglobales = $superglobales;
        $this->account       = $account;
        $this->imdb          = $imdb;

        parent::__construct($superglobales);

        if ($this->superglobales->getServer()->get('HTTP_X_REQUESTED_WITH', '') === 'XMLHttpRequest') {
            $this->isAjax = true;
        }
    }

    public function play(string $type, string $id, string $season = '', string $episode = '')
    {
        $url = '';
        if ($type === 'movie') {
            $movie = $this->iptv->getMovieInfo($id);
            $url = Param::BASE_URL_ABSOLUTE . '/asset/mkv/' . base64_encode($movie->getStreamLink());
        } elseif ($type === 'serie') {
            $serie = $this->iptv->getSerieInfo($id);

            if (isset($serie->getEpisodes()[$season][$episode])) {
                $url = Param::BASE_URL_ABSOLUTE .
                       '/asset/mkv/' .
                       base64_encode($serie->getEpisodes()[$season][$episode]->getStreamLink());
            }
        }

        if ($url === '') {
            exit;
        }

        echo $this->twig->render(
            'streamsPlay.html.twig',
            [
                'url' => $url,
            ]
        );
    }

    public function replayInfo(int $streamId): void
    {
        $streams = $this->iptv->getLiveStreams();

        /** @var Live $streams */
        $stream = current(
            array_filter($streams, function ($var) use ($streamId) {
                return $var->getStreamId() == $streamId;
            })
        );

        $dateStartArchive = new DateTime('-' . $stream->getTvArchiveDuration() . ' days');
        $replays          = $this->iptv->getReplaysByStreamId($streamId, $dateStartArchive);

        $replayDays = [];
        while ($dateStartArchive->format('Ymd') <= date('Ymd')) {
            $replayDays[] = $dateStartArchive->format('Y-m-d');
            $dateStartArchive->modify('+1 day');
        }

        echo $this->twig->render(
            'streamsReplayInfo.html.twig',
            [
                'stream'     => $stream,
                'replayDays' => $replayDays,
                'replays'    => $replays,
            ]
        );
    }

    public function liveInfo(string $streamName): void
    {
        $streamName = base64_decode($streamName);

        $streams = $this->iptv->getLiveStreamsByName($streamName);

        /** @var Live $refStream */
        $refStream = current($streams);

        $shortEpg = $this->iptv->getShortEPG($refStream->getStreamId());
        $name     = $refStream->getName();
        $img      = $refStream->getStreamIcon();

        $streamsSorted = [];
        if (isset($streams['4K'])) {
            $streamsSorted['4K'] = $streams['4K'];
        }
        if (isset($streams['UHD'])) {
            $streamsSorted['UHD'] = $streams['UHD'];
        }
        if (isset($streams['HD+'])) {
            $streamsSorted['HD+'] = $streams['HD+'];
        }
        if (isset($streams['FHD'])) {
            $streamsSorted['FHD'] = $streams['FHD'];
        }
        if (isset($streams['HEVC'])) {
            $streamsSorted['HEVC'] = $streams['HEVC'];
        }
        if (isset($streams['HD'])) {
            $streamsSorted['HD'] = $streams['HD'];
        }
        if (isset($streams['SD'])) {
            $streamsSorted['SD'] = $streams['SD'];
        }
        if (isset($streams['LQ'])) {
            $streamsSorted['LQ'] = $streams['LQ'];
        }
        $streamsSorted += $streams;

        if (count($streamsSorted) === 1) {
            $stream = current($streamsSorted);

            if ($stream->getTvArchive() < 1) {
                /*header('Location: ' . Param::VLC_DEEPLINK . $stream->getStreamLink());
                exit;*/
            }
        }

        $render = $this->twig->render(
            'streamsLiveInfo.html.twig',
            [
                'streams'    => $streamsSorted,
                'type'       => 'live',
                'shortEpg'   => $shortEpg,
                'name'       => $name,
                'img'        => $img,
                'streamName' => $streamName,
                'isFavorite' => isset($this->superglobales->getSession()->get('favorites')['live'][base64_encode($streamName)])
            ]
        );

        echo $render;
    }

    public function live(string $category, int $sort = 0, string $search = ''): void
    {
        $search = urldecode($search);

        $filter = [];
        if (is_numeric($category)) {
            $filter['cat'] = $category;
        } elseif ($category === 'favorites') {
            $filter['cat'] = 'favorites';
        }

        $streams        = $this->iptv->getLiveStreams($filter, $sort);
        $categories     = $this->iptv->getLiveCategories();

        $nbStreamsByCat = $this->iptv->getNbStreamByCat('live');
        $nbStreamsByCat += ['favorites' => count($this->superglobales->getSession()->get('favorites')['live'] ?? [])];

        $catName = isset($categories[$category]) ? $categories[$category]->getName() : '';

        $contentAlloweded = true;
        if (mb_stripos($catName, 'adult') || mb_stripos($catName, '18') || mb_stripos($catName, '♀')) {
            $contentAlloweded = false;

            if (md5($this->superglobales->getPost()->get('pass')) === $this->superglobales->getSession()->get('SKey')) {
                $contentAlloweded = true;
            }
        }

        $hiddenCategories = [];
        $hiddenStreams = 0;
        if (isset($this->superglobales->getSession()->get('hiddenCategories')['live'])) {
            foreach ($categories as $keyCat => $cat) {
                if (isset($this->superglobales->getSession()->get('hiddenCategories')['live'][$cat->getId()])) {
                    $hiddenStreams += $nbStreamsByCat[$cat->getId()];
                    $hiddenCategories[] = $cat;
                    unset($categories[$keyCat]);
                }
            }
        }

        $nbStreamsByCat += ['hidden' => $hiddenStreams];

        if ($category === 'favorites') {
            $favorites = [];
            foreach ($this->superglobales->getSession()->get('favorites')['live'] as $k => $v) {
                $favorites[base64_encode($this->stripQuality(base64_decode($k)))] = $v;
            }

            $streams = array_filter($streams, function ($var) use ($favorites) {
                return isset($favorites[base64_encode($var->getName())]);
            });
        }

        if ($search !== '') {
            $searchCleaned = $this->cleanSearch($search);
            $streams = array_filter($streams, function ($var) use ($searchCleaned) {
                if (stripos($var->getName(), '♀') !== false) {
                    return false;
                }

                if (stripos($var->getName(), 'xxx') !== false) {
                    return false;
                }

                return stripos($this->cleanSearch($var->getName()), $searchCleaned) !== false;
            });
        }

        if ($category === 'all') {
            $streams = array_filter($streams, function ($var) use ($searchCleaned) {
                if (stripos($var->getName(), '♀') !== false) {
                    return false;
                }

                if (stripos($var->getName(), 'xxx') !== false) {
                    return false;
                }

                return true;
            });
        }

        $render = $this->twig->render(
            ($this->isAjax ? 'ajax/' : '') . 'streamsLive.html.twig',
            [
                'start'            => $this->superglobales->getQuery()->get('start', 0),
                'streams'          => $streams,
                'type'             => 'live',
                'sort'             => $sort,
                'search'           => $search,
                'currentCat'       => $category,
                'categories'       => $categories,
                'hiddenCategories' => $hiddenCategories,
                'catName'          => $catName,
                'nbStreamsByCat'   => $nbStreamsByCat,
                'contentAlloweded' => $contentAlloweded,
                'isHidden'         => isset($categories[$category]) ? false : true
            ]
        );

        echo $render;
    }

    private function cleanSearch(string $search)
    {
        $str = htmlentities($search, ENT_NOQUOTES, 'utf-8');
        $str = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
        $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str);
        $str = preg_replace('#&[^;]+;#', '', $str);
        $str = preg_replace('#[^\w]#', '', $str);

        return $str;
    }

    // public function movie(string $category, int $sort = 0, string $search = ''): void
    // {
    //     $search = urldecode($search);
    //
    //     $filter = [];
    //     if (is_numeric($category)) {
    //         $filter['cat'] = $category;
    //     }
    //
    //     $streams    = $this->iptv->getMovieStreams($filter, $sort);
    //     $categories = $this->iptv->getMovieCategories();
    //
    //     $nbStreamsByCat = $this->iptv->getNbStreamByCat('movie');
    //     $nbStreamsByCat += ['favorites' => count($this->superglobales->getSession()->get('favorites')['movie'] ?? [])];
    //
    //     $catName = isset($categories[$category]) ? $categories[$category]->getName() : '';
    //
    //     $contentAlloweded = true;
    //     if (mb_stripos($catName, 'adult') || mb_stripos($catName, '18') || mb_stripos($catName, '♀')) {
    //         $contentAlloweded = false;
    //
    //         if (md5($this->superglobales->getPost()->get('pass')) === $this->superglobales->getSession()->get('SKey'))
    //         {
    //             $contentAlloweded = true;
    //         }
    //     }
    //
    //     $hiddenCategories = [];
    //     $hiddenStreams    = 0;
    //     if (isset($this->superglobales->getSession()->get('hiddenCategories')['movie'])) {
    //         foreach ($categories as $keyCat => $cat) {
    //             if (isset($this->superglobales->getSession()->get('hiddenCategories')['movie'][$cat->getId()])) {
    //                 $hiddenStreams += $nbStreamsByCat[$cat->getId()];
    //                 $hiddenCategories[] = $cat;
    //                 unset($categories[$keyCat]);
    //             }
    //         }
    //     }
    //
    //     $nbStreamsByCat += ['hidden' => $hiddenStreams];
    //
    //     if ($category === 'favorites') {
    //         $favorites = $this->superglobales->getSession()->get('favorites')['movie'];
    //         $streams = array_filter($streams, function ($var) use ($favorites) {
    //             return isset($favorites[$var->getStreamId()]);
    //         });
    //     }
    //
    //     if ($search !== '') {
    //         $searchCleaned = $this->cleanSearch($search);
    //         $streams = array_filter($streams, function ($var) use ($searchCleaned) {
    //             if (stripos($var->getName(), '♀') !== false) {
    //                 return false;
    //             }
    //
    //             if (stripos($var->getName(), 'xxx') !== false) {
    //                 return false;
    //             }
    //
    //             return stripos($this->cleanSearch($var->getName()), $searchCleaned) !== false;
    //         });
    //     }
    //
    //     if ($category === 'all') {
    //         $streams = array_filter($streams, function ($var) use ($searchCleaned) {
    //             if (stripos($var->getName(), '♀') !== false) {
    //                 return false;
    //             }
    //
    //             if (stripos($var->getName(), 'gims') !== false) {
    //                 return false;
    //             }
    //
    //             return true;
    //         });
    //     }
    //
    //     $render = $this->twig->render(
    //         ($this->isAjax ? 'ajax/' : '') . 'streamsMovie.html.twig',
    //         [
    //             'start'            => $this->superglobales->getQuery()->get('start', 0),
    //             'streams'          => $streams,
    //             'type'             => 'movie',
    //             'sort'             => $sort,
    //             'search'           => $search,
    //             'currentCat'       => $category,
    //             'categories'       => $categories,
    //             'hiddenCategories' => $hiddenCategories,
    //             'catName'          => $catName,
    //             'isHidden'         => isset($categories[$category]) ? false : true,
    //             'contentAlloweded' => $contentAlloweded,
    //             'nbStreamsByCat'   => $nbStreamsByCat,
    //             'streamView'       => $this->superglobales->getSession()->get('flaggedStreams')['movie']
    //         ]
    //     );
    //
    //     echo $render;
    // }

    public function movie(string $category, int $sort = 0, string $search = ''): void
    {
        $search = urldecode($search);

        $filter = [];
        if (is_numeric($category)) {
            $filter['cat'] = $category;
        }

        if ($category === 'favorites') {
            $favorites = array_keys($this->superglobales->getSession()->get('favorites')['movie']);
            $filter['streams'] = $favorites;
        }

        if ($search !== '') {
            $filter['search'] = $search;
        }

        if ($this->superglobales->getQuery()->has('advancedSearch')) {
            $filter['advancedSearch'] = $this->superglobales->getQuery()->get('advancedSearch');
        }

        $streams    = $this->iptv->getMovieStreams($filter, $sort, $this->superglobales->getQuery()->get('start', 0));
        $categories = $this->iptv->getMovieCategories();

        $nbStreamsByCat = $this->iptv->getNbStreamByCat2('movie');
        $nbStreamsByCat += ['favorites' => count($this->superglobales->getSession()->get('favorites')['movie'] ?? [])];

        $catName = isset($categories[$category]) ? $categories[$category]->getName() : '';

        $contentAlloweded = true;
        if (mb_stripos($catName, 'adult') || mb_stripos($catName, '18') || mb_stripos($catName, '♀')) {
            $contentAlloweded = false;

            if (md5($this->superglobales->getPost()->get('pass')) === $this->superglobales->getSession()->get('SKey'))
            {
                $contentAlloweded = true;
            }
        }

        $hiddenCategories = [];
        $hiddenStreams    = 0;
        if (isset($this->superglobales->getSession()->get('hiddenCategories')['movie'])) {
            foreach ($categories as $keyCat => $cat) {
                if (isset($this->superglobales->getSession()->get('hiddenCategories')['movie'][$cat->getId()])) {
                    $hiddenStreams += $nbStreamsByCat[$cat->getId()];
                    $hiddenCategories[] = $cat;
                    unset($categories[$keyCat]);
                }
            }
        }

        $nbStreamsByCat += ['hidden' => $hiddenStreams];

        $render = $this->twig->render(
            ($this->isAjax ? 'ajax/' : '') . 'streamsMovie.html.twig',
            [
                'start'            => $this->superglobales->getQuery()->get('start', 0),
                'streams'          => $streams,
                'type'             => 'movie',
                'sort'             => $sort,
                'search'           => $search,
                'currentCat'       => $category,
                'categories'       => $categories,
                'hiddenCategories' => $hiddenCategories,
                'catName'          => $catName,
                'isHidden'         => isset($categories[$category]) ? false : true,
                'contentAlloweded' => $contentAlloweded,
                'nbStreamsByCat'   => $nbStreamsByCat,
                'streamView'       => $this->superglobales->getSession()->get('flaggedStreams')['movie']
            ]
        );

        echo $render;
    }

    public function serie(string $category, int $sort = 0, string $search = ''): void
    {
        $search = urldecode($search);

        $filter = [];
        if (is_numeric($category)) {
            $filter['cat'] = $category;
        }

        if ($category === 'favorites') {
            $favorites = array_keys($this->superglobales->getSession()->get('favorites')['serie']);
            $filter['streams'] = $favorites;
        }

        if ($search !== '') {
            $filter['search'] = $search;
        }

        if ($this->superglobales->getQuery()->has('advancedSearch')) {
            $filter['advancedSearch'] = $this->superglobales->getQuery()->get('advancedSearch');
        }

        $streams        = $this->iptv->getSerieStreams($filter, $sort, $this->superglobales->getQuery()->get('start', 0));
        $categories     = $this->iptv->getSerieCategories();

        $nbStreamsByCat = $this->iptv->getNbStreamByCat('serie');
        $nbStreamsByCat += ['favorites' => count($this->superglobales->getSession()->get('favorites')['serie'] ?? [])];

        $catName = isset($categories[$category]) ? $categories[$category]->getName() : '';

        $hiddenStreams = 0;
        $hiddenCategories = [];
        if (isset($this->superglobales->getSession()->get('hiddenCategories')['serie'])) {
            foreach ($categories as $keyCat => $cat) {
                if (isset($this->superglobales->getSession()->get('hiddenCategories')['serie'][$cat->getId()])) {
                    $hiddenStreams += $nbStreamsByCat[$cat->getId()];
                    $hiddenCategories[] = $cat;
                    unset($categories[$keyCat]);
                }
            }
        }

        $nbStreamsByCat += ['hidden' => $hiddenStreams];

        $render = $this->twig->render(
            ($this->isAjax ? 'ajax/' : '') . 'streamsSerie.html.twig',
            [
                'start'            => $this->superglobales->getQuery()->get('start', 0),
                'streams'          => $streams,
                'type'             => 'serie',
                'sort'             => $sort,
                'search'           => $search,
                'currentCat'       => $category,
                'categories'       => $categories,
                'hiddenCategories' => $hiddenCategories,
                'catName'          => $catName,
                'nbStreamsByCat'   => $nbStreamsByCat,
                'isHidden'         => isset($categories[$category]) ? false : true,
            ]
        );

        echo $render;
    }

    public function advancedSearch(string $type)
    {
        echo $this->twig->render('advancedSearch.html.twig', ['type' => $type]);
    }

    private function stripQuality(string $string): string
    {
        return trim(str_replace(
            ['SD','LQ','UHD','FHD','HD+','HD', 'HEVC','4K','sd','lq','uhd','fhd','hd+','hd','hevc','4k'],
            '',
            $string
        ));
    }

    public function movieInfo(string $id): void
    {
        $movie = $this->iptv->getMovieInfo($id);
        $links = $this->iptv->getMovieLinks($id);

        echo $this->twig->render(
            'streamsMovieInfo.html.twig',
            [
                'movie'      => $movie,
                'links'      => $links,
                'streamView' => $this->superglobales->getSession()->get('flaggedStreams')['movie'],
                'isFavorite' => isset($this->superglobales->getSession()->get('favorites')['movie'][$id])
            ]
        );
    }

    private function hasSeeLastSerieEpisode(SerieInfo $serie): bool
    {
        $lastEpisode = end(end($serie->getEpisodes()))->getId();

        return isset($this->superglobales->getSession()->get('flaggedStreams')['serie'][$lastEpisode]);
    }

    public function serieInfo(string $id): void
    {
        $serie     = $this->iptv->getSerieInfo($id);
        $alternates = $this->iptv->getSerieAlternate($id);

        echo $this->twig->render(
            'streamsSerieInfo.html.twig',
            [
                'serie'      => $serie,
                'alternates' => $alternates,
                'streamView' => $this->superglobales->getSession()->get('flaggedStreams')['serie'],
                'isFavorite' => isset($this->superglobales->getSession()->get('favorites')['serie'][$id])
            ]
        );
    }

    // public function serie(string $category, int $sort = 0, string $search = ''): void
    // {
    //     $search = urldecode($search);
    //
    //     $filter = [];
    //     if (is_numeric($category)) {
    //         $filter['cat'] = $category;
    //     }
    //
    //     $streams        = $this->iptv->getSerieStreamsFromApi($filter, $sort);
    //     $categories     = $this->iptv->getSerieCategoriesFromApi();
    //
    //     $nbStreamsByCat = $this->iptv->getNbStreamByCat('serie');
    //     $nbStreamsByCat += ['favorites' => count($this->superglobales->getSession()->get('favorites')['serie'] ?? [])];
    //
    //     $catName = isset($categories[$category]) ? $categories[$category]->getName() : '';
    //
    //     $hiddenStreams = 0;
    //     $hiddenCategories = [];
    //     if (isset($this->superglobales->getSession()->get('hiddenCategories')['serie'])) {
    //         foreach ($categories as $keyCat => $cat) {
    //             if (isset($this->superglobales->getSession()->get('hiddenCategories')['serie'][$cat->getId()])) {
    //                 $hiddenStreams += $nbStreamsByCat[$cat->getId()];
    //                 $hiddenCategories[] = $cat;
    //                 unset($categories[$keyCat]);
    //             }
    //         }
    //     }
    //
    //     $nbStreamsByCat += ['hidden' => $hiddenStreams];
    //
    //     if ($category === 'favorites') {
    //         $favorites = $this->superglobales->getSession()->get('favorites')['serie'];
    //         $streams = array_filter($streams, function ($var) use ($favorites) {
    //             return isset($favorites[$var->getSerieId()]);
    //         });
    //     }
    //
    //     if ($search !== '') {
    //         $searchCleaned = $this->cleanSearch($search);
    //         $streams = array_filter($streams, function ($var) use ($searchCleaned) {
    //             return stripos($this->cleanSearch($var->getName()), $searchCleaned) !== false;
    //         });
    //     }
    //
    //     $render = $this->twig->render(
    //         ($this->isAjax ? 'ajax/' : '') . 'streamsSerie.html.twig',
    //         [
    //             'start'            => $this->superglobales->getQuery()->get('start', 0),
    //             'streams'          => $streams,
    //             'type'             => 'serie',
    //             'sort'             => $sort,
    //             'search'           => $search,
    //             'currentCat'       => $category,
    //             'categories'       => $categories,
    //             'hiddenCategories' => $hiddenCategories,
    //             'catName'          => $catName,
    //             'nbStreamsByCat'   => $nbStreamsByCat,
    //             'isHidden'         => isset($categories[$category]) ? false : true,
    //         ]
    //     );
    //
    //     echo $render;
    // }

    public function flagAsView(string $type, int $id, string $url)
    {
        $this->account->flagStreamAsView($type, $id);

        header('Location: ' . base64_decode($url));
    }

    public function suggestSerie($sort = 'moviemeter,asc')
    {
        $results = $this->imdb->filter(
            new ImdbFilter(
                1,
                ['tvSeries','tvMiniSeries'],
                7.0,
                10000,
                "$sort"
            )
        );

        $streams        = $this->iptv->getSerieStreamsSuggested($results);
        $categories     = $this->iptv->getSerieCategories();

        $nbStreamsByCat = $this->iptv->getNbStreamByCat('serie');
        $nbStreamsByCat += ['favorites' => count($this->superglobales->getSession()->get('favorites')['serie'] ?? [])];

        $catName = 'Suggest';

        $hiddenStreams = 0;
        $hiddenCategories = [];
        if (isset($this->superglobales->getSession()->get('hiddenCategories')['serie'])) {
            foreach ($categories as $keyCat => $cat) {
                if (isset($this->superglobales->getSession()->get('hiddenCategories')['serie'][$cat->getId()])) {
                    $hiddenStreams += $nbStreamsByCat[$cat->getId()];
                    $hiddenCategories[] = $cat;
                    unset($categories[$keyCat]);
                }
            }
        }

        $nbStreamsByCat += ['hidden' => $hiddenStreams];

        $render = $this->twig->render(
            ($this->isAjax ? 'ajax/' : '') . 'streamsSerie.html.twig',
            [
                'start'            => $this->superglobales->getQuery()->get('start', 0),
                'streams'          => $streams,
                'type'             => 'serie',
                'sort'             => $sort,
                'search'           => null,
                'currentCat'       => 'suggest',
                'categories'       => $categories,
                'hiddenCategories' => $hiddenCategories,
                'catName'          => $catName,
                'nbStreamsByCat'   => $nbStreamsByCat,
                'isHidden'         => false,
            ]
        );

        echo $render;
    }

    public function suggestMovie($sort = 'moviemeter,asc')
    {
        $results = $this->imdb->filter(
            new ImdbFilter(
                1,
                ['movie','tvMovie','short'],
                7.0,
                10000,
                "$sort"
            )
        );

        $streams    = $this->iptv->getMovieStreamsSuggested($results);
        $categories = $this->iptv->getMovieCategories();

        $nbStreamsByCat = $this->iptv->getNbStreamByCat2('movie');
        $nbStreamsByCat += ['favorites' => count($this->superglobales->getSession()->get('favorites')['movie'] ?? [])];

        $catName = 'Suggest';

        $hiddenCategories = [];
        $hiddenStreams    = 0;
        if (isset($this->superglobales->getSession()->get('hiddenCategories')['movie'])) {
            foreach ($categories as $keyCat => $cat) {
                if (isset($this->superglobales->getSession()->get('hiddenCategories')['movie'][$cat->getId()])) {
                    $hiddenStreams += $nbStreamsByCat[$cat->getId()];
                    $hiddenCategories[] = $cat;
                    unset($categories[$keyCat]);
                }
            }
        }

        $nbStreamsByCat += ['hidden' => $hiddenStreams];

        $render = $this->twig->render(
            ($this->isAjax ? 'ajax/' : '') . 'streamsMovie.html.twig',
            [
                'start'            => $this->superglobales->getQuery()->get('start', 0),
                'streams'          => $streams,
                'type'             => 'movie',
                'sort'             => $sort,
                'search'           => null,
                'currentCat'       => 'suggest',
                'categories'       => $categories,
                'hiddenCategories' => $hiddenCategories,
                'catName'          => $catName,
                'isHidden'         => false,
                'contentAlloweded' => true,
                'nbStreamsByCat'   => $nbStreamsByCat,
                'streamView'       => $this->superglobales->getSession()->get('flaggedStreams')['movie']
            ]
        );

        echo $render;
    }
}
