<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorWhatsappMessage;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Brian2694\Toastr\Facades\Toastr;

class VendorWhatsappMessageController extends Controller
{
    public function index()
    {
        $store = Helpers::get_store_data();
        $message = VendorWhatsappMessage::where('vendor_id', $store->vendor_id)->first();
        return view('vendor-views.whatsapp-message.index', compact('message'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'account_id' => 'nullable',
            'secret_key' => 'nullable',
        ]);

        $store = Helpers::get_store_data();

        VendorWhatsappMessage::updateOrCreate(
            ['vendor_id' => $store->vendor_id],
            [
                'account_id' => $request->account_id,
                'secret_key' => $request->secret_key,
                'status' => $request->has('status') ? 1 : 0,
            ]
        );

        Toastr::success(translate('messages.updated_successfully'));
        return back();
    }
}
