<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SendSMSController extends Controller
{
    //



public static function sendSms($phone, $message)
{
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
        'x-rapidapi-host' => 'sms-verify3.p.rapidapi.com',
        'x-rapidapi-key' => '578c22e437msh324f9480b79bfcbp1c8a23jsnc0d42298c88e',
    ])->post('https://sms-verify3.p.rapidapi.com/send-numeric-verify', [
        "target" => $phone,
        "estimate" => false
    ]);

    return $response->json();
}
}
