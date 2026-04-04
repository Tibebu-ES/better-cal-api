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
        //shared_type: all_subcalendars|selected_subcalendars
        //role : If the attribute share_type is set to all_subcalendars, then this attribute defines the common permission the access key has for all sub-calendars.
        Schema::table('access_keys', function (Blueprint $table) {
            $table->string('shared_type')->default('all_sub_calendars')->after('password');
            $table->string('role')->default('read_only')->after('shared_type');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('access_keys', function (Blueprint $table) {
            $table->dropColumn('shared_type');
            $table->dropColumn('role');
        });
    }
};
