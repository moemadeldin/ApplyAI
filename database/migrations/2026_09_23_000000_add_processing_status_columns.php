<?php

declare(strict_types=1);

use App\Enums\ProcessingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_job_vacancies', function (Blueprint $table): void {
            $table->string('status')
                ->default(ProcessingStatus::PENDING->value)
                ->index()
                ->after('job_url');
            $table->string('current_step')->nullable()->after('status');
            $table->text('error_message')->nullable()->after('current_step');
        });

        Schema::table('custom_job_applications', function (Blueprint $table): void {
            $table->string('status')
                ->default(ProcessingStatus::PENDING->value)
                ->index()
                ->after('custom_job_vacancy_id');
            $table->string('current_step')->nullable()->after('status');
            $table->text('error_message')->nullable()->after('current_step');
        });
    }

    public function down(): void
    {
        Schema::table('custom_job_vacancies', function (Blueprint $table): void {
            $table->dropColumn(['status', 'current_step', 'error_message']);
        });

        Schema::table('custom_job_applications', function (Blueprint $table): void {
            $table->dropColumn(['status', 'current_step', 'error_message']);
        });
    }
};
