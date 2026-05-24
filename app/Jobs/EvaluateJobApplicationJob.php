<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CustomJobApplication;
use App\Models\CustomJobVacancy;
use App\Models\Resume;
use App\Services\EvaluateResumeWithAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class EvaluateJobApplicationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private readonly CustomJobApplication $application) {}

    public function handle(EvaluateResumeWithAIService $aiEvaluator): void
    {
        /** @var CustomJobApplication $application */
        $application = $this->application->load(['user.resume', 'customJobVacancy']);

        /** @var Resume $resume */
        $resume = $application->user->resume;
        /** @var CustomJobVacancy $job */
        $job = $application->customJobVacancy;

        if (empty($resume->extracted_text)) {
            throw new RuntimeException(sprintf('Resume [%s] has no extracted text to evaluate.', $resume->id));
        }

        $evaluation = $aiEvaluator->evaluate(
            (string) $resume->extracted_text,
            (string) $job->description
        );

        $application->update([
            'compatibility_score' => $evaluation['score'],
            'feedback' => $evaluation['feedback'],
            'improvement_suggestions' => $evaluation['suggestions'],
            'applied_at' => now(),
            'reviewed_at' => now(),
        ]);

        Cache::forget('user:application:show:' . $application->id);
        Cache::increment('applications:gen:' . $application->user_id);
    }
}
