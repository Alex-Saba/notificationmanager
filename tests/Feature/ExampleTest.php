<?php

namespace Tests\Feature;

use Acl\Communications\Models\Communication;
use Acl\Communications\Models\CommunicationRule;
use Acl\Communications\Models\CommunicationTemplate;
use Acl\Communications\Models\NotificationEvent;
use App\Events\RequestCreated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('templates.seed_defaults', false);
        config()->set('templates.tag_entities', [
            [
                'model' => 'User',
                'variable' => '$user',
                'properties' => ['name', 'email'],
            ],
            [
                'model' => 'Company',
                'variable' => '$company',
                'properties' => ['name', 'address'],
            ],
            [
                'model' => 'Broken',
                'variable' => '$broken',
                'properties' => [],
            ],
        ]);
        config()->set('events', [
            'billing.payment-reminder.email' => [
                'label' => 'Payment reminder email',
                'payload' => [
                    'name' => 'required|string',
                    'due_date' => 'required|date',
                    'user_email' => 'required|email',
                ],
            ],
            'request.created.email' => [
                'label' => 'Request created email',
                'payload' => [
                    'request_number' => 'required|string',
                    'requester_name' => 'required|string',
                    'user_email' => 'required|email',
                ],
            ],
        ]);
    }

    public function test_templates_can_be_listed_via_json_with_declared_tags(): void
    {
        $template = $this->createTemplate();

        $response = $this->getJson(route('communications.api.templates.index'));

        $response->assertOk()
            ->assertJsonPath('templates.0.id', $template->id)
            ->assertJsonPath('templates.0.key', 'payment-reminder')
            ->assertJsonPath('templates.0.rule.event_key', 'billing.payment-reminder.email')
            ->assertJsonPath('event_keys.0.key', 'billing.payment-reminder.email')
            ->assertJsonPath('event_keys.0.label', 'Payment reminder email')
            ->assertJsonPath('tags.0.model', 'User')
            ->assertJsonPath('tags.0.variable', '$user')
            ->assertJsonPath('tags.0.tags.0.value', '{{ user.name }}')
            ->assertJsonPath('tags.1.model', 'Company')
            ->assertJsonPath('tags.1.tags.1.value', '{{ company.address }}');
    }

    public function test_invalid_declared_entities_are_ignored(): void
    {
        $response = $this->getJson(route('communications.api.templates.index'));

        $response->assertOk();
        $this->assertCount(2, $response->json('tags'));
    }

    public function test_the_home_page_redirects_to_the_package_ui(): void
    {
        $this->get('/')
            ->assertRedirect(route('communications.templates.page'));
    }

    public function test_notifications_demo_page_returns_the_vue_shell(): void
    {
        $this->get(route('communications.notifications.page'))
            ->assertOk()
            ->assertSee('id="app"', false);
    }

    public function test_notifications_can_be_created_listed_filtered_and_viewed(): void
    {
        $created = $this->postJson(route('communications.api.notifications.store'), [
            'type' => 'billing-reminder',
            'title' => 'Facture en attente',
            'message' => 'Votre facture #428 arrive a echeance demain.',
            'recipient_address' => 'alex@example.test',
            'date' => '2026-03-15',
        ]);

        $created->assertCreated()
            ->assertJsonPath('notification.type', 'billing-reminder')
            ->assertJsonPath('notification.title', 'Facture en attente')
            ->assertJsonPath('notification.read_at', null);

        $notificationId = $created->json('notification.id');

        $this->getJson(route('communications.api.notifications.index'))
            ->assertOk()
            ->assertJsonPath('notifications.0.id', $notificationId)
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.unread', 1);

        $this->getJson(route('communications.api.notifications.index', [
            'unread' => 1,
            'type' => 'billing-reminder',
            'date' => '2026-03-15',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'notifications');

        $this->getJson(route('communications.api.notifications.show', $notificationId))
            ->assertOk()
            ->assertJsonPath('notification.event_key', 'billing.reminder.in_app');
    }

    public function test_notifications_can_be_marked_read_and_unread(): void
    {
        $notification = $this->createNotification();

        $markRead = $this->patchJson(route('communications.api.notifications.read', $notification))
            ->assertOk()
            ->assertJsonPath('notification.id', $notification->id);

        $this->assertNotNull($markRead->json('notification.read_at'));

        $this->assertDatabaseMissing('communications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);

        $this->patchJson(route('communications.api.notifications.unread', $notification))
            ->assertOk()
            ->assertJsonPath('notification.id', $notification->id)
            ->assertJsonPath('notification.read_at', null);

        $this->assertDatabaseHas('communications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_notifications_can_be_deleted(): void
    {
        $notification = $this->createNotification();

        $this->deleteJson(route('communications.api.notifications.destroy', $notification))
            ->assertOk()
            ->assertJsonPath('message', 'Notification supprimee.');

        $this->assertDatabaseMissing('communications', [
            'id' => $notification->id,
        ]);
    }

    public function test_request_created_event_is_consumed_by_notification_listener(): void
    {
        $template = CommunicationTemplate::query()->create([
            'name' => 'Request Created',
            'key' => 'request-created',
            'event_key' => 'request.created.email',
            'channel' => 'mail',
            'subject' => 'Nouvelle demande',
            'content' => '<p>Bonjour {{ $requester_name }}, votre demande {{ $request_number }} est enregistree.</p>',
            'active' => true,
        ]);

        NotificationEvent::query()->create([
            'key' => 'request.created.email',
            'label' => 'Request created email',
            'payload_schema' => [
                'request_number' => 'required|string',
                'requester_name' => 'required|string',
                'user_email' => 'required|email',
            ],
            'is_active' => true,
        ]);

        $user = new User([
            'id' => 7,
            'name' => 'Alex',
            'email' => 'alex@example.test',
        ]);

        Event::dispatch(new RequestCreated($user, 'REQ-2026-001', [
        ]));

        $this->assertDatabaseHas('communications', [
            'event_key' => 'request.created.email',
            'channel' => 'email',
            'status' => 'sent',
            'recipient_address' => 'alex@example.test',
        ]);
    }

    public function test_test_send_command_dispatches_a_communication(): void
    {
        Artisan::call('communications:test-send', [
            '--email' => 'command@example.test',
            '--name' => 'Command User',
            '--request' => 'REQ-CLI-001',
        ]);

        $this->assertDatabaseHas('communications', [
            'event_key' => 'request.created.email',
            'channel' => 'email',
            'status' => 'sent',
            'recipient_address' => 'command@example.test',
        ]);
    }

    public function test_payment_reminder_simulation_command_uses_an_existing_template(): void
    {
        $template = CommunicationTemplate::query()->create([
            'name' => 'Payment Reminder',
            'key' => 'payment-reminder',
            'event_key' => 'billing.payment-reminder.email',
            'channel' => 'mail',
            'subject' => 'Rappel de paiement',
            'content' => '<p>Bonjour {{ $name }}</p><p>Votre echeance est fixee au {{ $due_date }}.</p>',
            'active' => true,
        ]);

        Artisan::call('communications:simulate-payment-reminder', [
            'templateKey' => $template->key,
            '--email' => 'payment@example.test',
            '--name' => 'Alex',
            '--due' => '2026-04-15',
        ]);

        $this->assertDatabaseHas('communications', [
            'event_key' => 'billing.payment-reminder.email',
            'channel' => 'email',
            'status' => 'sent',
            'recipient_address' => 'payment@example.test',
        ]);
    }

    protected function createTemplate(array $overrides = []): CommunicationTemplate
    {
        $template = CommunicationTemplate::query()->create([
            'name' => $overrides['name'] ?? 'Payment Reminder',
            'key' => $overrides['key'] ?? 'payment-reminder',
            'event_key' => $overrides['event_key'] ?? 'billing.payment-reminder.email',
            'channel' => $overrides['channel'] ?? 'mail',
            'content' => $overrides['content'] ?? '<h1>Rappel</h1><p>Bonjour {{ $user->name }}</p>',
            'active' => $overrides['active'] ?? true,
        ]);

        CommunicationRule::query()->create([
            'template_id' => $template->id,
            'event_key' => $overrides['event_key'] ?? 'billing.payment-reminder.email',
            'channels' => $overrides['channels'] ?? ['mail'],
            'priority' => $overrides['priority'] ?? 100,
            'fallback' => $overrides['fallback'] ?? [],
            'delay' => $overrides['delay'] ?? 0,
            'active' => $overrides['active'] ?? true,
        ]);

        return $template->fresh('rule');
    }

    protected function createNotification(array $overrides = []): Communication
    {
        return Communication::query()->create([
            'correlation_id' => 'test-correlation-id',
            'event_key' => $overrides['event_key'] ?? 'billing.reminder.in_app',
            'channel' => 'in_app',
            'status' => 'sent',
            'priority' => 100,
            'recipient_address' => $overrides['recipient_address'] ?? 'alex@example.test',
            'attempts' => 1,
            'idempotency_key' => 'test-idempotency-key-'.$this->fakerSafeSuffix(),
            'payload' => [
                'type' => $overrides['type'] ?? 'billing-reminder',
                'title' => $overrides['title'] ?? 'Facture en attente',
                'message' => $overrides['message'] ?? 'Votre facture #428 arrive a echeance demain.',
            ],
            'rendered_content' => $overrides['message'] ?? 'Votre facture #428 arrive a echeance demain.',
            'meta' => [
                'type' => $overrides['type'] ?? 'billing-reminder',
                'title' => $overrides['title'] ?? 'Facture en attente',
                'message' => $overrides['message'] ?? 'Votre facture #428 arrive a echeance demain.',
            ],
            'queued_at' => now(),
            'sent_at' => now(),
            'read_at' => $overrides['read_at'] ?? null,
        ]);
    }

    protected function fakerSafeSuffix(): string
    {
        return (string) random_int(1000, 9999);
    }
}
