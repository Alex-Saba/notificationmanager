<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id')->nullable()->index();
            $table->string('event_key')->nullable()->index();
            $table->foreignId('template_id')->nullable()->constrained('communication_templates')->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('communication_rules')->nullOnDelete();
            $table->string('channel');
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('priority')->default(100);
            $table->string('recipient_type')->nullable();
            $table->string('recipient_id')->nullable();
            $table->string('recipient_address')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('idempotency_key')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->longText('rendered_content')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
