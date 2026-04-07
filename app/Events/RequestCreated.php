<?php

namespace App\Events;

use Acl\Communications\Contracts\CommunicationEventInterface;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequestCreated implements CommunicationEventInterface
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public User $user,
        public string $requestNumber,
        public array $data = [],
    ) {
    }

    public function communicationEventKey(): string
    {
        return 'request.created.email';
    }

    public function communicationRecipient(): array
    {
        return [
            'address' => $this->user->email,
            'type' => 'user',
            'id' => isset($this->user->id) ? (string) $this->user->id : null,
            'name' => $this->user->name,
        ];
    }

    public function communicationData(): array
    {
        return array_merge($this->data, [
            'request_number' => $this->requestNumber,
            'requester_name' => $this->user->name,
            'user_email' => $this->user->email,
            'user_id' => isset($this->user->id) ? (string) $this->user->id : null,
        ]);
    }
}
