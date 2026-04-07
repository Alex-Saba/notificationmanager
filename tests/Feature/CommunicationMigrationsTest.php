<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommunicationMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_communication_tables_are_available(): void
    {
        $this->assertTrue(Schema::hasTable('communication_templates'));
        $this->assertTrue(Schema::hasTable('communication_rules'));
        $this->assertTrue(Schema::hasTable('communications'));
    }

    public function test_communication_rules_table_contains_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('communication_rules', [
            'template_id',
            'event_key',
            'channels',
            'priority',
            'fallback',
            'delay',
            'active',
        ]));
    }

    public function test_communications_table_contains_tracking_and_audit_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('communications', [
            'channel',
            'status',
            'attempts',
            'correlation_id',
            'idempotency_key',
            'payload',
            'rendered_content',
            'meta',
            'sent_at',
            'failed_at',
            'read_at',
        ]));
    }
}
