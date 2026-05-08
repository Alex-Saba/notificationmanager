<?php

namespace Acl\Communications\Templates;

use Acl\Communications\Contracts\TemplateRendererInterface;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Blade;

class BladeTemplateRenderer implements TemplateRendererInterface
{
    public function __construct(protected ViewFactory $views) {}

    public function render(string $template, array $data = []): string
    {
        $template = $this->normalizeDotNotationTags($template);
        $data = $this->normalizeData($data);

        if ($this->views->exists($template)) {
            return $this->views->make($template, $data)->render();
        }

        return Blade::render($template, $data);
    }

    protected function normalizeDotNotationTags(string $template): string
    {
        return preg_replace_callback('/{{\s*([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)+)\s*}}/', function (array $matches): string {
            $segments = explode('.', $matches[1]);
            $root = array_shift($segments);

            return '{{ $'.$root.'->'.implode('->', $segments).' }}';
        }, $template) ?? $template;
    }

    protected function normalizeData(array $data): array
    {
        return collect($data)
            ->map(fn (mixed $value) => $this->normalizeValue($value))
            ->all();
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = collect($value)
            ->map(fn (mixed $nested) => $this->normalizeValue($nested))
            ->all();

        return array_is_list($normalized) ? $normalized : (object) $normalized;
    }
}
