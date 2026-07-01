<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('databases', function (Blueprint $table) {
            $table->string('engine')->nullable()->after('collation');
            $table->string('charset')->nullable()->change();
            $table->string('collation')->nullable()->change();
        });

        // Backfill: rows with NULL or '' engine are MySQL (pre-existing rows)
        DB::table('databases')
            ->where(fn ($q) => $q->whereNull('engine')->orWhere('engine', ''))
            ->update(['engine' => 'mysql']);
    }

    public function down(): void
    {
        Schema::table('databases', function (Blueprint $table) {
            $table->dropColumn('engine');
            $table->string('charset')->default('utf8mb4')->change();
            $table->string('collation')->default('utf8mb4_unicode_ci')->change();
        });
    }
};
