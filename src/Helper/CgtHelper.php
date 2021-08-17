<?php declare(strict_types=1);
/**
 * Copyright (c) 2021 CardGate B.V.
 * All rights reserved.
 * See LICENSE for license details.
 */

namespace CardGate\Shopware\Helper;

use Symfony\Component\HttpFoundation\Request;

class CgtHelper
{
    /**
     * Retrieve super globals (replaces Request::createFromGlobals)
     *
     * @return Request
     */
    public function getGlobals(): Request
    {
        return new Request($_GET, $_POST, array(), $_COOKIE, $_FILES, $_SERVER);
    }
}
