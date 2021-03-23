<?php

declare(strict_types=1);

namespace Etbag\TrxpsPayments\Resources\snippet\de_DE;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_de_DE implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'trxps.payments.de-DE';
    }

    public function getPath(): string
    {
        return __DIR__.'/trxps.payments.de-DE.json';
    }

    public function getIso(): string
    {
        return 'de-DE';
    }

    public function getAuthor(): string
    {
        return 'ETB AG';
    }

    public function isBase(): bool
    {
        return false;
    }
}
