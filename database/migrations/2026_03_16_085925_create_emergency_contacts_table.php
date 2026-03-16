<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('slug')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 50);
            $table->timestamps();

            $table->index(['tenant_id', 'student_id']);
        });

        // Migrate existing emergency contact data
        $students = DB::table('students')
            ->whereNotNull('emergency_contact_name')
            ->orWhereNotNull('emergency_contact_phone')
            ->get(['id', 'tenant_id', 'emergency_contact_name', 'emergency_contact_phone']);

        foreach ($students as $student) {
            if ($student->emergency_contact_name || $student->emergency_contact_phone) {
                DB::table('emergency_contacts')->insert([
                    'id' => Str::uuid()->toString(),
                    'tenant_id' => $student->tenant_id,
                    'student_id' => $student->id,
                    'name' => $student->emergency_contact_name ?? '',
                    'phone' => $student->emergency_contact_phone ?? '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('students', function (Blueprint $table) {
            $table->foreignUuid('phone_contact_id')->nullable()->constrained('emergency_contacts')->nullOnDelete();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['emergency_contact_name', 'emergency_contact_phone']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
        });

        // Migrate data back: take the first emergency contact per student
        $contacts = DB::table('emergency_contacts')
            ->select('student_id', 'name', 'phone')
            ->orderBy('created_at')
            ->get()
            ->unique('student_id');

        foreach ($contacts as $contact) {
            DB::table('students')
                ->where('id', $contact->student_id)
                ->update([
                    'emergency_contact_name' => $contact->name,
                    'emergency_contact_phone' => $contact->phone,
                ]);
        }

        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('phone_contact_id');
        });

        Schema::dropIfExists('emergency_contacts');
    }
};
