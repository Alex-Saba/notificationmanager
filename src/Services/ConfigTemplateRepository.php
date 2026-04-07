<?php

namespace Acl\Communications\Services;

use Acl\Communications\Contracts\TemplateRepositoryInterface;

class ConfigTemplateRepository implements TemplateRepositoryInterface
{
    public function find(string $key, array $context = []): ?array
    {
        $templates = config('communications.templates.catalog', []);
        $template = $templates[$key] ?? null;

        if (! is_array($template)) {
            return null;
        }

        return array_merge($template, [
            'key' => $key,
        ]);
    }
}
