<?php

return [
    'request.created.email' => [
        'label' => 'Request created email',
        'payload' => [
            'request_number' => 'required|string',
            'requester_name' => 'required|string',
            'user_email' => 'required|email',
        ],
        'template' => '<p>Bonjour {{ $requester_name }}, votre demande {{ $request_number }} est enregistree.</p>',
        'subject' => 'Nouvelle demande',
    ],
    'billing.payment-reminder.email' => [
        'label' => 'Payment reminder email',
        'payload' => [
            'name' => 'required|string',
            'due_date' => 'required|date',
            'user_email' => 'required|email',
        ],
        'template' => '<p>Bonjour {{ $name }}</p><p>Votre facture arrive a echeance le {{ $due_date }}.</p>',
        'subject' => 'Rappel de paiement',
    ],
    'billing.reminder.in_app' => [
        'label' => 'Billing reminder in app',
        'payload' => [
            'title' => 'required|string',
            'message' => 'required|string',
            'user_id' => 'nullable',
        ],
    ],
];
