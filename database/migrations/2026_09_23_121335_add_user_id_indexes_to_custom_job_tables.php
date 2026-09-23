<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_job_applications', function (Blueprint $table) {
            $table->index('user_id');
            $table->index(['user_id', 'compatibility_score']);
        });

        Schema::table('custom_job_vacancies', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('custom_job_applications', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['user_id', 'compatibility_score']);
        });

        Schema::table('custom_job_vacancies', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
