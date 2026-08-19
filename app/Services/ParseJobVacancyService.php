<?php

declare(strict_types=1);

namespace App\Services;

use App\Traits\HasAiPrompt;
use RuntimeException;

final readonly class ParseJobVacancyService
{
    use HasAiPrompt;

    private const string SYSTEM_PROMPT = 'You are a job vacancy parser.';

    public function __construct(private GroqClient $client) {}

    /**
     * @return array<string, int|string|null>
     */
    public function parse(string $jobText): array
    {
        $prompt = $this->getPrompt($jobText, 'prompts.parse_job_vacancy');

        /** @var array<mixed, mixed> $data */
        $data = $this->client->requestJson(self::SYSTEM_PROMPT, $prompt);

        $result = [
            'title' => $this->stringOrNull($data, 'title'),
            'company' => $this->stringOrNull($data, 'company'),
            'description' => $this->stringOrNull($data, 'description'),
            'location' => $this->stringOrNull($data, 'location'),
            'employment_type' => $this->normalizeEmploymentType($this->stringOrNull($data, 'employment_type')),
            'responsibilities' => $this->stringOrNull($data, 'responsibilities'),
            'requirements' => $this->stringOrNull($data, 'requirements'),
            'skills_required' => $this->stringOrNull($data, 'skills_required'),
            'experience_years_min' => $this->intOrNull($data, 'experience_years_min'),
            'experience_years_max' => $this->intOrNull($data, 'experience_years_max'),
            'expected_salary' => $this->normalizeSalary($this->stringOrNull($data, 'expected_salary')),
            'category' => $this->stringOrNull($data, 'category'),
        ];

        throw_if(
            array_filter($result, fn (int|string|null $value): bool => $value !== null) === [],
            RuntimeException::class,
            "Couldn't extract job details from this page."
        );

        return $result;
    }

    private function getPrompt(string $jobText, string $configKey): string
    {
        /** @var string $template */
        $template = config($configKey);

        return str_replace('{job_text}', $jobText, $template);
    }

    /**
     * @param  array<mixed, mixed>  $data
     */
    private function stringOrNull(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<mixed, mixed>  $data
     */
    private function intOrNull(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) && $value >= 0 ? $value : null;
    }

    private function normalizeEmploymentType(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower(mb_trim($value));

        return match (true) {
            str_contains($normalized, 'full') => 'full-time',
            str_contains($normalized, 'part') => 'part-time',
            default => null,
        };
    }

    private function normalizeSalary(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = str_replace(['$', ','], '', mb_trim($value));

        return is_numeric($cleaned) ? $cleaned : null;
    }
}
