<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_resource_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('snapshotted_at');
            $table->unsignedBigInteger('disk_bytes');
            $table->unsignedBigInteger('apache_request_count')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'snapshotted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_resource_snapshots');
    }
};
