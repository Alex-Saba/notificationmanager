<?php

namespace App\Http\Controllers;

use Acl\Communications\Models\Communication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function page(): View
    {
        return view('welcome');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Communication::query()
            ->where('channel', 'database')
            ->latest('created_at');

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        if ($type = trim((string) $request->string('type'))) {
            $query->where('event_key', 'like', 'in_app.'.Str::slug($type).'%');
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
        abort_unless($communication->channel === 'database', 404);

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
            'event_key' => 'in_app.'.$type,
            'channel' => 'database',
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
        abort_unless($communication->channel === 'database', 404);

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
        abort_unless($communication->channel === 'database', 404);

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
        abort_unless($communication->channel === 'database', 404);

        $communication->delete();

        return response()->json([
            'message' => 'Notification supprimee.',
        ]);
    }

    protected function transformNotification(Communication $communication): array
    {
        $meta = $communication->meta ?? [];
        $payload = $communication->payload ?? [];
        $type = (string) ($meta['type'] ?? $payload['type'] ?? Str::after($communication->event_key, 'in_app.'));
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
}
