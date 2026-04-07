<?php

namespace Acl\Communications\Http\Controllers;

use Acl\Communications\Models\Communication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function page(): View
    {
        return view((string) config('communications.ui.view', 'welcome'), [
            'communicationsPage' => 'notifications',
            'aclCommunicationsUi' => [
                'page' => 'notifications',
                'mode' => 'list',
                'routes' => [
                    'templates' => [
                        'page' => route($this->routeName('templates.page')),
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
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Communication::query()
            ->where('channel', 'in_app')
            ->latest('created_at');

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        if ($type = trim((string) $request->string('type'))) {
            $query->where('event_key', $this->buildInAppEventKey($type));
        }

        if ($date = trim((string) $request->string('date'))) {
            $query->whereDate('created_at', $date);
        }

        $notifications = $query->get()->map(fn (Communication $communication) => $this->transformNotification($communication))->values();

        return response()->json([
            'notifications' => $notifications,
            'stats' => [
                'total' => $notifications->count(),
                'unread' => $notifications->where('read_at', null)->count(),
                'types' => $notifications->pluck('type')->filter()->unique()->values(),
            ],
        ]);
    }

    public function show(Communication $communication): JsonResponse
    {
        abort_unless($communication->channel === 'in_app', 404);

        return response()->json([
            'notification' => $this->transformNotification($communication),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string'],
            'recipient_address' => ['nullable', 'string', 'max:180'],
            'recipient_type' => ['nullable', 'string', 'max:120'],
            'recipient_id' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', 'date'],
            'read' => ['sometimes', 'boolean'],
        ]);

        $type = Str::slug($validated['type']);
        $createdAt = isset($validated['date']) ? Carbon::parse($validated['date']) : now();

        $notification = Communication::query()->create([
            'correlation_id' => (string) Str::uuid(),
            'event_key' => $this->buildInAppEventKey($type),
            'channel' => 'in_app',
            'status' => 'sent',
            'priority' => 100,
            'recipient_type' => $validated['recipient_type'] ?? null,
            'recipient_id' => $validated['recipient_id'] ?? null,
            'recipient_address' => $validated['recipient_address'] ?? null,
            'attempts' => 1,
            'idempotency_key' => (string) Str::uuid(),
            'payload' => [
                'type' => $type,
                'title' => $validated['title'],
                'message' => $validated['message'],
            ],
            'rendered_content' => $validated['message'],
            'meta' => [
                'title' => $validated['title'],
                'message' => $validated['message'],
                'type' => $type,
            ],
            'queued_at' => $createdAt,
            'sent_at' => $createdAt,
            'read_at' => ($validated['read'] ?? false) ? $createdAt : null,
        ]);

        $notification->timestamps = false;
        $notification->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return response()->json([
            'message' => 'Notification creee avec succes.',
            'notification' => $this->transformNotification($notification),
        ], 201);
    }

    public function markRead(Communication $communication): JsonResponse
    {
        abort_unless($communication->channel === 'in_app', 404);

        $communication->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'message' => 'Notification marquee comme lue.',
            'notification' => $this->transformNotification($communication->fresh()),
        ]);
    }

    public function markUnread(Communication $communication): JsonResponse
    {
        abort_unless($communication->channel === 'in_app', 404);

        $communication->update([
            'read_at' => null,
        ]);

        return response()->json([
            'message' => 'Notification marquee comme non lue.',
            'notification' => $this->transformNotification($communication->fresh()),
        ]);
    }

    public function destroy(Communication $communication): JsonResponse
    {
        abort_unless($communication->channel === 'in_app', 404);

        $communication->delete();

        return response()->json([
            'message' => 'Notification supprimee.',
        ]);
    }

    protected function transformNotification(Communication $communication): array
    {
        $meta = $communication->meta ?? [];
        $payload = $communication->payload ?? [];
        $segments = explode('.', $communication->event_key);
        $fallbackType = count($segments) >= 2 ? implode('-', array_slice($segments, 0, 2)) : '';
        $type = (string) ($meta['type'] ?? $payload['type'] ?? $fallbackType);
        $title = (string) ($meta['title'] ?? $payload['title'] ?? Str::headline(str_replace(['.', '-', '_'], ' ', $type)));
        $message = (string) ($meta['message'] ?? $payload['message'] ?? $communication->rendered_content ?? '');

        return [
            'id' => $communication->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'event_key' => $communication->event_key,
            'status' => $communication->status,
            'channel' => $communication->channel,
            'recipient_address' => $communication->recipient_address,
            'recipient_type' => $communication->recipient_type,
            'recipient_id' => $communication->recipient_id,
            'read_at' => $communication->read_at,
            'created_at' => $communication->created_at,
            'updated_at' => $communication->updated_at,
        ];
    }

    protected function routeName(string $suffix): string
    {
        return trim((string) config('communications.ui.name_prefix', 'communications.'), '.').'.'.$suffix;
    }

    protected function buildInAppEventKey(string $type): string
    {
        $segments = explode('-', Str::slug($type), 2);

        if (count($segments) === 1) {
            $segments[] = 'notification';
        }

        return $segments[0].'.'.$segments[1].'.in_app';
    }
}
