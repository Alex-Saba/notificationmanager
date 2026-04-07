<?php

namespace Acl\Communications\Http\Controllers;

use Acl\Communications\Models\NotificationEvent;
use Acl\Communications\Models\CommunicationTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function templatesPage(): View
    {
        return $this->shellView('templates', 'list');
    }

    public function createPage(): View
    {
        return $this->shellView('templates', 'create');
    }

    public function editPage(string $template): View
    {
        return $this->shellView('templates', 'edit', [
            'editingTemplateId' => (int) $template,
        ]);
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->templateRules());
        $key = $this->resolveTemplateKey($validated);

        if ($this->baseTemplateQuery()->where('key', $key)->exists()) {
            return response()->json([
                'message' => 'Un template avec cette cle existe deja.',
                'errors' => [
                    'key' => ['Un template avec cette cle existe deja.'],
                ],
            ], 422);
        }

        $template = DB::transaction(function () use ($validated, $key) {
            $channels = $this->normalizeStringArray($validated['channels']);
            $fallback = $this->normalizeStringArray($validated['fallback'] ?? []);

            $template = $this->baseTemplateQuery()->create([
                'name' => $validated['name'],
                'key' => $key,
                'event_key' => $this->resolveEventKey($key, $validated['event_key'] ?? null),
                'channel' => count($channels) === 1 ? $channels[0] : null,
                'content' => trim($validated['content']),
                'active' => $validated['active'],
            ]);

            $template->rule()->create([
                'event_key' => $template->event_key,
                'channels' => $channels,
                'priority' => $validated['priority'],
                'fallback' => $fallback,
                'delay' => $validated['delay'],
                'active' => $validated['active'],
            ]);

            return $template->load('rule');
        });

        return response()->json([
            'message' => 'Template cree avec succes.',
            'template' => $this->transformTemplate($template),
        ], 201);
    }

    public function update(Request $request, string $template): JsonResponse
    {
        $validated = $request->validate($this->templateRules(isUpdate: true));
        $resolvedTemplate = $this->baseTemplateQuery()
            ->with('rule')
            ->findOrFail((int) $template);

        $key = $this->resolveTemplateKey($validated, $resolvedTemplate);

        if ($this->baseTemplateQuery()
            ->where('id', '!=', $resolvedTemplate->id)
            ->where('key', $key)
            ->exists()) {
            return response()->json([
                'message' => 'Un template avec cette cle existe deja.',
                'errors' => [
                    'key' => ['Un template avec cette cle existe deja.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($resolvedTemplate, $validated, $key) {
            $channels = $this->normalizeStringArray($validated['channels']);
            $fallback = $this->normalizeStringArray($validated['fallback'] ?? []);

            $resolvedTemplate->update([
                'name' => $validated['name'],
                'key' => $key,
                'event_key' => $this->resolveEventKey($key, $validated['event_key']),
                'channel' => count($channels) === 1 ? $channels[0] : null,
                'content' => trim($validated['content']),
                'active' => $validated['active'],
            ]);

            $resolvedTemplate->rule()->updateOrCreate(
                ['template_id' => $resolvedTemplate->id],
                [
                    'event_key' => $resolvedTemplate->event_key,
                    'channels' => $channels,
                    'priority' => $validated['priority'],
                    'fallback' => $fallback,
                    'delay' => $validated['delay'],
                    'active' => $validated['active'],
                ]
            );
        });

        $resolvedTemplate->refresh()->load('rule');

        return response()->json([
            'message' => 'Template mis a jour avec succes.',
            'template' => $this->transformTemplate($resolvedTemplate),
        ]);
    }

    public function updateRule(Request $request, string $template): JsonResponse
    {
        $validated = $request->validate([
            'event_key' => ['nullable', 'string', 'max:180'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', 'in:mail'],
            'priority' => ['required', 'integer', 'min:1'],
            'fallback' => ['nullable', 'array'],
            'fallback.*' => ['string', 'in:mail'],
            'delay' => ['required', 'integer', 'min:0'],
            'active' => ['required', 'boolean'],
        ]);

        $resolvedTemplate = $this->baseTemplateQuery()
            ->with('rule')
            ->findOrFail((int) $template);

        DB::transaction(function () use ($resolvedTemplate, $validated) {
            $channels = $this->normalizeStringArray($validated['channels']);
            $fallback = $this->normalizeStringArray($validated['fallback'] ?? []);

            $resolvedTemplate->update([
                'event_key' => $this->resolveEventKey($resolvedTemplate->key, $validated['event_key'] ?? $resolvedTemplate->rule?->event_key),
                'channel' => count($channels) === 1 ? $channels[0] : null,
                'active' => $validated['active'],
            ]);

            $resolvedTemplate->rule()->updateOrCreate(
                ['template_id' => $resolvedTemplate->id],
                [
                    'event_key' => $resolvedTemplate->event_key,
                    'channels' => $channels,
                    'priority' => $validated['priority'],
                    'fallback' => $fallback,
                    'delay' => $validated['delay'],
                    'active' => $validated['active'],
                ]
            );
        });

        $resolvedTemplate->refresh()->load('rule');

        return response()->json([
            'message' => 'Regle mise a jour avec succes.',
            'template' => $this->transformTemplate($resolvedTemplate),
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
                    'createPage' => route($this->routeName('templates.create.page')),
                    'editPagePattern' => route($this->routeName('templates.edit.page'), ['template' => '__TEMPLATE__']),
                    'index' => route($this->routeName('api.templates.index')),
                    'showPattern' => route($this->routeName('api.templates.show'), ['template' => '__TEMPLATE__']),
                    'store' => route($this->routeName('api.templates.store')),
                    'updatePattern' => route($this->routeName('api.templates.update'), ['template' => '__TEMPLATE__']),
                    'updateRulePattern' => route($this->routeName('api.templates.rule.update'), ['template' => '__TEMPLATE__']),
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

    protected function templateRules(bool $isUpdate = false): array
    {
        $eventKeys = $this->getAvailableEventKeys()->pluck('key')->all();
        $eventKeyRules = [$isUpdate ? 'required' : 'nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/'];

        if ($eventKeys !== []) {
            $eventKeyRules[] = Rule::in($eventKeys);
        }

        return [
            'name' => ['required', 'string', 'max:120'],
            'key' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'content' => ['required', 'string'],
            'event_key' => $eventKeyRules,
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', 'in:mail,email,sms,in_app'],
            'priority' => ['required', 'integer', 'min:1'],
            'fallback' => ['nullable', 'array'],
            'fallback.*' => ['string', 'in:mail,email,sms,in_app'],
            'delay' => ['required', 'integer', 'min:0'],
            'active' => ['required', 'boolean'],
        ];
    }

    protected function resolveTemplateKey(array $validated, ?CommunicationTemplate $template = null): string
    {
        $candidate = trim((string) ($validated['key'] ?? ''));

        if ($candidate !== '') {
            return $candidate;
        }

        if ($template && $template->key !== '') {
            return $template->key;
        }

        return Str::slug($validated['name']) ?: 'template';
    }

    protected function resolveEventKey(string $key, ?string $eventKey = null): string
    {
        $candidate = trim((string) $eventKey);

        return $candidate !== '' ? $candidate : 'communications.'.$key.'.email';
    }

    protected function normalizeStringArray(array $values): array
    {
        return collect($values)
            ->filter(fn (mixed $value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->unique()
            ->values()
            ->all();
    }

    protected function baseTemplateQuery(): Builder
    {
        return CommunicationTemplate::query();
    }
}
