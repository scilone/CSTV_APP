<?php

namespace App\Infrastructure;

use DateTimeImmutable;

class Foot
{
    /**
     * @var CurlOO
     */
    private $curl;

    public function __construct(CurlOO $curl)
    {
        $this->curl = $curl;
    }

    public function getFixturesByDate(array $options)
    {
        $this->curl->init("https://api-football-v1.p.rapidapi.com/v3/fixtures?" . http_build_query($options));

        $this->curl
            ->setOption(CURLOPT_RETURNTRANSFER, true)
            ->setOption(CURLOPT_FOLLOWLOCATION, true)
            ->setOption(CURLOPT_ENCODING, "")
            ->setOption(CURLOPT_MAXREDIRS, 10)
            ->setOption(CURLOPT_TIMEOUT, 30)
            ->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1)
            ->setOption(CURLOPT_CUSTOMREQUEST, "GET")
            ->setOption(
                CURLOPT_HTTPHEADER,
                [
                    "x-rapidapi-host: api-football-v1.p.rapidapi.com",
                    "x-rapidapi-key: e5193b26f9mshef15393864a0ec4p1f9862jsn0f470ad2f98f"
                ]
            )
        ;

        $result = $this->curl->execute();

        var_dump(
            $result,
            json_decode($result),
            $this->curl->getError(),
            $this->curl->getInfo()
        );
    }

    public function getPrediction(int $fixtureId)
    {
        $this->curl->init("https://api-football-v1.p.rapidapi.com/v3/predictions?fixture=" . $fixtureId);

        $this->curl
            ->setOption(CURLOPT_RETURNTRANSFER, true)
            ->setOption(CURLOPT_FOLLOWLOCATION, true)
            ->setOption(CURLOPT_ENCODING, "")
            ->setOption(CURLOPT_MAXREDIRS, 10)
            ->setOption(CURLOPT_TIMEOUT, 30)
            ->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1)
            ->setOption(CURLOPT_CUSTOMREQUEST, "GET")
            ->setOption(
                CURLOPT_HTTPHEADER,
                [
                    "x-rapidapi-host: api-football-v1.p.rapidapi.com",
                    "x-rapidapi-key: e5193b26f9mshef15393864a0ec4p1f9862jsn0f470ad2f98f"
                ]
            )
        ;

        $result = $this->curl->execute();

        return $result;
        // var_dump(
        //     $result,
        //     json_decode($result),
        //     $this->curl->getError(),
        //     $this->curl->getInfo()
        // );
    }
}
