<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_job_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignUuid('custom_job_vacancy_id')
                ->constrained('custom_job_vacancies')
                ->cascadeOnDelete();
            $table->integer('compatibility_score')->nullable();
            $table->json('feedback')->nullable();
            $table->json('improvement_suggestions')->nullable();
            $table->longText('cover_letter')->nullable();
            $table->longText('optimized_resume')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
