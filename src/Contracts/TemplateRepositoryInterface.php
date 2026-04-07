<?php

namespace Acl\Communications\Contracts;

interface TemplateRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    public function find(string $key, array $context = []): ?array;
}
