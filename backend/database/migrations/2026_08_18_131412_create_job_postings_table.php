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
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->foreignId('recruiter_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('crew_type')->default('normal');
            $table->unsignedInteger('number_of_positions');
            $table->decimal('pay_rate', 10, 2)->nullable();
            $table->string('status')->default('open');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
