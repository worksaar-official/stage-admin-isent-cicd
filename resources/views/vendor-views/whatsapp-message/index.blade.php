@extends('layouts.vendor.app')

@section('title', translate('messages.vendor_whatsapp_message'))

@section('content')
    <div class="content container-fluid">
        <!-- Page Heading -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <i class="tio-whatsapp"></i>
                </span>
                <span>
                    {{ translate('messages.vendor_whatsapp_message') }}
                </span>
            </h1>
        </div>
        <!-- End Page Heading -->

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <span class="card-header-icon">
                        <i class="tio-settings-outlined"></i>
                    </span>
                    <span>
                        {{ translate('messages.configuration') }}
                    </span>
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('vendor.whatsapp-message.store') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label" for="account_id">{{ translate('messages.account_id') }}</label>
                                <input type="text" name="account_id" value="{{ $message ? $message->account_id : '' }}" class="form-control" id="account_id" placeholder="{{ translate('messages.account_id') }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label" for="secret_key">{{ translate('messages.secret_key') }}</label>
                                <input type="text" name="secret_key" value="{{ $message ? $message->secret_key : '' }}" class="form-control" id="secret_key" placeholder="{{ translate('messages.secret_key') }}" required>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="toggle-switch toggle-switch-sm d-flex justify-content-between border border-secondary rounded px-4 form-control" for="status">
                                    <span class="pr-2">{{ translate('messages.status') }}</span>
                                    <label class="switch toggle-switch-lg m-0">
                                        <input type="checkbox" name="status" class="toggle-switch-input" id="status" {{ $message && $message->status ? 'checked' : '' }}>
                                        <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="btn--container justify-content-end">
                        <button type="reset" class="btn btn--reset">{{ translate('messages.reset') }}</button>
                        <button type="submit" class="btn btn--primary">{{ translate('messages.submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
