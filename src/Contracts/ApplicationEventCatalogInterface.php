<?php

namespace Acl\Communications\Contracts;

interface ApplicationEventCatalogInterface
{
    /**
     * @return array<string, mixed>
     */
    public function lookup(CommunicationEventInterface $event): array;
}
