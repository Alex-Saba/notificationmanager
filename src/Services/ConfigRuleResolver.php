<?php

namespace Acl\Communications\Services;

use Acl\Communications\Contracts\RuleResolverInterface;

class ConfigRuleResolver implements RuleResolverInterface
{
    public function resolve(string $eventKey, array $context = []): ?array
    {
        $rules = config('communications.rules', []);
        $rule = $rules[$eventKey] ?? null;

        if (! is_array($rule)) {
            return null;
        }

        return array_merge($rule, [
            'event_key' => $eventKey,
        ]);
    }
}
