<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasColumn('job_postings', 'crew_type') &&
            ! Schema::hasColumn('job_postings', 'job_type')
        ) {
            Schema::table('job_postings', function (Blueprint $table) {
                $table->renameColumn('crew_type', 'job_type');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn('job_postings', 'job_type') &&
            ! Schema::hasColumn('job_postings', 'crew_type')
        ) {
            Schema::table('job_postings', function (Blueprint $table) {
                $table->renameColumn('job_type', 'crew_type');
            });
        }
    }
};