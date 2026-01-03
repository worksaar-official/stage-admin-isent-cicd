<?php

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Model;

interface LocalCurrencyConversionRepositoryInterface extends RepositoryInterface
{
    /**
     * @param array $params
     * @param array $relations
     * @return Model|null
     */
    public function getFirstWithoutGlobalScopeWhere(array $params, array $relations = []): ?Model;
}