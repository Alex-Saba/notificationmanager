<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_templates', function (Blueprint $table) {
            $table->string('event_key')->nullable()->after('key')->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->after('event_key')->index();
        });
    }

    public function down(): void
    {
        Schema::table('communication_templates', function (Blueprint $table) {
            $table->dropColumn(['event_key', 'tenant_id']);
        });
    }
};
