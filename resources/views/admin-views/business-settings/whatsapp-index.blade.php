@extends('layouts.admin.app')

@section('title', translate('messages.whatsapp'))

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <i class="tio-whatsapp"></i>
                </span>
                <span>
                    <!-- {{translate('messages.whatsapp_configuration')}} -->
                     Whatsapp Configuration
                </span>
            </h1>
        </div>
        <!-- End Page Header -->

        <div class="row">
            <div class="col-md-12">
                <form action="{{route('admin.business-settings.whatsapp-update')}}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <span class="card-header-icon">
                                    <i class="tio-whatsapp"></i>
                                </span>
                                <!-- <span>{{translate('messages.whatsapp')}}</span> -->
                                 <span>Whatsapp Messages</span>
                            </h5>
                            <label class="toggle-switch toggle-switch-sm">
                                <input type="checkbox" class="toggle-switch-input" name="status" value="1" {{$whatsapp_status == 1 ? 'checked' : ''}}>
                                <span class="toggle-switch-label">
                                    <span class="toggle-switch-indicator"></span>
                                </span>
                            </label>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="input-label" for="whatsapp_secret">{{translate('messages.secret')}} *</label>
                                <input type="text" name="whatsapp_secret" class="form-control" id="whatsapp_secret" value="{{$whatsapp_secret}}" placeholder="{{translate('messages.secret')}}" required>
                            </div>
                            <div class="form-group">
                                <!-- <label class="input-label" for="whatsapp_account_id">{{translate('messages.account_id')}} *</label> -->
                                 <label class="input-label" for="whatsapp_account_id">Account id *</label>
                                <input type="text" name="whatsapp_account_id" class="form-control" id="whatsapp_account_id" value="{{$whatsapp_account_id}}" placeholder="{{translate('messages.account_id')}}" required>
                            </div>

                            <div class="btn--container justify-content-end">
                                <button type="submit" class="btn btn--primary">{{translate('messages.save')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
