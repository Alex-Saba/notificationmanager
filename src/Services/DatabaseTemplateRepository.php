<?php

namespace Acl\Communications\Services;

use Acl\Communications\Contracts\TemplateRepositoryInterface;
use Acl\Communications\Models\CommunicationTemplate;

class DatabaseTemplateRepository implements TemplateRepositoryInterface
{
    public function __construct(protected ConfigTemplateRepository $fallback)
    {
    }

    public function find(string $key, array $context = []): ?array
    {
        $templateId = (int) ($context['template_id'] ?? 0);

        $query = CommunicationTemplate::query()->where('active', true);

        if ($templateId > 0) {
            $query->where('id', $templateId);
        } else {
            $query->where('key', $key);
        }

        $template = $query->orderBy('id')->first();

        if ($template) {
            return [
                'id' => $template->id,
                'key' => $template->key,
                'name' => $template->name,
                'channel' => $template->channel,
                'subject' => $template->subject,
                'content' => $template->content,
                'variables' => $template->variables ?? [],
                'active' => $template->active,
            ];
        }

        return $this->fallback->find($key, $context);
    }
}
