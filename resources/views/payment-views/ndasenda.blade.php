<!DOCTYPE html>
<html>
<head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ndasenda</title></head>
<body>
@php($mode = $mode ?? 'redirect')
@if($mode === 'redirect')
    <div style="display:flex;justify-content:center;align-items:center;min-height:100vh;flex-direction:column;gap:1rem;">
        <h1 style="font-size:1.2rem;text-align:center;">Please do not refresh this page...</h1>
        <div style="display:flex;gap:1rem;">
            <form id="f" method="POST" action="{{ route('ndasenda.redirect') }}">
                @csrf
                <input type="hidden" name="payment_id" value="{{ $data->id }}">
                <input type="hidden" name="amount" value="{{ round($data->payment_amount,2) }}">
                <input type="hidden" name="currency" value="{{ $data->currency_code }}">
                <input type="hidden" name="description" value="Payment for {{ $data->attribute_id }}">
                <input type="hidden" name="firstName" value="{{ $payer->first_name ?? 'Customer' }}">
                <input type="hidden" name="lastName" value="{{ ($payer->last_name ?? '') !== '' ? $payer->last_name : 'Customer' }}">
                <input type="hidden" name="email" value="{{ $payer->email ?? '' }}">
            </form>
            
        </div>
    </div>
    <script>setTimeout(function(){document.getElementById('f').submit();}, 500);</script>
@else
    <div style="position:fixed;inset:0;background:rgba(0,0,0,.4);display:flex;justify-content:center;align-items:center;">
        <div style="width:92%;max-width:520px;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);padding:24px;text-align:center;">
            <div style="width:80px;height:80px;border-radius:50%;margin:0 auto;background:{{ $flag === 'success' ? '#e6f7ec' : '#fdeaea' }};display:flex;justify-content:center;align-items:center;">
                <span style="font-size:40px;color:{{ $flag === 'success' ? '#45a049' : '#e53935' }};">{{ $flag === 'success' ? '✓' : '✕' }}</span>
            </div>
            <h2 style="margin:18px 0 8px;font-size:20px;">{{ $flag === 'success' ? 'Payment Successful' : 'Payment Failed' }}</h2>
            <p style="margin:0 0 18px;line-height:1.6;color:#555;">{{ $flag === 'success' ? 'Your order is placed successfully. We will start processing it shortly.' : 'We could not complete your payment. Please try again or contact support.' }}</p>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button type="button" onclick="window.location.href='{{ request()->input('successURL', $data->external_redirect_link ?? url('/') ) }}'" style="background:#0c7262;color:#fff;border:none;padding:.6rem 1.6rem;border-radius:6px;cursor:pointer;">Continue</button>
                <button type="button" onclick="window.close && window.close();" style="background:#f0f0f0;color:#333;border:none;padding:.6rem 1.6rem;border-radius:6px;cursor:pointer;">Close</button>
            </div>
        </div>
    </div>
@endif
</body>
</html>
