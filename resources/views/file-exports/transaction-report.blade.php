<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('order_transactions_report') }}</h1></div>
    <div class="col-lg-12">



    <table>
        @php
            $orderIds = collect($data['order_transactions'] ?? [])->pluck('order_id')->unique()->values();
            $payments = \App\Models\PaymentRequest::whereIn('attribute_id', $orderIds)->get()->keyBy('attribute_id');
            $ndKeys = [
                'plarftormID_ndasenda' => 'Ndasenda Platform ID',
                'customerAcc_ndasenda' => 'Ndasenda Customer Account',
                'methodName_ndasenda' => 'Ndasenda Method Name',
                'statusName_ndasenda' => 'Ndasenda Status Name',
                'paymentReference_ndasenda' => 'Ndasenda Payment Reference',
                'merchantReference_ndasenda' => 'Ndasenda Merchant Reference',
                'paymentDescription_ndasenda' => 'Ndasenda Payment Description',
                'merchantDescription_ndasenda' => 'Ndasenda Merchant Description',
                'merchantFees_ndasenda' => 'Ndasenda Merchant Fees',
                'customerFees_ndasenda' => 'Ndasenda Customer Fees',
                'paidDate_ndasenda' => 'Ndasenda Paid Date',
                'createdDate_ndasenda' => 'Ndasenda Created Date',
                'correlator_ndasenda' => 'Ndasenda Correlator',
            ];
            $ndShowKeys = [];
            foreach(($data['order_transactions'] ?? []) as $ot){
                $p = $payments[$ot->order_id] ?? null;
                if($p){
                    foreach($ndKeys as $k=>$label){
                        $v = $p->$k ?? null;
                        if(!(is_null($v) || $v==='')){ $ndShowKeys[$k] = $label; }
                    }
                }
            }
        @endphp
        <thead>
            <tr>
                <th>{{ translate('Search_Criteria') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('module' )}} - {{ $data['module']?translate($data['module']):translate('all') }}
                    <br>
                    {{ translate('zone' )}} - {{ $data['zone']??translate('all') }}
                    <br>
                    {{ translate('store' )}} - {{ $data['store']??translate('all') }}
                    @if ($data['from'])
                    <br>
                    {{ translate('from' )}} - {{ $data['from']?Carbon\Carbon::parse($data['from'])->format('d M Y'):'' }}
                    @endif
                    @if ($data['to'])
                    <br>
                    {{ translate('to' )}} - {{ $data['to']?Carbon\Carbon::parse($data['to'])->format('d M Y'):'' }}
                    @endif
                    <br>
                    {{ translate('filter')  }}- {{  translate($data['filter']) }}
                    <br>
                    {{ translate('Search_Bar_Content')  }}- {{ $data['search'] ??translate('N/A') }}

                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
                </tr>
            <tr>
                <th>{{ translate('Transaction_Analytics') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Completed_Transactions')  }}- {{ $data['delivered'] ??translate('N/A') }}
                    <br>
                    {{ translate('Refunded_Transactions')  }}- {{ $data['canceled'] ??translate('N/A') }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr>
                <th>{{ translate('Earning_Analytics') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Admin_Earnings')  }} - {{ $data['admin_earned'] ??translate('N/A') }}
                    <br>
                    {{ translate('Store_Earnings')  }} - {{ $data['store_earned'] ??translate('N/A') }}
                    <br>
                    {{ translate('Delivery_Man_Earnings')  }} - {{ $data['deliveryman_earned'] ??translate('N/A') }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        <tr>
            <th>{{ translate('sl') }}</th>
            <th>{{ translate('messages.order_id') }}</th>
            <th>{{ translate('messages.store') }}</th>
            <th>{{ translate('messages.customer_name') }}</th>
            <th>{{ translate('messages.delivery_man') }}</th>
            <th>{{ translate('messages.total_item_amount') }}</th>
            <th>{{ translate('messages.item_discount') }}</th>
            <th>{{ translate('messages.coupon_discount') }}</th>
            <th>{{ translate('messages.referral_discount') }}</th>
            <th>{{ translate('messages.discounted_amount') }}</th>
            <th>{{ translate('messages.vat/tax') }}</th>
            <th>{{ translate('messages.delivery_charge') }}</th>
            <th>{{ translate('messages.order_amount') }}</th>
            <th>{{ translate('messages.admin_discount') }}</th>
            <th>{{ translate('messages.store_discount') }}</th>
            <th>{{ translate('messages.admin_commission') }}</th>
            <th>{{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name')??translate('messages.additional_charge') }}</th>
            <th>{{ translate('messages.extra_packaging_amount') }}</th>
            <th>{{ translate('commision_on_delivery_charge') }}</th>
            <th>{{ translate('admin_net_income') }}</th>
            <th>{{ translate('store_net_income') }}</th>
            <th>DM Tips</th>
            <th>{{ translate('messages.amount_received_by') }}</th>
            <th style="border:1px solid #000;padding:6px;text-align:center;vertical-align:middle;">{{ translate('messages.payment_method') }}</th>
            <th style="border:1px solid #000;padding:6px;text-align:center;vertical-align:middle;">{{ translate('messages.payment_status') }}</th>
            @foreach($ndShowKeys as $label)
                <th style="background:#b3b3b3;border:1px solid #000;color:#333;padding:6px;text-align:center;vertical-align:middle;">{{ $label }}</th>
            @endforeach
        </thead>
        <tbody>
        @foreach($data['order_transactions'] as $key => $ot)
            <tr>
                <td>{{ $key+1}}</td>
                <td>{{ $ot->order_id }}</td>
                <td>
                    @if($ot->order?->store)
                        {{Str::limit($ot->order?->store?->name ?? '',25,'...')}}
                    @else
                        {{ translate('messages.parcel_order') }}
                    @endif
                </td>
                <td>
                    @if ($ot->order?->customer)
                        {{  ($ot->order?->customer['f_name'] ?? '') . ' ' . ($ot->order?->customer['l_name'] ?? '')  }}
                    @else
                        {{ translate('messages.not_found') }}
                    @endif
                </td>
                <td>{{ $ot?->order?->delivery_man?->full_name ?? 'NA' }}</td>
                {{-- total_item_amount --}}
                <td>{{ \App\CentralLogics\Helpers::format_currency( ($ot->order?->order_amount ?? 0) - ($ot->additional_charge ?? 0) - ($ot->order?->dm_tips ?? 0) - ($ot->order?->delivery_charge ?? 0) - ($ot['tax'] ?? 0) + ($ot->order?->coupon_discount_amount ?? 0) + ($ot->order?->store_discount_amount ?? 0) + ($ot->order?->flash_admin_discount_amount ?? 0) + ($ot->order?->flash_store_discount_amount ?? 0) + ($ot->order?->ref_bonus_amount ?? 0) - ($ot->order?->extra_packaging_amount ?? 0) ) }}</td>


                {{-- item_discount --}}
                <td>{{ \App\CentralLogics\Helpers::format_currency( (($ot->order) ? $ot->order->details()->sum(DB::raw('discount_on_item * quantity')) : 0) + ($ot->order?->flash_admin_discount_amount ?? 0) + ($ot->order?->flash_store_discount_amount ?? 0) ) }}</td>

                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->order?->coupon_discount_amount ?? 0) }}</td>
                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->order?->ref_bonus_amount ?? 0) }}</td>
                {{-- discounted_amount --}}
                <td>  {{ \App\CentralLogics\Helpers::number_format_short( ($ot->order?->coupon_discount_amount ?? 0) + ($ot->order?->store_discount_amount ?? 0) + ($ot->order?->flash_store_discount_amount ?? 0) + ($ot->order?->flash_admin_discount_amount ?? 0) + ($ot->order?->ref_bonus_amount ?? 0) ) }}</td>

                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->tax) }}</td>
                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->delivery_charge) }}</td>
                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->order_amount) }}</td>
                {{-- admin_discount --}}
                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->admin_expense) }}</td>
                {{-- store_discount --}}
                <td>{{ \App\CentralLogics\Helpers::format_currency(($ot->discount_amount_by_store ?? 0) + ($ot->order?->flash_store_discount_amount ?? 0)) }}</td>
                {{-- admin_commission --}}
                <td>{{ \App\CentralLogics\Helpers::format_currency((($ot->admin_commission ?? 0) + ($ot->admin_expense ?? 0)) - ($ot->delivery_fee_comission ?? 0) - ($ot->additional_charge ?? 0) - ($ot->order?->flash_admin_discount_amount ?? 0)) }}</td>

                <td>{{ \App\CentralLogics\Helpers::format_currency(($ot->additional_charge)) }}</td>
                <td>{{ \App\CentralLogics\Helpers::format_currency(($ot->extra_packaging_amount)) }}</td>
                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->delivery_fee_comission) }}</td>
                {{-- admin_net_income --}}
                <td>{{ \App\CentralLogics\Helpers::format_currency((($ot->admin_commission ?? 0)  - ($ot->order?->flash_admin_discount_amount ?? 0))) }}</td>
                <td>{{ \App\CentralLogics\Helpers::format_currency($ot->store_amount -($ot?->order?->order_type == 'parcel' ? 0: $ot->tax)) }}</td>
                <td>{{ \App\CentralLogics\Helpers::format_currency($ot?->order?->dm_tips ?? 0) }}</td>
                @if ($ot->received_by == 'admin')
                    <td>{{ translate('messages.admin') }}</td>
                @elseif ($ot->received_by == 'deliveryman')
                    <td>
                        <div>{{ translate('messages.delivery_man') }}</div>
                        <div>
                            @if (isset($ot->delivery_man) && $ot->delivery_man->earning == 1)
                                {{translate('messages.freelance')}}
                            @elseif (isset($ot->delivery_man) && $ot->delivery_man->earning == 0 && $ot->delivery_man->type == 'restaurant_wise')
                                {{translate('messages.restaurant')}}
                            @elseif (isset($ot->delivery_man) && $ot->delivery_man->earning == 0 && $ot->delivery_man->type == 'zone_wise')
                                {{translate('messages.admin')}}
                            @endif
                        </div>
                    </td>
                @elseif ($ot->received_by == 'store')
                    <td>{{ translate('messages.store') }}</td>
                @endif
                <td style="border:1px solid #000;padding:6px;vertical-align:middle;">
                        {{ translate(str_replace('_', ' ', ($ot->order?->payment_method ?? ''))) }}
                </td>
                <td style="border:1px solid #000;padding:6px;vertical-align:middle;">
                    @if ($ot->status)
                        {{translate('messages.refunded')}}
                    @else
                        {{translate('messages.completed')}}
                    @endif
                </td>
                @php $p = $payments[$ot->order_id] ?? null; @endphp
                @foreach($ndShowKeys as $k=>$label)
                    <td style="border:1px solid #000;padding:6px;vertical-align:middle;">{{ $p?->$k }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
