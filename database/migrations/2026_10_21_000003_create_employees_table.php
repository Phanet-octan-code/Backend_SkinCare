<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('position_id')->constrained('positions')->restrictOnDelete();
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->date('start_date');
            $table->string('status', 20)->default('active')->index(); // active, inactive, terminated
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
