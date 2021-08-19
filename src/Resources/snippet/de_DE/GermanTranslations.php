<?php declare(strict_types=1);
/**
 * Copyright (c) 2021 CardGate B.V.
 * All rights reserved.
 * See LICENSE for license details.
 */

namespace CardGate\Shopware\Resources\snippet\de_DE;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class GermanTranslations implements SnippetFileInterface
{
    /**
     * @return string
     */
    public function getName(): string
    {
        return 'messages.de-DE';
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return __DIR__ . '/messages.de-DE.json';
    }

    /**
     * @return string
     */
    public function getIso(): string
    {
        return 'de-DE';
    }

    /**
     * @return string
     */
    public function getAuthor(): string
    {
        return 'CardGate';
    }

    /**
     * @return bool
     */
    public function isBase(): bool
    {
        return false;
    }
}
