<?php

declare(strict_types=1);

use App\Enums\Status;
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
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('status')->index()->default(value: Status::ACTIVE->value);
            $table->string('verification_code')->index()->nullable();
            $table->timestamp('verification_code_expire_at')->index()->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->string('email_unique')
                ->storedAs('CASE WHEN deleted_at IS NULL THEN email ELSE NULL END')
                ->nullable();
            $table->unique('email_unique');
            $table->string('provider_unique')
                ->storedAs('CASE WHEN deleted_at IS NULL THEN provider ELSE NULL END')
                ->nullable();
            $table->string('provider_id_unique')
                ->storedAs('CASE WHEN deleted_at IS NULL THEN provider_id ELSE NULL END')
                ->nullable();
            $table->unique(['provider_unique', 'provider_id_unique']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }
};
