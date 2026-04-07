<?php

namespace Acl\Communications\Contracts;

interface CommunicationServiceInterface
{
    /**
     * Entree principale recommandee : un event applicatif expose au package.
     * Cet event est d'abord normalise via le catalogue d'evenements applicatifs.
     *
     * @return array<string, mixed>
     */
    public function trigger(CommunicationEventInterface $event): array;
}
