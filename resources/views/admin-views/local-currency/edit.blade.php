@extends('layouts.admin.app')

@section('title', 'Update Local Currency')

@push('css_or_js')
@endpush

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{ asset('public/assets/admin/img/edit.png') }}" class="w--20" alt="">
            </span>
            <span>Update Local Currency</span>
        </h1>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.local-currency.update', [$currency->id]) }}" method="post">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label class="input-label" for="local_rate">Local Rate</label>
                    <input type="number" step="0.00000001" min="0" name="local_rate" id="local_rate" class="form-control" value="{{ old('local_rate', $currency->local_rate) }}" required>
                </div>
                <div class="btn--container justify-content-end">
                    <button type="reset" class="btn btn--reset">{{ translate('messages.reset') }}</button>
                    <button type="submit" class="btn btn--primary">{{ translate('messages.update') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script_2')
@endpush