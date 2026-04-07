<?php

namespace Acl\Communications\Documents;

use Acl\Communications\Contracts\DocumentGeneratorInterface;

class InlineDocumentGenerator implements DocumentGeneratorInterface
{
    public function generate(string $content, array $context = []): string
    {
        return $content;
    }
}
