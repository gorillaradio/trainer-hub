<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->integer('monthly_fee_override')->nullable()->after('enrolled_at');
            $table->date('current_cycle_started_at')->nullable()->after('monthly_fee_override');
            $table->json('past_cycles')->nullable()->after('current_cycle_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['monthly_fee_override', 'current_cycle_started_at', 'past_cycles']);
        });
    }
};
