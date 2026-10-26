<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('crew_type');
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->renameColumn('crew_type', 'job_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('crew_type')->nullable();
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->renameColumn('job_type', 'crew_type');
        });
    }
};