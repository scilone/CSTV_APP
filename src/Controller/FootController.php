<?php

namespace App\Controller;

use App\Application\Twig;
use App\Infrastructure\Foot;
use App\Infrastructure\SuperglobalesOO;
use DateTimeImmutable;

class FootController
{
    /**
     * @var Twig
     */
    private $twig;

    /**
     * @var SuperglobalesOO
     */
    private $superglobales;

    /**
     * @var Foot
     */
    private $foot;

    public function __construct(
        Twig $twig,
        SuperglobalesOO $superglobales,
        Foot $foot
    ) {
        $this->twig          = $twig;
        $this->superglobales = $superglobales;
        $this->foot          = $foot;
    }

    public function getFixturesByDate()
    {
        var_dump('START');

        $options = [
            'league' => 9,
            'season' => 2021,
            // 'from'   => date('Y-m-d'),
            // 'to'     => '2021-07-15',
        ];

        $this->foot->getFixturesByDate($options);

        var_dump('END');
    }

    public function getPrediction($id)
    {
        // var_dump('START');

        echo $this->foot->getPrediction($id);

        // var_dump('END');
    }
}
