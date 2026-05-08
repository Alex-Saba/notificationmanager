<?php

namespace Tests\Unit;

use Acl\Communications\Contracts\ApplicationEventConsumerInterface;
use Acl\Communications\Contracts\CommunicationServiceInterface;
use Acl\Communications\Contracts\NotificationManagerInterface;
use Acl\Communications\Contracts\TemplateRendererInterface;
use Acl\Communications\Events\CommunicationOrchestrated;
use Acl\Communications\Events\NotificationSent;
use Acl\Communications\Jobs\SendCommunicationJob;
use Acl\Communications\Listeners\NotificationListener;
use Acl\Communications\Mail\CommunicationMail;
use Acl\Communications\Models\CommunicationTemplate;
use Acl\Communications\Models\NotificationEvent;
use Acl\Communications\Services\NotificationTemplateResolver;
use App\Events\RequestCreated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommunicationServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('events', [
            'request.created.email' => [
                'label' => 'Request created email',
                'payload' => [
                    'request_number' => 'required|string',
                    'requester_name' => 'required|string',
                    'user_email' => 'required|email',
                ],
                'template' => '<p>Config request template</p>',
                'subject' => 'Nouvelle demande',
            ],
            'billing.payment-reminder.email' => [
                'label' => 'Payment reminder email',
                'payload' => [
                    'name' => 'required|string',
                    'due_date' => 'required|date',
                    'user_email' => 'required|email',
                ],
                'template' => '<p>Config payment template</p>',
                'subject' => 'Rappel de paiement',
            ],
        ]);

        config()->set('communications.events.catalog', [
            RequestCreated::class => [
                'event_key' => 'request.created.email',
                'name' => 'Request created email',
            ],
        ]);
    }

    public function test_package_contracts_are_resolvable(): void
    {
        $this->assertInstanceOf(NotificationManagerInterface::class, app(NotificationManagerInterface::class));
        $this->assertInstanceOf(CommunicationServiceInterface::class, app(CommunicationServiceInterface::class));
        $this->assertInstanceOf(ApplicationEventConsumerInterface::class, app(ApplicationEventConsumerInterface::class));
        $this->assertInstanceOf(NotificationTemplateResolver::class, app(NotificationTemplateResolver::class));
        $this->assertInstanceOf(NotificationListener::class, app(NotificationListener::class));
        $this->assertInstanceOf(TemplateRendererInterface::class, app(TemplateRendererInterface::class));
    }

    public function test_notifications_sync_command_populates_the_runtime_event_catalog(): void
    {
        Artisan::call('notifications:sync');

        $this->assertDatabaseHas('notification_events', [
            'key' => 'request.created.email',
            'label' => 'Request created email',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('notification_events', [
            'key' => 'billing.payment-reminder.email',
            'label' => 'Payment reminder email',
            'is_active' => true,
        ]);
    }

    public function test_notification_manager_dispatches_an_email_event_from_the_database_runtime_catalog(): void
    {
        Event::fake([
            NotificationSent::class,
            CommunicationOrchestrated::class,
        ]);
        Mail::fake();

        $event = NotificationEvent::query()->create([
            'key' => 'billing.payment-reminder.email',
            'label' => 'Payment reminder email',
            'payload_schema' => [
                'name' => 'required|string',
                'due_date' => 'required|date',
                'user_email' => 'required|email',
            ],
            'is_active' => true,
        ]);

        $template = CommunicationTemplate::query()->create([
            'name' => 'Payment Reminder',
            'key' => 'payment-reminder',
            'event_key' => $event->key,
            'channel' => 'email',
            'subject' => 'Rappel de paiement',
            'content' => '<p>Bonjour {{ $name }}</p><p>Votre facture arrive a echeance le {{ $due_date }}.</p>',
            'active' => true,
        ]);

        $result = app(NotificationManagerInterface::class)->dispatch($event->key, [
            'name' => 'Alex',
            'due_date' => '2026-04-15',
            'user_email' => 'alex@example.test',
            'user_id' => '42',
        ]);

        $this->assertSame('sent', $result['status']);
        $this->assertSame('billing', $result['parsed']['module']);
        $this->assertSame('payment-reminder', $result['parsed']['action']);
        $this->assertSame('email', $result['parsed']['channel']);
        $this->assertSame($template->id, $result['template']['id']);
        $this->assertStringContainsString('Bonjour Alex', $result['rendered']);

        $this->assertDatabaseHas('communications', [
            'event_key' => $event->key,
            'notification_event_id' => $event->id,
            'template_id' => $template->id,
            'channel' => 'email',
            'status' => 'sent',
            'recipient_address' => 'alex@example.test',
        ]);

        Mail::assertSent(CommunicationMail::class, function (CommunicationMail $mail): bool {
            return $mail->hasTo('alex@example.test')
                && $mail->subjectLine === 'Rappel de paiement'
                && str_contains($mail->htmlContent, '2026-04-15');
        });

        Event::assertDispatched(NotificationSent::class);
        Event::assertDispatched(CommunicationOrchestrated::class);
    }

    public function test_notification_manager_publishes_email_to_the_configured_queue(): void
    {
        Queue::fake();
        Mail::fake();

        $event = NotificationEvent::query()->create([
            'key' => 'billing.payment-reminder.email',
            'label' => 'Payment reminder email',
            'payload_schema' => [
                'name' => 'required|string',
                'due_date' => 'required|date',
                'user_email' => 'required|email',
            ],
            'is_active' => true,
        ]);

        CommunicationTemplate::query()->create([
            'name' => 'Payment Reminder',
            'key' => 'payment-reminder',
            'event_key' => $event->key,
            'channel' => 'email',
            'subject' => 'Rappel de paiement',
            'content' => '<p>Bonjour {{ $name }}</p>',
            'active' => true,
        ]);

        $result = app(NotificationManagerInterface::class)->dispatch($event->key, [
            'name' => 'Alex',
            'due_date' => '2026-04-15',
            'user_email' => 'alex@example.test',
        ]);

        $this->assertSame('queued', $result['status']);
        $this->assertSame('queued', $result['response']['status']);
        $this->assertSame('notifications.email', $result['response']['queue']);

        $this->assertDatabaseHas('communications', [
            'event_key' => $event->key,
            'channel' => 'email',
            'status' => 'queued',
            'recipient_address' => 'alex@example.test',
            'attempts' => 0,
        ]);

        Queue::assertPushedOn('notifications.email', SendCommunicationJob::class);
        Mail::assertNothingSent();
    }

    public function test_notification_manager_validates_payload_against_the_database_schema(): void
    {
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

        CommunicationTemplate::query()->create([
            'name' => 'Request Created',
            'key' => 'request-created',
            'event_key' => 'request.created.email',
            'channel' => 'email',
            'subject' => 'Nouvelle demande',
            'content' => '<p>Bonjour {{ $requester_name }}</p>',
            'active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(NotificationManagerInterface::class)->dispatch('request.created.email', [
            'request_number' => 'REQ-001',
        ]);
    }

    public function test_template_resolver_prioritizes_runtime_override_then_database_then_config(): void
    {
        $resolver = app(NotificationTemplateResolver::class);

        $override = $resolver->resolve('request.created.email', [
            'template' => '<p>Override</p>',
            'subject' => 'Override subject',
        ]);

        $this->assertSame('options', $override['source']);
        $this->assertSame('<p>Override</p>', $override['template']);

        CommunicationTemplate::query()->create([
            'name' => 'Request Created',
            'key' => 'request-created',
            'event_key' => 'request.created.email',
            'tenant_id' => 12,
            'channel' => 'email',
            'subject' => 'DB subject',
            'content' => '<p>DB template</p>',
            'active' => true,
        ]);

        $database = $resolver->resolve('request.created.email', [], 12);

        $this->assertSame('database', $database['source']);
        $this->assertSame('DB subject', $database['subject']);

        $config = $resolver->resolve('request.created.email');

        $this->assertSame('config', $config['source']);
        $this->assertSame('Nouvelle demande', $config['subject']);
    }

    public function test_template_renderer_supports_dot_notation_for_array_payloads(): void
    {
        $rendered = app(TemplateRendererInterface::class)->render(
            'Bonjour {{ user.full_name }}, demande {{ request.reference }}.',
            [
                'user' => [
                    'full_name' => 'Alex',
                ],
                'request' => [
                    'reference' => 'REQ-001',
                ],
            ],
        );

        $this->assertSame('Bonjour Alex, demande REQ-001.', $rendered);
    }

    public function test_communication_service_can_trigger_the_notification_manager_from_an_application_event(): void
    {
        Mail::fake();

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

        CommunicationTemplate::query()->create([
            'name' => 'Request Created',
            'key' => 'request-created',
            'event_key' => 'request.created.email',
            'channel' => 'email',
            'subject' => 'Nouvelle demande',
            'content' => '<p>Bonjour {{ $requester_name }}, votre demande {{ $request_number }} est enregistree.</p>',
            'active' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Alex',
            'email' => 'alex@example.test',
            'password' => bcrypt('secret'),
        ]);

        $result = app(CommunicationServiceInterface::class)->trigger(
            new RequestCreated($user, 'REQ-2026-009'),
        );

        $this->assertSame('sent', $result['status']);
        $this->assertSame('request.created.email', $result['event_key']);

        $this->assertDatabaseHas('communications', [
            'event_key' => 'request.created.email',
            'channel' => 'email',
            'status' => 'sent',
            'recipient_address' => 'alex@example.test',
        ]);

        Mail::assertSent(CommunicationMail::class);
    }

    public function test_notification_manager_requires_a_three_segment_event_key(): void
    {
        NotificationEvent::query()->create([
            'key' => 'request.created',
            'label' => 'Bad event key',
            'payload_schema' => [],
            'is_active' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Notification event keys must use the format <module>.<action>.<channel>.');

        app(NotificationManagerInterface::class)->dispatch('request.created', []);
    }
}
