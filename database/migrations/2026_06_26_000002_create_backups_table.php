<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('operation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');                         // db | files
            $table->string('target');                       // db name or website URL
            $table->string('storage');                      // local | s3
            $table->string('disk_name')->nullable();        // backups | backups_s3 etc.
            $table->string('path')->nullable();             // relative path on disk
            $table->bigInteger('size_bytes')->nullable();
            $table->string('status')->default('pending');   // pending | completed | failed
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
