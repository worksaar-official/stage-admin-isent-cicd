<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Models\User;
use App\Traits\Processor;
use Illuminate\Contracts\Foundation\Application as FApp;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class NdasendaController extends Controller
{
    use Processor;

    private PaymentRequest $payment;
    private User $user;

    public function __construct(PaymentRequest $payment, User $user)
    {
        // Load addon_settings('ndasenda','payment_config')
        $config = $this->payment_config('ndasenda', 'payment_config');
        if ($config && in_array($config->mode, ['live','test'])) {
            $values = json_decode($config->{$config->mode . '_values'});
            if ($values) {
                Config::set('ndasenda', [
                    'api_key'     => $values->api_key,
                    'api_secret'  => $values->api_secret,
                    'base_url'    => rtrim($values->base_url, '/'),
                    'merchant_id' => $values->merchant_id,
                ]);
                // Upar ka code ndasenda ke API credentials ko app config me set karta hai
            }
        }

        $this->payment = $payment;
        $this->user = $user;
        // Yaha dependency injection se PaymentRequest aur User models ko controller me assign kiya gaya hai
    }

    // Render minimal page if you need a webview like Razorpay
    public function index(Request $request): View|Factory|JsonResponse|FApp
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);
        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }
        // Request me aayi hui payment_id ko validate karta hai; fail hone par 400 JSON error deta hai

        $data = $this->payment::where(['id' => $request['payment_id'], 'is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }
        // Unpaid payment_request ko database se nikalta hai; na mile to 204 (no content) jaisi response deta hai

        $payer = json_decode($data['payer_information']);
        if ($data['additional_data'] != null) {
            $business = json_decode($data['additional_data']);
            $business_name = $business->business_name ?? "my_business";
            $business_logo = $business->business_logo ?? url('/');
        } else {
            $business_name = "my_business";
            $business_logo = url('/');
        }
        // Payer aur business ki details ko prepare karta hai jo view me dikhengi

        return view('payment-views.ndasenda', compact('data', 'payer', 'business_logo', 'business_name'));
        // Payment page (webview) render karta hai jis me ndasenda integration dikhaya jayega
    }

    // Create redirect session and send user to Ndasenda Checkout
    public function redirect(Request $request): JsonResponse|Redirector|RedirectResponse|FApp
    {
        $request->validate([
            'payment_id' => 'required|uuid',
            'amount'     => 'required|numeric',
            'currency'   => 'required|string',
            'description'=> 'nullable|string',
            'firstName'  => 'nullable|string',
            'lastName'   => 'nullable|string',
            'email'      => 'nullable|email',
        ]);
        // Ndasenda checkout ke liye zaroori fields validate karta hai

        $data = $this->payment::where(['id' => $request['payment_id']])->firstOrFail();
        // payment_id se payment_request ko lazmi (fail hone par 404) tarike se fetch karta hai

        $endpoint = config('ndasenda.base_url') . '/redirect';
        $basic = base64_encode(config('ndasenda.api_key') . ':' . config('ndasenda.api_secret'));
        // Ndasenda redirect API endpoint aur Basic Auth header ready karta hai

        $firstName = trim($request->input('firstName', 'Customer'));
        $lastName = trim($request->input('lastName', ''));
        if ($lastName === '') {
            $lastName = 'Customer';
        }
        $email = trim($request->input('email', ''));
        // Customer ke naam aur email ko sanitize/normalize karta hai

        $payload = [
            'merchantID' => config('ndasenda.merchant_id'),
            'currency'   => $request['currency'],
            'reference'  => $data->id,
            'description'=> $request->input('description', 'Payment'),
            'amount'     => (float)$request['amount'],
            'firstName'  => $firstName,
            'lastName'   => $lastName,
            'email'      => $email,
            'notifyURL'  => route('ndasenda.notify'),
            'cancelURL'  => route('ndasenda.cancel', ['reference' => $data->id]),
            'successURL' => route('ndasenda.complete', ['ref' => $data->id]),
        ];
        // Ndasenda ko bhejne ke liye payload banata hai, callbacks (notify/complete) ki URLs set karta hai

        info(['ndasenda_redirect_request' => ['endpoint' => $endpoint, 'payload' => $payload]]);
        $res = Http::withHeaders([
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Basic ' . $basic,
        ])->post($endpoint, $payload);
        info(['ndasenda_redirect_response' => ['status' => $res->status(), 'body' => $res->json()]]);
        // Ndasenda redirect API ko POST request bhejta hai

        if (!$res->successful()) {
            return response()->json([
                'status' => false,
                'message' => 'Ndasenda request failed',
                'body' => $res->json(),
            ], 422);
        }
        // Agar response successful na ho to 422 error ke sath details return karta hai

        $body = $res->json();
        // Most providers return a redirect/link; if not, fall back to query URL format
        $redirectUrl = $body['redirectUrl'] ?? $body['url'] ?? 'https://checkout.ndasenda.co.zw/?amount='
            . urlencode($payload['amount']) . '&currency=' . urlencode($payload['currency'])
            . '&description=' . urlencode($payload['description']);
        // API response se redirect URL nikalta hai; agar na mile to default query-string based URL banata hai

        // you can update intent reference if returned
        if (isset($body['reference'])) {
            $data->transaction_id = $body['reference'];
            $data->save();
        }
        // Ndasenda ke diye gaye reference ko hamari payment_request me transaction_id ke roop me save karta hai

        return redirect()->away($redirectUrl);
        // User ko Ndasenda ke checkout page par redirect kar deta hai
    }

    // Server-to-server notify webhook
    public function notify(Request $request): JsonResponse|Redirector|RedirectResponse|FApp
    {
        $status = strtolower($request->input('statusName', $request->input('status', $request->input('paymentResponse',''))));
        info(['ndasenda_notify' => ['headers' => $request->headers->all(), 'body' => $request->all()]]);
        // Webhook se aayi hui payment status ko read aur log karta hai
        $refInput = $request->input('reference')
            ?? $request->input('merchantReference')
            ?? $request->input('paymentReference')
            ?? $request->input('id');
        $payment = null;
        if ($refInput) {
            $payment = $this->payment::where('id', $refInput)->first();
            if (!$payment) {
                $payment = $this->payment::where('transaction_id', $refInput)->first();
            }
        }
        if (!$payment) {
            $successURL = $request->input('successURL');
            if (is_string($successURL) && $successURL !== '') {
                $parts = parse_url($successURL);
                $query = $parts['query'] ?? '';
                if ($query !== '') {
                    parse_str($query, $qs);
                    $refFromSuccess = $qs['ref'] ?? ($qs['reference'] ?? null);
                    if ($refFromSuccess) {
                        $payment = $this->payment::find($refFromSuccess) ?: $this->payment::where('transaction_id', $refFromSuccess)->first();
                    }
                }
            }
        }
        // Reference/ID se payment_request dhundta hai; pehle id se phir transaction_id se
        if (!$payment) {
         
              return response()->json(['ok' => false, 'received' => $request->all()], 404);
        }
        // Agar payment record na mile to 404 ke sath webhook payload wapas karta hai

        if (in_array($status, ['paid','success','completed','complete'])) {
            $payment->payment_method = 'ndasenda';
            $payment->is_paid = 1;
            $payment->transaction_id = $request->input('transaction_id', $payment->transaction_id) ?? ($request->input('merchantReference') ?? $request->input('paymentReference'));
            $this->persistNdasendaMeta($payment, $request);
            $payment->save();

            if ($payment && is_string($payment->success_hook) && function_exists($payment->success_hook)) {
                call_user_func($payment->success_hook, $payment);
            }
        } else {
            $this->persistNdasendaMeta($payment, $request);
            $payment->save();
            if ($payment && is_string($payment->failure_hook) && function_exists($payment->failure_hook)) {
                call_user_func($payment->failure_hook, $payment);
            }
        }
        // Success status par payment ko paid mark karta hai aur success_hook chalata hai; warna failure_hook
        /*
        return response()->json(['ok' => true]);
        */
        //try start
        return response()->json(['ok' => true, 'received' => $request->all(), 'payment' => $payment]);
        //try end
        // Webhook ko acknowledgement JSON return karta hai
    }

    // Browser redirections
    public function complete(Request $request): View|Factory|JsonResponse|Redirector|RedirectResponse|FApp
    {
        try {
            $reference = $request->input('reference')
                ?? $request->input('ref')
                ?? $request->input('transactionRef')
                ?? $request->input('merchantReference')
                ?? $request->input('paymentReference');
            $status = strtolower($request->input('statusName', $request->input('status', $request->input('paymentResponse',''))));
            $statusInputs = [
                'statusName' => $request->input('statusName'),
                'status' => $request->input('status'),
                'paymentResponse' => $request->input('paymentResponse'),
            ];
            info(['ndasenda_complete_inputs' => [
                'url' => url()->full(),
                'route' => \Route::currentRouteName(),
                'referer' => $request->headers->get('referer'),
                'reference_candidates' => [
                    'reference' => $request->input('reference'),
                    'ref' => $request->input('ref'),
                    'transactionRef' => $request->input('transactionRef'),
                    'merchantReference' => $request->input('merchantReference'),
                    'paymentReference' => $request->input('paymentReference'),
                ],
                'status_inputs' => $statusInputs,
                'extras' => [
                    'successURL' => $request->input('successURL'),
                    'success' => $request->input('success'),
                    'transaction_id' => $request->input('transaction_id'),
                    'amount' => $request->input('amount'),
                    'currency' => $request->input('currency'),
                    'email' => $request->input('email'),
                    'firstName' => $request->input('firstName'),
                    'lastName' => $request->input('lastName'),
                ],
            ]]);
            $payment = null;
            if ($reference) {
                $payment = $this->payment::find($reference) ?: $this->payment::where('transaction_id', $reference)->first();
            }
            // Browser redirect ke query se reference/status nikalta hai aur payment record locate karta hai

            info(['ndasenda_complete_start' => ['query' => $request->all(), 'ref' => $reference, 'status' => $status]]);
            // Debugging ke liye start state ko log karta hai

            if ($payment) {
                $referer = $request->headers->get('referer');
                $refererRef = null;
                if (is_string($referer) && $referer !== '') {
                    $pr = parse_url($referer);
                    $rq = $pr['query'] ?? '';
                    if ($rq !== '') {
                        parse_str($rq, $rqs);
                        $refererRef = $rqs['ref'] ?? ($rqs['reference'] ?? null);
                    }
                }
                if ((in_array($status, ['paid','success','completed']) || $refererRef) && !$payment->is_paid) {
                    $payment->payment_method = 'ndasenda';
                    $payment->is_paid = 1;
                    $payment->transaction_id = $request->input('transaction_id', $payment->transaction_id) ?? $refererRef ?? $payment->transaction_id;
                    $this->persistNdasendaMeta($payment, $request);
                    $payment->save();
                    if ($payment && is_string($payment->success_hook) && function_exists($payment->success_hook)) {
                        call_user_func($payment->success_hook, $payment);
                    }
                }
                // Agar status success ho aur record unpaid ho to paid mark kar deta hai aur success_hook chalata hai
                info(['ndasenda_complete_payment' => ['found' => (bool)$payment, 'is_paid' => $payment->is_paid, 'id' => $payment->id]]);
                if ($payment->is_paid) {
                    $payer = json_decode($payment['payer_information']);
                    if ($payment['additional_data'] != null) {
                        $business = json_decode($payment['additional_data']);
                        $business_name = $business->business_name ?? "my_business";
                        $business_logo = $business->business_logo ?? url('/');
                    } else {
                        $business_name = "my_business";
                        $business_logo = url('/');
                    }
                    return view('payment-views.ndasenda', [
                        'data' => $payment,
                        'payer' => $payer,
                        'business_logo' => $business_logo,
                        'business_name' => $business_name,
                        'mode' => 'result',
                        'flag' => 'success',
                        'dialog' => true,
                    ]);
                    // Agar payment ho chuki ho to success result view render karta hai
                }
            }

            $successUrlParam = $request->input('successURL');
            $successParam = $request->input('success');
            $statusFallbackSuccess = ($successParam === '1' || strtolower((string)$successParam) === 'true') || !empty($successUrlParam);
            $computedFlag = ((isset($payment) && $payment && $payment->is_paid) || in_array($status, ['paid','success','completed']) || $statusFallbackSuccess) ? 'success' : 'fail';
            info(['ndasenda_complete_missing' => [
                'has_payment' => (bool)$payment,
                'is_paid' => $payment?->is_paid ?? null,
                'status_empty' => empty($status),
                'reference_empty' => empty($reference),
                'fallback_success' => $statusFallbackSuccess,
            ]]);
            info(['ndasenda_complete_result' => ['flag' => $computedFlag, 'status' => $status, 'is_paid' => $payment?->is_paid ?? null, 'ref' => $reference]]);
            return view('payment-views.ndasenda', [
                'data' => $payment ?? (object)['id' => $reference],
                'payer' => isset($payment) ? json_decode($payment['payer_information']) : (object)[],
                'business_logo' => url('/'),
                'business_name' => "my_business",
                'mode' => 'result',
                'flag' => $computedFlag,
                'dialog' => true,
            ]);
            // Agar payment confirm na ho payi ho to status ke base par success/fail result view dikhata hai
        } catch (\Throwable $e) {
            \Log::error('ndasenda_complete_error: '.$e->getMessage());
            $status = strtolower($request->input('statusName', $request->input('status', $request->input('paymentResponse',''))));
            $reference = $request->input('reference')
                ?? $request->input('ref')
                ?? $request->input('transactionRef')
                ?? $request->input('merchantReference')
                ?? $request->input('paymentReference');
            info(['ndasenda_complete_catch' => ['query' => $request->all(), 'ref' => $reference, 'status' => $status, 'error' => $e->getMessage()]]);
            return view('payment-views.ndasenda', [
                'data' => (object)['id' => $reference],
                'payer' => (object)[],
                'business_logo' => url('/'),
                'business_name' => "my_business",
                'mode' => 'result',
                'flag' => in_array($status, ['paid','success','completed']) ? 'success' : 'fail',
                'dialog' => true,
            ]);
            // Exception aane par error log karta hai aur safe fallback result view return karta hai
        }
    }

    public function paid(Request $request): View|Factory|JsonResponse|Redirector|RedirectResponse|FApp
    {
        return $this->complete($request);
        // Paid callback bhi complete() ke through hi result flow handle karta hai
    }

    public function cancel(Request $request): View|Factory|JsonResponse|Redirector|RedirectResponse|FApp
    {
        $reference = $request->input('reference') ?? $request->input('ref');
        $payment = null;
        if ($reference) {
            $payment = $this->payment::find($reference) ?: $this->payment::where('transaction_id', $reference)->first();
        }

        if ($payment && is_string($payment->failure_hook) && function_exists($payment->failure_hook)) {
            call_user_func($payment->failure_hook, $payment);
        }

        $payer = $payment ? json_decode($payment['payer_information']) : (object)[];
        if ($payment && $payment['additional_data'] != null) {
            $business = json_decode($payment['additional_data']);
            $business_name = $business->business_name ?? "my_business";
            $business_logo = $business->business_logo ?? url('/');
        } else {
            $business_name = "my_business";
            $business_logo = url('/');
        }

        return view('payment-views.ndasenda', [
            'data' => $payment ?? (object)['id' => $reference],
            'payer' => $payer,
            'business_logo' => $business_logo,
            'business_name' => $business_name,
            'mode' => 'result',
            'flag' => 'fail',
            'dialog' => true,
        ]);
    }

    private function persistNdasendaMeta(PaymentRequest $payment, Request $request): void
    {
        $payment->plarftormID_ndasenda = $request->input('plarftormID');
        $payment->customerAcc_ndasenda = $request->input('customerAcc');
        $payment->methodName_ndasenda = $request->input('methodName');
        $payment->statusName_ndasenda = $request->input('statusName');
        $payment->paymentReference_ndasenda = $request->input('paymentReference');
        $payment->merchantReference_ndasenda = $request->input('merchantReference');
        $payment->paymentDescription_ndasenda = $request->input('paymentDescription');
        $payment->merchantDescription_ndasenda = $request->input('merchantDescription');
        $payment->merchantFees_ndasenda = $request->input('merchantFees');
        $payment->customerFees_ndasenda = $request->input('customerFees');
        $payment->paidDate_ndasenda = $request->input('paidDate');
        $payment->createdDate_ndasenda = $request->input('createdDate');
        $payment->correlator_ndasenda = $request->input('correlator');
    }
}
