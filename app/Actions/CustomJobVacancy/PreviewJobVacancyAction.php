<?php

declare(strict_types=1);

namespace App\Actions\CustomJobVacancy;

use App\Services\FetchJobPageService;
use App\Services\ParseJobVacancyService;

final readonly class PreviewJobVacancyAction
{
    public function __construct(
        private FetchJobPageService $fetchService,
        private ParseJobVacancyService $parseService,
    ) {}

    /**
     * Fetch a job posting URL and parse its contents without persisting anything.
     *
     * @return array<string, int|string|null>
     */
    public function handle(string $url): array
    {
        $jobText = $this->fetchService->fetch($url);

        return $this->parseService->parse($jobText);
    }
}
