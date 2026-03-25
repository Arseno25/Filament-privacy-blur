<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_reveal_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('tenant_id')->nullable()->index();
            $table->string('panel_id')->nullable();
            $table->string('resource')->nullable();
            $table->string('page')->nullable();
            $table->string('column_name');
            $table->string('record_key')->nullable();
            $table->string('reveal_mode');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['column_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_reveal_logs');
    }
};
