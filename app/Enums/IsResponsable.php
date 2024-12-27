<?php

namespace App\Enums;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

/**
 * @mixin Responsable
 */
trait IsResponsable
{
    use IsArrayable;

    /**
     * @implements Responsable::toResponse()
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse($this->toArray());
    }
}
