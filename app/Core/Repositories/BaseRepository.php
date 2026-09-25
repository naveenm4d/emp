<?php

namespace App\Core\Repositories;

use App\Core\Contracts\RepositoryInterface;
use App\Core\Exceptions\NotFoundException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of the common repository operations.
 *
 * Repositories are the only layer that builds Eloquent queries. Services
 * depend on the domain repository interface, never on this class.
 *
 * @template TModel of Model
 *
 * @implements RepositoryInterface<TModel>
 */
abstract class BaseRepository implements RepositoryInterface
{
    /** @return class-string<TModel> */
    abstract protected function model(): string;

    /** Human friendly name used in not-found errors. */
    protected function resourceName(): string
    {
        return class_basename($this->model());
    }

    /** @return Builder<TModel> */
    protected function query(): Builder
    {
        return $this->model()::query();
    }

    public function find(string $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function findOrFail(string $id): Model
    {
        return $this->find($id) ?? throw new NotFoundException("{$this->resourceName()} not found.");
    }

    public function create(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();

        return $model;
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return $this->query()->latest()->paginate($perPage)->withQueryString();
    }
}
