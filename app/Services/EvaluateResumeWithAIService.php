<?php

declare(strict_types=1);

namespace App\Services;

use App\Traits\HasAiPrompt;

final readonly class EvaluateResumeWithAIService
{
    use HasAiPrompt;

    private const string SYSTEM_PROMPT = 'You are a structured evaluator. Always return only valid JSON.';

    public function __construct(private GeminiClient $client) {}

    /**
     * @return array{score: int, feedback: array{strengths: list<string>, weaknesses: list<string>}, suggestions: string}
     */
    public function evaluate(string $resumeText, string $jobDescription): array
    {
        $prompt = $this->getPrompt($resumeText, $jobDescription, 'prompts.evaluation');

        /** @var array<mixed, mixed> $data */
        $data = $this->client->requestJson(self::SYSTEM_PROMPT, $prompt);

        /** @var array{strengths: list<string>, weaknesses: list<string>} $feedback */
        $feedback = $data['feedback'] ?? ['strengths' => [], 'weaknesses' => []];

        $rawScore = $data['score'] ?? 0;
        $score = is_int($rawScore) ? $rawScore : 0;

        /** @var string $suggestions */
        $suggestions = $data['suggestions'] ?? 'No suggestions available.';

        return [
            'score' => $score,
            'feedback' => $feedback,
            'suggestions' => $suggestions,
        ];
    }
}
