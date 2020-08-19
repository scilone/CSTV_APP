<?php

namespace App\Application;

use App\Infrastructure\SuperglobalesOO;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Twig
{
    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var SuperglobalesOO
     */
    private $superglobales;

    public function __construct(SuperglobalesOO $superglobalesOO, array $globalVars)
    {
        $loader = new FilesystemLoader(__DIR__ . '/../Templates');
        $this->twig = new Environment($loader);
        $this->superglobales = $superglobalesOO;

        foreach ($globalVars as $name => $value) {
            $this->twig->addGlobal($name, $value);
        }

        $this->addGenericVars();
        $this->addGenericFilters();
        $this->addGenericFunctions();
    }

    private function addGenericFilters(): void
    {
        $this->twig->addFilter(new TwigFilter('base64Encode', 'base64_encode'));
        $this->twig->addFilter(
            new TwigFilter(
                'stripLang',
                function ($string) {
                    return preg_replace('#\|\w+\|#', '', $string);
                }
            )
        );
    }

    private function addGenericFunctions(): void
    {
        $this->twig->addFunction(new TwigFunction('sessionId', 'session_id'));
    }

    private function addGenericVars(): void
    {
        $userAgent = $this->superglobales->getServer()->get('HTTP_USER_AGENT');

        $this->twig->addGlobal(
            'isIos',
            $isIos =
            stripos($userAgent, 'iPod') !== false
            || stripos($userAgent, 'iPad') !== false
            || stripos($userAgent, 'iPhone') !== false
        );

        $this->twig->addGlobal(
            'isTv',
            $isTv = strpos($userAgent, 'TV') !== false
                    || stripos($userAgent, 'Tizen') !== false
                    || stripos($userAgent, 'Web0S') !== false
                    || stripos($userAgent, 'BRAVIA') !== false
                    || stripos($userAgent, 'MIBOX') !== false
        );

        $this->twig->addGlobal('isAndroid', $isAndroid = stripos($userAgent, 'Android') !== false);
        $this->twig->addGlobal('isChrome', $isChrome = stripos($userAgent, 'Chrome') !== false);

        if ($isIos === false && $isAndroid === false && $isTv === false) {
            $this->twig->addGlobal('vlcDeeplink', '');
        }

        $currentUrl = 'https://' . $this->superglobales->getServer()->get('HTTP_HOST')
                      . $this->superglobales->getServer()->get('REQUEST_URI');

        $this->twig->addGlobal('currentUrl', $currentUrl);
    }

    /**
     * @param string $name
     * @param array  $context
     *
     * @return string
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig\Error\LoaderError
     */
    public function render(string $name, array $context = []): string
    {
        return $this->twig->render($name, $context);
    }
}
