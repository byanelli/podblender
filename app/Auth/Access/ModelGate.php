<?php

namespace App\Auth\Access;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for a custom application gate using the default model actions.
 *
 * cf. https://laravel.com/docs/11.x/authorization#policy-methods
 */
readonly abstract class ModelGate
{
    public function __construct(private Gate $gate) {}

    public function checkView(Model $model): bool {
        return $this->gate->check('view', $model);
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeView(Model $model): Response {
        return $this->gate->authorize('view', $model);
    }

    public function checkViewAny(Model $model): bool {
        return $this->gate->check('viewAny', $model);
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeViewAny(Model $model): Response {
        return $this->gate->authorize('viewAny', $model);
    }

    public function checkCreate(Model $model): bool {
        return $this->gate->check('create', $model);
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeCreate(Model $model): Response {
        return $this->gate->authorize('create', $model);
    }

    public function checkUpdate(Model $model): bool {
        return $this->gate->check('update', $model);
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeUpdate(Model $model): Response {
        return $this->gate->authorize('update', $model);
    }

    public function checkDelete(Model $model): bool {
        return $this->gate->check('delete', $model);
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeDelete(Model $model): Response {
        return $this->gate->authorize('delete', $model);
    }

    public function checkRestore(Model $model): bool {
        return $this->gate->check('restore', $model);
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeRestore(Model $model): Response {
        return $this->gate->authorize('restore', $model);
    }

    public function checkForceDelete(Model $model): bool {
        return $this->gate->check('forceDelete', $model);
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeForceDelete(Model $model): Response {
        return $this->gate->authorize('forceDelete', $model);
    }
}
