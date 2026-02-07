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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_calendar_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('all_day')->default(false);
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->string('rrule')->nullable();
            $table->string('about')->nullable();
            $table->string('where')->nullable();
            $table->string('who')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
