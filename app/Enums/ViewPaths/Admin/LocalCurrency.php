<?php

namespace App\Enums\ViewPaths\Admin;

enum LocalCurrency
{
    const INDEX = [
        URI => '/',
        VIEW => 'admin-views.local-currency.index'
    ];

    const UPDATE = [
        URI => 'edit',
        VIEW => 'admin-views.local-currency.edit'
    ];
}