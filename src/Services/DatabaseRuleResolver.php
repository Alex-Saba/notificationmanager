<?php

namespace Acl\Communications\Services;

use Acl\Communications\Contracts\RuleResolverInterface;
use Acl\Communications\Models\CommunicationRule;

class DatabaseRuleResolver implements RuleResolverInterface
{
    public function __construct(protected ConfigRuleResolver $fallback)
    {
    }

    public function resolve(string $eventKey, array $context = []): ?array
    {
        $query = CommunicationRule::query()
            ->with('template')
            ->where('event_key', $eventKey)
            ->where('active', true)
            ->whereHas('template', fn ($templateQuery) => $templateQuery->where('active', true));

        $rule = $query
            ->orderByDesc('priority')
            ->orderBy('id')
            ->first();

        if ($rule) {
            return [
                'id' => $rule->id,
                'event_key' => $rule->event_key,
                'template' => $rule->template?->key,
                'template_id' => $rule->template_id,
                'channels' => $rule->channels ?? [],
                'priority' => $rule->priority,
                'fallback' => $rule->fallback ?? [],
                'delay' => $rule->delay,
                'active' => $rule->active,
                'conditions' => $rule->conditions ?? [],
            ];
        }

        return $this->fallback->resolve($eventKey, $context);
    }
}
