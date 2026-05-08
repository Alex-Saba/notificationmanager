<?php

namespace Acl\Communications\Http\Controllers;

use Acl\Communications\Models\CommunicationTemplate;
use Acl\Communications\Models\NotificationEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function templatesPage(): View
    {
        return $this->shellView('templates', 'list');
    }

    public function index(): JsonResponse
    {
        $templates = $this->getTemplates();

        return response()->json([
            'templates' => $templates,
            'tags' => $this->getTemplateTags(),
            'event_keys' => $this->getAvailableEventKeys(),
        ]);
    }

    public function show(string $template): JsonResponse
    {
        $resolvedTemplate = CommunicationTemplate::query()
            ->with('rule')
            ->findOrFail((int) $template);

        return response()->json([
            'template' => $this->transformTemplate($resolvedTemplate),
            'tags' => $this->getTemplateTags(),
            'event_keys' => $this->getAvailableEventKeys(),
        ]);
    }

    protected function shellView(string $page, string $mode, array $extra = []): View
    {
        return view((string) config('communications.ui.view', 'welcome'), [
            'communicationsPage' => $page,
            'aclCommunicationsUi' => array_merge($this->uiConfig($page, $mode), $extra),
        ]);
    }

    protected function uiConfig(string $page, string $mode): array
    {
        return [
            'page' => $page,
            'mode' => $mode,
            'routes' => [
                'templates' => [
                    'page' => route($this->routeName('templates.page')),
                    'index' => route($this->routeName('api.templates.index')),
                    'showPattern' => route($this->routeName('api.templates.show'), ['template' => '__TEMPLATE__']),
                ],
                'notifications' => [
                    'page' => route($this->routeName('notifications.page')),
                    'index' => route($this->routeName('api.notifications.index')),
                    'store' => route($this->routeName('api.notifications.store')),
                    'showPattern' => route($this->routeName('api.notifications.show'), ['communication' => '__NOTIFICATION__']),
                    'markReadPattern' => route($this->routeName('api.notifications.read'), ['communication' => '__NOTIFICATION__']),
                    'markUnreadPattern' => route($this->routeName('api.notifications.unread'), ['communication' => '__NOTIFICATION__']),
                    'destroyPattern' => route($this->routeName('api.notifications.destroy'), ['communication' => '__NOTIFICATION__']),
                ],
            ],
        ];
    }

    protected function routeName(string $suffix): string
    {
        return trim((string) config('communications.ui.name_prefix', 'communications.'), '.').'.'.$suffix;
    }

    protected function getTemplates(): Collection
    {
        $this->seedDefaultTemplatesIfEmpty();

        return $this->baseTemplateQuery()
            ->with('rule')
            ->orderBy('key')
            ->get()
            ->map(fn (CommunicationTemplate $template) => $this->transformTemplate($template))
            ->values();
    }

    protected function transformTemplate(CommunicationTemplate $template): array
    {
        $content = (string) $template->content;

        return [
            'id' => $template->id,
            'identifier' => (string) $template->id,
            'display_name' => $template->name,
            'name' => $template->name,
            'key' => $template->key,
            'event_key' => $template->event_key,
            'channel' => $template->channel,
            'active' => $template->active,
            'updated_at' => $template->updated_at,
            'excerpt' => Str::limit(trim((string) preg_replace('/\s+/', ' ', $content)), 110),
            'content' => $content,
            'size' => strlen($content),
            'rule' => [
                'event_key' => $template->rule?->event_key,
                'channels' => $template->rule?->channels ?? [],
                'priority' => $template->rule?->priority,
                'fallback' => $template->rule?->fallback ?? [],
                'delay' => $template->rule?->delay,
                'active' => $template->rule?->active ?? $template->active,
            ],
        ];
    }

    protected function getTemplateTags(): Collection
    {
        return collect(config('templates.tag_entities', []))
            ->map(function (array $entity): ?array {
                $model = trim((string) ($entity['model'] ?? ''));
                $variable = trim((string) ($entity['variable'] ?? ''));
                $properties = collect($entity['properties'] ?? [])
                    ->filter(fn (mixed $property) => is_string($property) && trim($property) !== '')
                    ->map(fn (string $property) => trim($property))
                    ->unique()
                    ->values();

                if ($model === '' || $variable === '' || $properties->isEmpty()) {
                    return null;
                }

                return [
                    'model' => $model,
                    'variable' => $variable,
                    'tags' => $properties->map(fn (string $property) => [
                        'label' => ltrim($variable, '$').'.'.$property,
                        'value' => '{{ '.ltrim($variable, '$').'.'.$property.' }}',
                    ])->values(),
                ];
            })
            ->filter()
            ->values();
    }

    protected function getAvailableEventKeys(): Collection
    {
        $runtime = NotificationEvent::query()
            ->where('is_active', true)
            ->orderBy('label')
            ->get()
            ->map(fn (NotificationEvent $event) => [
                'key' => $event->key,
                'label' => $event->label,
                'description' => null,
            ]);

        $configured = collect(config('events', []))
            ->map(function (mixed $entry, mixed $key): ?array {
                if (! is_string($key) || ! is_array($entry)) {
                    return null;
                }

                return [
                    'key' => $key,
                    'label' => trim((string) ($entry['label'] ?? $key)),
                    'description' => ($entry['description'] ?? null) !== null ? trim((string) $entry['description']) : null,
                ];
            })
            ->filter()
            ->unique('key')
            ->values();

        return $runtime
            ->concat($configured)
            ->unique('key')
            ->sortBy('label')
            ->values();
    }

    protected function seedDefaultTemplatesIfEmpty(): void
    {
        if (! config('templates.seed_defaults', true) || app()->environment('testing') || $this->baseTemplateQuery()->exists()) {
            return;
        }

        DB::transaction(function () {
            $welcome = $this->baseTemplateQuery()->create([
                'name' => 'Welcome Email',
                'key' => 'welcome-email',
                'event_key' => 'request.created.email',
                'channel' => 'mail',
                'content' => '<h1>Bienvenue {{ $name }}</h1><p>Votre compte est pret. Vous pouvez maintenant acceder a votre espace.</p>',
                'active' => true,
            ]);
            $welcome->rule()->create([
                'event_key' => 'request.created.email',
                'channels' => ['mail'],
                'priority' => 100,
                'fallback' => [],
                'delay' => 0,
                'active' => true,
            ]);

            $payment = $this->baseTemplateQuery()->create([
                'name' => 'Payment Reminder',
                'key' => 'payment-reminder',
                'event_key' => 'billing.payment-reminder.email',
                'channel' => 'mail',
                'content' => '<h1>Rappel de paiement</h1><p>Bonjour {{ $name }}, votre facture arrive a echeance le {{ $due_date }}.</p>',
                'active' => true,
            ]);
            $payment->rule()->create([
                'event_key' => 'billing.payment-reminder.email',
                'channels' => ['mail'],
                'priority' => 100,
                'fallback' => [],
                'delay' => 0,
                'active' => true,
            ]);
        });
    }

    protected function baseTemplateQuery(): Builder
    {
        return CommunicationTemplate::query();
    }
}
