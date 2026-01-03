<?php

namespace App\Http\Controllers\Admin\Item;

use App\Contracts\Repositories\LocalCurrencyConversionRepositoryInterface;
use App\Enums\ViewPaths\Admin\LocalCurrency as LocalCurrencyViewPath;
use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class LocalCurrencyController extends BaseController
{
    public function __construct(
        protected LocalCurrencyConversionRepositoryInterface $currencyRepo,
    ) {
    }

    public function index(?Request $request): View|LengthAwarePaginator|null
    {
        $currencies = $this->currencyRepo->getListWhere(
            searchValue: request()?->search,
            dataLimit: config('default_pagination')
        );
        return view(LocalCurrencyViewPath::INDEX[VIEW], compact('currencies'));
    }

    public function getUpdateView(string|int $id): View
    {
        $currency = $this->currencyRepo->getFirstWithoutGlobalScopeWhere(params: ['id' => $id]);
        return view(LocalCurrencyViewPath::UPDATE[VIEW], compact('currency'));
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $request->validate([
            'local_rate' => ['required', 'numeric', 'min:0'],
        ]);

        $this->currencyRepo->update(id: $id, data: [
            'local_rate' => $request->local_rate,
        ]);
        Toastr::success(translate('messages.updated_successfully'));
        return redirect()->route('admin.local-currency.index');
    }
}