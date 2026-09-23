<?php

declare(strict_types=1);

namespace App\Services;

use App\Traits\HasAiPrompt;
use App\Utilities\Constants;

final readonly class OptimizeResumeService
{
    use HasAiPrompt;

    public function __construct(private GeminiClient $client) {}

    public function optimize(string $resumeText, string $jobDescription): string
    {
        $prompt = $this->getPrompt($resumeText, $jobDescription, 'prompts.resume_optimization');

        $text = $this->client->requestText(Constants::SYSTEM_PROMPT_OPTIMIZE_RESUME, $prompt);

        return $this->sanitizeText($text);
    }

    private function sanitizeText(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        return mb_trim($text);
    }
}
