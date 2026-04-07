<?php

namespace Acl\Communications\Contracts;

interface DocumentGeneratorInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function generate(string $content, array $context = []): string;
}
