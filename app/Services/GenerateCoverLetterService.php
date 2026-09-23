<?php

declare(strict_types=1);

namespace App\Services;

use App\Traits\HasAiPrompt;
use App\Utilities\Constants;

final readonly class GenerateCoverLetterService
{
    use HasAiPrompt;

    public function __construct(private GeminiClient $client) {}

    public function generate(string $resumeText, string $jobDescription): string
    {
        $prompt = $this->getPrompt($resumeText, $jobDescription, 'prompts.cover_letter');

        $text = $this->client->requestText(Constants::SYSTEM_PROMPT_COVER_LETTER, $prompt);

        return $this->sanitizeText($text);
    }

    private function sanitizeText(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $text = preg_replace('/(\r\n|\r|\n)+/', ' ', $text) ?? $text;
        $text = preg_replace('/\\\\n+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return mb_trim($text);
    }
}
