<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type');                                          // db | files
            $table->string('target');                                        // db name or website URL
            $table->string('storage');                                       // local | s3
            $table->string('disk_name')->nullable();
            $table->string('cron_expression')->default('0 2 * * *');
            $table->unsignedSmallInteger('retention_count')->default(7);
            $table->text('s3_key')->nullable();
            $table->text('s3_secret')->nullable();
            $table->string('s3_region')->nullable();
            $table->string('s3_bucket')->nullable();
            $table->string('s3_endpoint')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_backups');
    }
};
