<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->boolean('ssl_enabled')->default(false);
            $table->string('ssl_status')->default('inactive'); // inactive, active, expired, pending
            $table->timestamp('ssl_expires_at')->nullable();
            $table->timestamp('ssl_generated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn(['ssl_enabled', 'ssl_status', 'ssl_expires_at', 'ssl_generated_at']);
        });
    }
};
