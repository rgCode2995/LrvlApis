<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use RuntimeException;
use DB;
use Validator;

class PayumoneyController extends Controller {

    public $successStatus = 200;

    public function __construct() {

    }

    public function generate_hash(Request $request) {
        try {
            $txnid = $request->txnid;
            $amount = $request->amount;
            $productinfo = $request->productinfo;
            $firstname = $request->firstname;
            $email = $request->email;
            $udf1 = $request->user_id;
            $key = env('PAYUMONEY_KEY');
            $salt = env('PAYUMONEY_SALT');

            $hashSequence= $key.'|'.$txnid.'|'.$amount.'|'.$productinfo.'|'.$firstname.'|'.$email.'|'.$udf1.'||||||||||'.$salt;
            $hash = strtolower(hash("sha512", $hashSequence));

            return response()->json([
                'status' => 200,
                'result' => $hash
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        } 
    }
}
