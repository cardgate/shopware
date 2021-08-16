<?php declare(strict_types=1);
/**
 * Copyright (c) 2021 CardGate B.V.
 * See DISCLAIMER.md for disclaimer details.
 */

namespace CardGate\Shopware\Resources\snippet\nl_NL;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class DutchTranslations implements SnippetFileInterface
{
    /**
     * @return string
     */
    public function getName(): string
    {
        return 'messages.nl-NL';
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return __DIR__ . '/messages.nl_NL.json';
    }

    /**
     * @return string
     */
    public function getIso(): string
    {
        return 'nl_NL';
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
