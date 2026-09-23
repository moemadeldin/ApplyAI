<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CustomJobVacancy\CreateCustomJobVacancyAction;
use App\Enums\ProcessingStatus;
use App\Models\CustomJobApplication;
use App\Models\CustomJobVacancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Backoff(30)]
#[Tries(3)]
final class ProcessCustomJobVacancyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly CustomJobVacancy $vacancy,
        private readonly CustomJobApplication $application,
    ) {}

    public function handle(CreateCustomJobVacancyAction $action): void
    {
        $vacancy = $this->vacancy;
        $application = $this->application;

        $vacancy->update([
            'status' => ProcessingStatus::PROCESSING->value,
            'current_step' => 'parse',
            'error_message' => null,
        ]);
        $application->update([
            'status' => ProcessingStatus::PROCESSING->value,
            'error_message' => null,
        ]);

        try {
            $action->handle($vacancy, $vacancy->user);

            $vacancy->refresh();

            $vacancy->update([
                'status' => ProcessingStatus::COMPLETED->value,
                'current_step' => 'completed',
            ]);
            $application->update([
                'status' => ProcessingStatus::COMPLETED->value,
                'current_step' => 'completed',
            ]);

            Cache::increment('vacancies:gen:'.$vacancy->user_id);
            Cache::increment('applications:gen:'.$vacancy->user_id);
        } catch (Throwable $exception) {
            $vacancy->update([
                'status' => ProcessingStatus::FAILED->value,
                'current_step' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
            $application->update([
                'status' => ProcessingStatus::FAILED->value,
                'current_step' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
