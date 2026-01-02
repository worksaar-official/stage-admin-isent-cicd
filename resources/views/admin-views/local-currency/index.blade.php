@extends('layouts.admin.app')

@section('title', 'Local Currency List')

@push('css_or_js')
@endpush

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{ asset('public/assets/admin/img/money.png') }}" class="w--20" alt="">
            </span>
            <span>Local Currency List</span>
        </h1>
    </div>

    <div class="card">
        <div class="table-responsive datatable-custom">
            <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                <thead class="thead-light">
                    <tr class="text-center">
                        <th class="border-0">{{ translate('sl') }}</th>
                        <th class="border-0">Local Rate</th>
                        <th class="border-0">{{ translate('messages.action') }}</th>
                    </tr>
                </thead>
                <tbody class="text-center">
                    @forelse($currencies as $key => $currency)
                        <tr>
                            <td>{{ $key + ($currencies->firstItem() ?? 1) }}</td>
                            <td>{{ $currency->local_rate }}</td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn action-btn btn--primary btn-outline-primary" href="{{ route('admin.local-currency.edit', [$currency->id]) }}" title="{{ translate('messages.edit') }}"><i class="tio-edit"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <div class="empty--data">
                                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                                    <h5>{{ translate('no_data_found') }}</h5>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(count($currencies) !== 0)
            <hr>
        @endif
        <div class="page-area">
            {!! $currencies->links() !!}
        </div>
    </div>
</div>
@endsection

@push('script_2')
@endpush