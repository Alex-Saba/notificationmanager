<?php

namespace Acl\Communications\Contracts;

interface CommunicationEventInterface
{
    public function communicationEventKey(): string;

    /**
     * @return array<string, mixed>|string
     */
    public function communicationRecipient(): array|string;

    /**
     * @return array<string, mixed>
     */
    public function communicationData(): array;
}
