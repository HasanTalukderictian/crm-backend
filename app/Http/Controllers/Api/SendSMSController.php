<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class SendSMSController extends Controller
{
    public static function sendSms($phone, $message)
    {
        $response = Http::post('https://smsplus.sslwireless.com/api/v3/send-sms', [
            "api_token" => "xhw7rusz-3yo58wqh-0dfjsxwu-bpitrk9x-dxmsyxvh",
            "sid"       => "AKASHBARIHDMASKING",
            "msisdn"    => $phone,
            "sms"       => $message,
            "csms_id"   => uniqid() // auto unique id
        ]);

        return $response->json();
    }
}
