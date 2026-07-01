<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Backs the App\Models\Option key/value store. The model predates this table
// (it had no migration); the GPU profile is its first persistent user.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->string('option_name')->unique();
            $table->longText('option_value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('options');
    }
};
