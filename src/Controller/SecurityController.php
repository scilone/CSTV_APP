<?php

namespace App\Controller;

use App\Application\Iptv;
use App\Config\Param;
use App\Infrastructure\SuperglobalesOO;

class SecurityController
{
    /**
     * @var SuperglobalesOO
     */
    private $superglobales;

    public function __construct(SuperglobalesOO $superglobales)
    {
        $this->superglobales = $superglobales;

        if ($this->superglobales->getQuery()->has('sessid')) {
            session_id(base64_decode($this->superglobales->getQuery()->get('sessid')));
            session_start();
        }

        if ($this->superglobales->getSession()->has('sessionCreated')
            && $this->superglobales->getSession()->get('sessionCreated') + (3600 * 2) < time()
        ) {
            $this->setCookieRedirect();

            session_destroy();

            header('Location: ' . Param::BASE_URL_ABSOLUTE . '/account/autolog');
            exit;
        }

        if ($this->superglobales->getSession()->has('userId') === false) {
            $this->setCookieRedirect();

            if ($this->superglobales->getCookie()->has('autologId')) {
                header('Location: ' . Param::BASE_URL_ABSOLUTE . '/account/autolog');
                exit;
            }

            header('Location: ' . Param::BASE_URL_ABSOLUTE . '/account/log');
            exit;
        }

        if ($this->superglobales->getSession()->has(Iptv::PREFIX . 'host') === false) {
            $this->setCookieRedirect();

            header('Location: ' . Param::BASE_URL_ABSOLUTE . '/account/iptv');
            exit;
        }
    }

    private function setCookieRedirect()
    {
        setcookie(
            'redirect',
            $this->superglobales->getServer()->get('REDIRECT_URL'),
            time() + 3600,
            Param:: BASE_URL_RELATIVE,
            false,
            true,
            true
        );
    }
}
