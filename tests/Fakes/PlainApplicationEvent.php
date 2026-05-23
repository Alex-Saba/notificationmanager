<?php

namespace Tests\Fakes;

class PlainApplicationEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(public array $payload)
    {
    }
}
