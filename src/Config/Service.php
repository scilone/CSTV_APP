<?php

namespace App\Config;

Class Service
{
    const CONTROLLER_TEST     = 'App\Controller\TestController';
    const CONTROLLER_HOME     = 'App\Controller\HomeController';
    const CONTROLLER_CATEGORY = 'App\Controller\CategoryController';
    const CONTROLLER_STREAM   = 'App\Controller\StreamsController';
    const CONTROLLER_EXTRA    = 'App\Controller\ExtraController';
    const CONTROLLER_ACCOUNT  = 'App\Controller\AccountController';
    const CONTROLLER_CACHES   = 'App\Controller\CachesController';
    const CONTROLLER_IMPORT   = 'App\Controller\ImportController';
    const CONTROLLER_COINBASE = 'App\Controller\CoinbaseController';
    const CONTROLLER_FOOT     = 'App\Controller\FootController';

    const APPLICATION_TWIG    = 'App\Application\Twig';
    const APPLICATION_IPTV    = 'App\Application\Iptv';
    const APPLICATION_ACCOUNT = 'App\Application\Account';

    const DOMAIN_IPTV_XCODE_API            = 'App\Domain\Iptv\XcodeApi';
    const DOMAIN_REPOSITORY_ACCOUNT        = 'App\Domain\Repository\Account';
    const DOMAIN_REPOSITORY_CATEGORY       = 'App\Domain\Repository\Category';
    const DOMAIN_REPOSITORY_GENRE          = 'App\Domain\Repository\Genre';
    const DOMAIN_REPOSITORY_PEOPLE         = 'App\Domain\Repository\People';
    const DOMAIN_REPOSITORY_STREAM         = 'App\Domain\Repository\Stream';
    const DOMAIN_REPOSITORY_COINBASE_HISTO = 'App\Domain\Repository\CoinbaseHisto';

    const INFRASTRUCTURE_SUPERGLOBALES = 'App\Infrastructure\SuperglobalesOO';
    const INFRASTRUCTURE_CURL          = 'App\Infrastructure\CurlOO';
    const INFRASTRUCTURE_CACHE_RAW     = 'App\Infrastructure\CacheRaw';
    const INFRASTRUCTURE_CACHE_ITEM    = 'App\Infrastructure\CacheItem';
    const INFRASTRUCTURE_SODIUM        = 'App\Infrastructure\SodiumDummies';
    const INFRASTRUCTURE_MYSQL         = 'App\Infrastructure\SqlConnection';
    const INFRASTRUCTURE_COINBASE      = 'App\Infrastructure\Coinbase';
    const INFRASTRUCTURE_FOOT          = 'App\Infrastructure\Foot';
    const INFRASTRUCTURE_IMDB          = 'App\Infrastructure\Imdb';
}
