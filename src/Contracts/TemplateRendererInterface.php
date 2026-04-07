<?php

namespace Acl\Communications\Contracts;

interface TemplateRendererInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $template, array $data = []): string;
}
