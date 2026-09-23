<?php

namespace App\Jobs\Concerns;

use Illuminate\Container\Container;

/**
 * Gives a job's failure handling the same method injection as handle().
 *
 * Laravel calls a job's failed() method directly, without the container, so failed() can't declare dependencies
 * as parameters. This trait's failed() calls the job's handleFailure() through the container instead. A job that
 * uses it defines handleFailure(?\Throwable $e, ...dependencies) in place of failed().
 */
trait InjectsFailureDependencies
{
    public function failed(?\Throwable $e): void
    {
        Container::getInstance()->call([$this, 'handleFailure'], ['e' => $e]);
    }
}
