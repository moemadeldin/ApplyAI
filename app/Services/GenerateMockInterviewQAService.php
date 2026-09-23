<?php

declare(strict_types=1);

namespace App\Services;

use App\Traits\HasAiPrompt;
use App\Utilities\Constants;

final readonly class GenerateMockInterviewQAService
{
    use HasAiPrompt;

    public function __construct(private GeminiClient $client) {}

    /**
     * @return list<array{question: string, answer: string}>
     */
    public function generate(string $resumeText, string $jobDescription): array
    {
        $prompt = $this->getPrompt(
            $resumeText,
            $jobDescription,
            'prompts.mock_interview'
        );

        /** @var array<mixed, mixed> $response */
        $response = $this->client->requestJson(Constants::SYSTEM_PROMPT_MOCK_INTERVIEW, $prompt);

        /** @var list<mixed> $qaList */
        $qaList = $response['qa'] ?? [];

        $filtered = array_filter($qaList, static fn (mixed $item): bool => is_array($item) && isset($item['question'], $item['answer']));

        /** @var list<array{question: string, answer: string}> */
        return array_values($filtered);
    }
}
