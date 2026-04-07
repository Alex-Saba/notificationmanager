<?php

namespace Acl\Communications\Contracts;

interface RuleResolverInterface
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    public function resolve(string $eventKey, array $context = []): ?array;
}
