<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('communication_templates')->cascadeOnDelete();
            $table->string('event_key')->index();
            $table->json('channels');
            $table->unsignedInteger('priority')->default(100);
            $table->json('fallback')->nullable();
            $table->unsignedInteger('delay')->default(0);
            $table->boolean('active')->default(true);
            $table->json('conditions')->nullable();
            $table->timestamps();

            $table->unique('template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_rules');
    }
};
