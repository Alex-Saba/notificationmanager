<?php

use App\Events\RequestCreated;
use App\Models\User;
use Acl\Communications\Contracts\NotificationManagerInterface;
use Acl\Communications\Models\Communication;
use Acl\Communications\Models\CommunicationTemplate;
use Acl\Communications\Models\NotificationEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('communications:test-send {--email=alex@example.test} {--name=Alex} {--request=REQ-2026-001}', function () {
    $email = (string) $this->option('email');
    $name = (string) $this->option('name');
    $requestNumber = (string) $this->option('request');

    $user = User::query()->firstOrCreate(
        ['email' => $email],
        [
            'name' => $name !== '' ? $name : 'Alex',
            'password' => bcrypt(Str::random(24)),
        ],
    );

    $template = CommunicationTemplate::query()->firstOrCreate(
        ['key' => 'request-created'],
        [
            'name' => 'Request Created',
            'event_key' => 'request.created.email',
            'channel' => 'mail',
            'subject' => 'Nouvelle demande',
            'content' => '<p>Bonjour {{ $requester_name }}, votre demande {{ $request_number }} est enregistree.</p>',
            'active' => true,
        ],
    );

    NotificationEvent::query()->updateOrCreate(
        ['key' => 'request.created.email'],
        [
            'label' => 'Request created email',
            'payload_schema' => [
                'request_number' => 'required|string',
                'requester_name' => 'required|string',
                'user_email' => 'required|email',
            ],
            'is_active' => true,
        ],
    );

    Event::dispatch(new RequestCreated($user, $requestNumber));

    $communication = Communication::query()
        ->where('event_key', 'request.created.email')
        ->where('recipient_address', $email)
        ->latest('id')
        ->first();

    if (! $communication) {
        $this->error('Aucune communication n a ete enregistree.');

        return self::FAILURE;
    }

    $this->info('Communication de test executee.');
    $this->line('Email: '.$email);
    $this->line('Request: '.$requestNumber);
    $this->line('Status: '.$communication->status);
    $this->line('Channel: '.$communication->channel);

    if ($communication->error_message) {
        $this->warn('Erreur: '.$communication->error_message);
    }

    return $communication->status === 'sent' ? self::SUCCESS : self::FAILURE;
})->purpose('Prepare a test communication through the package');

Artisan::command('communications:simulate-payment-reminder {templateKey=payment-reminder} {--email=alex@example.test} {--name=Alex} {--due=2026-03-31}', function (string $templateKey) {
    $email = (string) $this->option('email');
    $name = (string) $this->option('name');
    $dueDate = (string) $this->option('due');

    $template = CommunicationTemplate::query()->where('key', $templateKey)->first();

    if (! $template) {
        $this->error("Aucun template existant avec la cle [{$templateKey}] n a ete trouve.");

        return self::FAILURE;
    }

    $user = User::query()->firstOrCreate(
        ['email' => $email],
        [
            'name' => $name !== '' ? $name : 'Alex',
            'password' => bcrypt(Str::random(24)),
        ],
    );

    $template->forceFill([
        'event_key' => 'billing.payment-reminder.email',
    ])->save();

    NotificationEvent::query()->updateOrCreate(
        ['key' => 'billing.payment-reminder.email'],
        [
            'label' => 'Payment reminder email',
            'payload_schema' => [
                'name' => 'required|string',
                'due_date' => 'required|date',
                'user_email' => 'required|email',
            ],
            'is_active' => true,
        ],
    );

    $result = app(NotificationManagerInterface::class)->dispatch('billing.payment-reminder.email', [
        'name' => $name !== '' ? $name : $user->name,
        'due_date' => $dueDate,
        'user_email' => $email,
        'user_id' => (string) $user->id,
    ]);

    $communication = Communication::query()
        ->where('event_key', 'billing.payment-reminder.email')
        ->where('recipient_address', $email)
        ->latest('id')
        ->first();

    if (! $communication) {
        $this->error('Aucune communication payment reminder n a ete enregistree.');

        return self::FAILURE;
    }

    $this->info('Simulation payment reminder executee.');
    $this->line('Template: '.$template->key);
    $this->line('Email: '.$email);
    $this->line('Due date: '.$dueDate);
    $this->line('Status: '.$communication->status);
    $this->line('Channel: '.$communication->channel);
    $this->line('Event id: '.($result['event_id'] ?? 'n/a'));

    if ($communication->error_message) {
        $this->warn('Erreur: '.$communication->error_message);
    }

    return $communication->status === 'sent' ? self::SUCCESS : self::FAILURE;
})->purpose('Attach an existing template to billing.payment-reminder.email and send a simulation');
