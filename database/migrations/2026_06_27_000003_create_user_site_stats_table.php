<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_site_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('snapshotted_at');
            $table->unsignedBigInteger('disk_bytes');
            $table->timestamps();

            $table->index(['website_id', 'snapshotted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_site_stats');
    }
};
