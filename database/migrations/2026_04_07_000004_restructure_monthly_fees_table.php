<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::drop('monthly_fees');

        Schema::create('monthly_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('slug')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('period');
            $table->integer('expected_amount');
            $table->date('due_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'student_id', 'period']);
            $table->index(['tenant_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::drop('monthly_fees');

        Schema::create('monthly_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('slug')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->integer('amount');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('period');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'student_id', 'period']);
            $table->index(['tenant_id', 'due_date', 'paid_at']);
        });
    }
};
