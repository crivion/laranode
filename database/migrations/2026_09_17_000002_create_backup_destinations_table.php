<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('driver');
            $table->text('config');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'driver']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_destinations');
    }
};
