<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->text('s3_key')->nullable()->after('disk_name');
            $table->text('s3_secret')->nullable()->after('s3_key');
            $table->string('s3_region')->nullable()->after('s3_secret');
            $table->string('s3_bucket')->nullable()->after('s3_region');
            $table->string('s3_endpoint')->nullable()->after('s3_bucket');
        });
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn(['s3_key', 's3_secret', 's3_region', 's3_bucket', 's3_endpoint']);
        });
    }
};
