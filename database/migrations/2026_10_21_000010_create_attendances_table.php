<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date')->index();
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('status', 20)->default('present')->index(); // present, late, absent, on_leave
            $table->text('notes')->nullable();
            $table->timestamps();

            // Prevent duplicate attendance per employee per date
            $table->unique(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
