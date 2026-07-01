<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('schedule', 100);
            $table->string('command', 500);
            $table->string('label', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'active']);
            $table->unique(['user_id', 'schedule', 'command']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_jobs');
    }
};
