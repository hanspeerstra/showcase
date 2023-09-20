<?php

declare(strict_types=1);

namespace App\Utils\Storage;

use App\Exceptions\Eloquent\CouldNotDeleteModelException;
use App\Exceptions\Eloquent\CouldNotSaveModelException;
use App\Exceptions\Eloquent\CouldNotUpdateModelException;
use App\Exceptions\Eloquent\ModelSaveIntegrityViolationException;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;

/**
 * Trait for Eloquent repositories offering reusable functionality
 */
trait EloquentRepositoryHelperTrait
{
    protected static function doGetByIdList(string $modelClass, array $ids): array
    {
        $found = call_user_func([$modelClass, 'findMany'], $ids)->all();
        $foundIds = array_map(static function (Model $entry) {
            return $entry->getKey();
        }, $found);
        $missingIds = array_diff($ids, $foundIds);
        if (count($missingIds) > 0) {
            throw new ModelNotFoundException(sprintf(
                'Could not find models of type %s with ids %s',
                $modelClass,
                implode(', ', $missingIds)
            ));
        }
        return $found;
    }

    protected static function doInsert(Model $model): void
    {
        if ($model->exists) {
            throw new CouldNotSaveModelException(
                sprintf('Cannot insert existing model (ID=%s) (table=%s)', $model->getKey(), $model->getTable())
            );
        }

        self::doPersist($model);
    }

    protected static function doPersist(Model $model): void
    {
        try {
            $didSave = $model->save();
        } catch (QueryException $ex) {
            // Copied logic for checking unique constraint violations below from Symfony's PdoSessionHandler

            // Handle integrity violation SQLSTATE 23000 (or a subclass like 23505 in Postgres) for duplicate keys
            if (0 === strpos($ex->getCode(), '23')) {
                throw new ModelSaveIntegrityViolationException($ex->getMessage(), (int) $ex->getCode(), $ex);
            }

            throw $ex;
        }

        if (!$didSave) {
            throw new CouldNotSaveModelException(
                sprintf('Something went wrong while persisting Model (table=%s)', $model->getTable())
            );
        }
    }

    protected static function doDelete(Model $model): void
    {
        if (!$model->exists) {
            throw new CouldNotDeleteModelException('Cannot delete non-existing model');
        }

        try {
            $deleted = $model->delete();
        } catch (Exception $exception) {
            throw new CouldNotDeleteModelException($exception->getMessage(), 0, $exception);
        }

        if (!$deleted) {
            throw new CouldNotDeleteModelException(
                sprintf('Cannot delete model (ID=%s) (table=%s)', $model->getKey(), $model->getTable())
            );
        }
    }

    protected static function doUpdate(Model $model): void
    {
        if (!$model->exists) {
            throw new CouldNotUpdateModelException('Cannot update non-existing model');
        }

        if (!$model->save()) {
            throw new CouldNotUpdateModelException(sprintf(
                'Something went wrong while updating Model (ID=%s) (table=%s)',
                $model->getKey(),
                $model->getTable()
            ));
        }
    }
}
