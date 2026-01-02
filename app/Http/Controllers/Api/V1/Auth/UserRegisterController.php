<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\BusinessSetting;
use App\Models\User;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailVerification;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Modules\Gateways\Traits\SmsGateway;
use App\CentralLogics\SMS_module;

class UserRegisterController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
            'ref_code' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        try {
            $ref_by = null;
            if ($request->ref_code) {
                $ref_status = BusinessSetting::where('key', 'ref_earning_status')->first()->value;
                if ($ref_status != '1') {
                    return response()->json(['errors' => Helpers::error_formater('ref_code', translate('messages.referer_disable'))], 403);
                }

                $referar_user = User::where('ref_code', '=', $request->ref_code)->first();
                if (!$referar_user || !$referar_user->status) {
                    return response()->json(['errors' => Helpers::error_formater('ref_code', translate('messages.referer_code_not_found'))], 405);
                }

                $ref_by = $referar_user->id;
            }

            $name = $request->name;
            $nameParts = explode(' ', $name, 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? '';

            $user = User::create([
                'f_name' => $firstName,
                'l_name' => $lastName,
                'email' => $request->email,
                'phone' => $request->phone,
                'ref_by' => $ref_by,
                'password' => bcrypt($request->password)
            ]);

            $user->ref_code = Helpers::generate_referer_code($user);
            $user->save();

            $token = $user->createToken('RestaurantCustomerAuth')->accessToken;

            $login_settings = array_column(BusinessSetting::whereIn('key', [
                'manual_login_status',
                'otp_login_status',
                'social_login_status',
                'google_login_status',
                'facebook_login_status',
                'apple_login_status',
                'email_verification_status',
                'phone_verification_status'
            ])->get(['key', 'value'])->toArray(), 'value', 'key');

            $phone_verified = 1;
            $email_verified = 1;
            if (isset($login_settings['phone_verification_status']) && $login_settings['phone_verification_status'] == 1) {
                $phone_verified = 0;
                $this->sendPhoneVerification($request->phone);
            }

            if (isset($login_settings['email_verification_status']) && $login_settings['email_verification_status'] == 1) {
                $email_verified = 0;
                $this->sendEmailVerification($request->email, $firstName);
            }
            try {
                $mail_status = Helpers::get_mail_status('registration_mail_status_user');
                if (config('mail.status') && $mail_status == '1') {
                    Mail::to($request->email)->send(new \App\Mail\CustomerRegistration($request->name));
                }
            } catch (\Exception $ex) {
                info($ex->getMessage());
            }

            return response()->json([
                'token' => $token,
                'is_phone_verified' => $phone_verified,
                'is_email_verified' => $email_verified,
                'is_personal_info' => 1,
                'is_exist_user' => null,
                'login_type' => 'manual',
                'email' => $request->email,
                'user' => $user,
                'message' => translate('messages.registration_successful')
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function sendPhoneVerification($phone)
    {
        $firebase_otp_verification = BusinessSetting::where('key', 'firebase_otp_verification')->first()?->value ?? 0;

        if (!$firebase_otp_verification) {
            $otp_interval_time = 60; // seconds
            $verification_data = DB::table('phone_verifications')->where('phone', $phone)->first();

            if (isset($verification_data) && Carbon::parse($verification_data->updated_at)->DiffInSeconds() < $otp_interval_time) {
                $time = $otp_interval_time - Carbon::parse($verification_data->updated_at)->DiffInSeconds();
                return [
                    'errors' => [['code' => 'otp', 'message' => translate('messages.please_try_again_after_') . $time . ' ' . translate('messages.seconds')]]
                ];
            }

            $otp = rand(100000, 999999);
            if (env('APP_MODE') == 'test') {
                $otp = '123456';
            }

            DB::table('phone_verifications')->updateOrInsert(['phone' => $phone], [
                'token' => $otp,
                'otp_hit_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $published_status = 0;
            $payment_published_status = config('get_payment_publish_status');
            if (isset($payment_published_status[0]['is_published'])) {
                $published_status = $payment_published_status[0]['is_published'];
            }

            if ($published_status == 1) {
                $response = SmsGateway::send($phone, $otp);
            } else {
                $response = SMS_module::send($phone, $otp);
            }

            if (env('APP_MODE') != 'test' && $response !== 'success') {
                return [
                    'errors' => [['code' => 'otp', 'message' => translate('messages.failed_to_send_sms')]]
                ];
            }
        }

        return true;
    }

    private function sendEmailVerification($email, $name)
    {
        $otp = rand(100000, 999999);
        if (env('APP_MODE') == 'test') {
            $otp = '123456';
        }

        DB::table('email_verifications')->updateOrInsert(['email' => $email], [
            'token' => $otp,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $mailResponse = null;
            $mail_status = Helpers::get_mail_status('registration_otp_mail_status_user');

            if (config('mail.status') && $mail_status == '1') {
                Mail::to($email)->send(new EmailVerification($otp, $name));
                $mailResponse = 'success';
            }
        } catch (\Exception $ex) {
            info($ex->getMessage());
            $mailResponse = null;
        }

        if (env('APP_MODE') != 'test' && $mailResponse !== 'success') {
            return [
                'errors' => [['code' => 'otp', 'message' => translate('messages.failed_to_send_mail')]]
            ];
        }

        return true;
    }
}
