<?php


namespace App\Infrastructure;


use App\Infrastructure\DTO\ImdbFilter;

class Imdb
{
    private const BASE_URL = 'https://www.imdb.com';

    /**
     * @var CurlOO
     */
    private $curlOO;

    public function __construct(CurlOO $curlOO)
    {
        $this->curlOO = $curlOO;
    }

    public function filter(ImdbFilter $imdbFilter)
    {
        $response = $this->curlOO
            ->init(
                self::BASE_URL . '/search/keyword/_ajax?' .  (string) $imdbFilter
            )
            ->setOption(CURLOPT_RETURNTRANSFER, true)
            ->execute()
        ;

        $matches = [];
        preg_match_all(
            '#lister-item-header.*<a.*>(?<name>.*)<\/a>.*lister-item-year.*>\((?<year>.*)\)<\/span>.*genre.*>(?<genre>.*)<\/span>.*imdb-rating.*<\/span>.*<strong>(?<rate>.*)<\/strong>.*<p class="">(?<plot>.*)<\/p>#sU',
            $response,
            $matches
        );

        $results = [];
        foreach ($matches[0] as $key => $match) {
            $results[] = [
                'name'  => $matches['name'][$key],
                'year'  => $matches['year'][$key],
                'genre' => trim($matches['genre'][$key]),
                'rate'  => $matches['rate'][$key],
                'plot'  => trim($matches['plot'][$key]),
            ];
        }

        return $results;
    }
}
