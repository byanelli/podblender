<?php

namespace App\Cache;

use App\Enums\Platform;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Cache\Repository;

class Metadata
{
    public function __construct(private readonly Repository $repository) {}

    private function key(Platform $platform, string $id): string {
        return "$platform->value:$id";
    }

    public function put(Platform $platform, string $id, object $metadata): void {
        $this->repository->put($this->key($platform, $id), $metadata, CarbonInterval::day());
    }

    public function get(Platform $platform, string $id): ?object {
        return $this->repository->get($this->key($platform, $id));
    }
}
