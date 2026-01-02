<?php

namespace App\Repositories;

use App\Contracts\Repositories\LocalCurrencyConversionRepositoryInterface;
use App\Models\LocalCurrencyConversion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class LocalCurrencyConversionRepository implements LocalCurrencyConversionRepositoryInterface
{
    public function __construct(protected LocalCurrencyConversion $currency)
    {
    }

    public function add(array $data): string|object
    {
        $model = $this->currency->newInstance();
        foreach ($data as $key => $value) {
            $model[$key] = $value;
        }
        $model->save();
        return $model;
    }

    public function getFirstWhere(array $params, array $relations = []): ?Model
    {
        $query = $this->currency->newQuery();
        if (!empty($relations)) {
            $query->with($relations);
        }
        foreach ($params as $key => $value) {
            $query->where($key, $value);
        }
        return $query->first();
    }

    public function getFirstWithoutGlobalScopeWhere(array $params, array $relations = []): ?Model
    {
        $query = $this->currency->newQueryWithoutScopes();
        if (!empty($relations)) {
            $query->with($relations);
        }
        foreach ($params as $key => $value) {
            $query->where($key, $value);
        }
        return $query->first();
    }

    public function getList(array $orderBy = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, int $offset = null): Collection|LengthAwarePaginator
    {
        $query = $this->currency->newQuery();
        if (!empty($relations)) {
            $query->with($relations);
        }
        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }
        if ($dataLimit === 'all') {
            return $query->get();
        }
        return $query->paginate($dataLimit);
    }

    public function getListWhere(string $searchValue = null, array $filters = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, int $offset = null): Collection|LengthAwarePaginator
    {
        $query = $this->currency->newQuery();
        if (!empty($relations)) {
            $query->with($relations);
        }
        if ($searchValue) {
            $query->where('local_rate', 'like', "%{$searchValue}%");
        }
        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }
        if ($dataLimit === 'all') {
            return $query->get();
        }
        return $query->paginate($dataLimit);
    }

    public function update(string $id, array $data): bool|string|object
    {
        $model = $this->currency->find($id);
        if (!$model) return false;
        foreach ($data as $key => $value) {
            $model[$key] = $value;
        }
        $model->save();
        return $model;
    }

    public function delete(string $id): bool
    {
        $model = $this->currency->find($id);
        if (!$model) return false;
        return (bool) $model->delete();
    }
}