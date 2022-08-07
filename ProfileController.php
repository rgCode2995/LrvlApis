<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\User;
use App\Customer;
use App\BusinessSetting;
use RuntimeException;
use Hash;
use Validator;
use Cache;

class ProfileController extends Controller { 

    public $successStatus = 200;
    public $user = null;
    public $profile = null;
    private $coulumToDisplay = ["id","name","email","avatar",'address','country','city','postal_code','phone','api_token',"created_at"];
    
    public function __construct() {
        $this->middleware(function ($request, $next) {
            $header = $request->header('Authorization');
            if ($header !== null) {
                $api_token = str_replace("Bearer ", "", $header);
                $this->user = User::where('api_token', $api_token)->first();
                if (!empty($this->user)) {
                    $this->profile = User::select($this->coulumToDisplay)->where('id',$this->user->id)->first();
                } else {
                    return response()->json([
                                'status' => 401,
                                'message' => 'Unauthorized access',
                                'errors' => [
                                    'error' => 'Unauthorized access'
                                ]], $this->successStatus);
                }
            }
            return $next($request);
        });
    }

    public function update_profile(Request $request) {
        try {
            $rules = [
                'user_id'     => 'required',
            ];
            $messages = [
                'user_id.required' => trans('validation.required'),
            ];
            $validator = Validator::make($request->all(), $rules, $messages);
            if ($validator->fails()) {
                $error_messages = [];
                $errors = $validator->errors();
                foreach ($errors->getMessages() as $key => $error) {
                    $error_messages[$key] = $error[0];
                }
                return response()->json([
                            'status' => 400,
                            'message' => $errors->first(),
                            'errors' => $error_messages
                                ], $this->successStatus);
            }
            $user_id      = $request->get("user_id");
            $this->profile = User::find($user_id);
            if (isset($request->name) && 0 < strlen($request->name)) {
                $this->profile->name = $request->name;
            }
            // if (isset($request->email)) {
            //     $this->user->email = $request->email;
            // }
            
            if (isset($request->address) && 0 < strlen($request->address)) {
                $this->profile->address = $request->address;
            }
            
            if (isset($request->country) && 0 < strlen($request->country)) {
                $this->profile->country = $request->country;
            }

            if (isset($request->city) && 0 < strlen($request->city)) {
                $this->profile->city = $request->city;
            }

            if (isset($request->postal_code) && 0 < strlen($request->postal_code)) {
                $this->profile->postal_code = $request->postal_code;
            }

            if (isset($request->phone) && 0 < strlen($request->phone)) {
                $this->profile->phone = $request->phone;
            }
           
            if ($this->profile->save()) {
                return response()->json([
                            'status' => $this->successStatus,
                            'message' => trans('messages.profile_update'),
                                ], $this->successStatus);
            }
            return response()->json([
                        'status' => 500,
                        'message' => trans('messages.internal_error'),
                        'errors' => [
                            'error' => trans('messages.internal_error'),
                        ]
                            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                Bugsnag::notifyException($ex);
            }
        }
    }

    public function get_profile(Request $request) {
            $rules = [
                'user_id'     => 'required',
            ];
            $messages = [
                'user_id.required' => trans('validation.required'),
            ];
            $validator = Validator::make($request->all(), $rules, $messages);
            if ($validator->fails()) {
                $error_messages = [];
                $errors = $validator->errors();
                foreach ($errors->getMessages() as $key => $error) {
                    $error_messages[$key] = $error[0];
                }
                return response()->json([
                            'status' => 400,
                            'message' => $errors->first(),
                            'errors' => $error_messages
                                ], $this->successStatus);
            }
            $user_id      = $request->get("user_id");
            $userdetial     = User::select($this->coulumToDisplay)
                                    ->where('id', $user_id)
                                     ->first();
            if (isset($userdetial['id']) && 0 < (int)$userdetial['id']) {
            return response()->json([
                        'status' => $this->successStatus,
                        'message' => trans('messages.profile_display'),
                        'data' => [
                            "profile" => $userdetial
                        ]
                            ], $this->successStatus);
            }else{
                 return response()->json([
                        'status' => 400,
                        'message' => "You have entered an invalid username Or password",
                        'errors' => [
                            'error' => "You have entered an invalid username Or password",
                        ]
                            ], $this->successStatus);
            }
        
    }
  

}
