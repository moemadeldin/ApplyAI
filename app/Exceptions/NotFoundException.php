<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;

final class NotFoundException extends Exception
{
    public function __construct(string $resource)
    {
        parent::__construct($resource.' not found.', Response::HTTP_NOT_FOUND);
    }
}
