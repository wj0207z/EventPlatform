<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->after('client_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->string('name')->after('company_id');

            $table->text('description')
                ->nullable()
                ->after('name');

            $table->date('event_date')->after('description');

            $table->string('location')->after('event_date');

            $table->string('status')
                ->default('planning')
                ->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['company_id']);

            $table->dropColumn([
                'company_id',
                'name',
                'description',
                'event_date',
                'location',
                'status',
            ]);
        });
    }
};