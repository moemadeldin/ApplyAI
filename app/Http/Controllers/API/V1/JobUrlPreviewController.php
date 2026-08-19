<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Actions\CustomJobVacancy\PreviewJobVacancyAction;
use App\Http\Requests\PreviewJobUrlRequest;
use App\Traits\APIResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

final readonly class JobUrlPreviewController
{
    use APIResponses;

    public function __construct(private PreviewJobVacancyAction $action) {}

    public function __invoke(PreviewJobUrlRequest $request): JsonResponse
    {
        /** @var string $url */
        $url = $request->validated('job_url');

        try {
            $parsed = $this->action->handle($url);
        } catch (RuntimeException $runtimeException) {
            return $this->fail($runtimeException->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->success(['vacancy' => $parsed], 'Job vacancy parsed successfully.');
    }
}
