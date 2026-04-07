<?php

namespace Acl\Communications\Contracts;

interface CommunicationReactionPolicyInterface
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(object $event): array;
}
