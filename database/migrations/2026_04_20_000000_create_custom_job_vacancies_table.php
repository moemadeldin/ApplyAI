<?php

declare(strict_types=1);

use App\Enums\EmploymentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_job_vacancies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->string('company')->nullable();
            $table->string('title')->nullable();
            $table->longText('description')->nullable();
            $table->longText('job_text')->nullable();
            $table->string('job_url')->nullable();
            $table->text('responsibilities')->nullable();
            $table->text('requirements')->nullable();
            $table->text('skills_required')->nullable();
            $table->integer('experience_years_min')->nullable();
            $table->integer('experience_years_max')->nullable();
            $table->text('nice_to_have')->nullable();
            $table->string('location')
                ->index()
                ->nullable();
            $table->decimal('expected_salary', 10, 2)->nullable();
            $table->string('employment_type')
                ->index()
                ->default(EmploymentType::FULL_TIME->value);
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
