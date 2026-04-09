<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Soft-delete all inactive students (they are archived)
        // Must happen before making the column nullable, while NOT NULL is still enforced
        DB::table('students')
            ->whereNull('deleted_at')
            ->where('status', 'inactive')
            ->update(['deleted_at' => now()]);

        // Step 2: Make the column nullable with default null
        // Must happen before nulling out status values, as the column is currently NOT NULL
        // Disable FK checks to avoid Doctrine DBAL triggering FK violation during column change
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::table('students', function (Blueprint $table) {
            $table->string('status')->nullable()->default(null)->change();
        });
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Step 3: Null out all remaining non-suspended statuses (active → null)
        // Disable FK checks in case dev DB has orphaned tenant rows from old test data
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('students')
            ->whereNull('deleted_at')
            ->where('status', '!=', 'suspended')
            ->update(['status' => null]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // Step 1: Restore null status to 'active'
        DB::table('students')
            ->whereNull('status')
            ->update(['status' => 'active']);

        // Step 2: Restore column to non-nullable with default 'active'
        Schema::table('students', function (Blueprint $table) {
            $table->string('status')->nullable(false)->default('active')->change();
        });
    }
};
