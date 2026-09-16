<?php

declare(strict_types=1);

namespace App\Services;

use App\Traits\HasAiPrompt;

final readonly class OptimizeResumeService
{
    use HasAiPrompt;

    private const string SYSTEM_PROMPT = 'You are an expert resume strategist and ATS optimization specialist.';

    public function __construct(private GeminiClient $client) {}

    public function optimize(string $resumeText, string $jobDescription): string
    {
        $prompt = $this->getPrompt($resumeText, $jobDescription, 'prompts.resume_optimization');

        $text = $this->client->requestText(self::SYSTEM_PROMPT, $prompt);

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
