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
        Schema::create('sub_calendar_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_calendar_id')->constrained()->cascadeOnDelete();
            $table->foreignId('access_key_id')->constrained()->cascadeOnDelete();
            $table->enum('access_type', ['read_only', 'modify'])->default('read_only');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_calendar_permissions');
    }
};
