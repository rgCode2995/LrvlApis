<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Order;
use App\BusinessSetting;
use App\Coupon;
use App\CouponUsage;
use App\Cart;
use App\Product;
use App\Color;
use App\OrderDetail;

use PDF;
use Mail;
use App\Mail\InvoiceEmailManager;

use RuntimeException;
use DB;

class CheckoutController extends Controller {

    public $successStatus = 200;
    public $limit = 10;


    public function __construct() {

    }

    public function checkout(Request $request){
        try {

            $user_id = $request->user_id;
            $name = $request->name;
            $email = $request->email;
            $address = $request->address;
            $country = $request->country;
            $city = $request->city;
            $postal_code = $request->postal_code;
            $phone = $request->phone;
            $checkout_type = 'logged';
            $payment_option = $request->payment_option;
            $coupon_discount = $request->coupon_discount;
            $coupon_id = $request->coupon_id;

            if(empty($name)){
                return response()->json([
                    'status' => 400,
                    'message' => 'name is required.',
                ], 400);
            } else if(empty($email)){
                return response()->json([
                    'status' => 400,
                    'message' => 'email is required.',
                ], 400);
            } else if(empty($address)){
                return response()->json([
                    'status' => 400,
                    'message' => 'address is required.',
                ], 400);
            } else if(empty($country)){
                return response()->json([
                    'status' => 400,
                    'message' => 'country is required.',
                ], 400);
            } else if(empty($city)){
                return response()->json([
                    'status' => 400,
                    'message' => 'city is required.',
                ], 400);
            } else if(empty($postal_code)){
                return response()->json([
                    'status' => 400,
                    'message' => 'postal code is required.',
                ], 400);
            } else if(empty($phone)){
                return response()->json([
                    'status' => 400,
                    'message' => 'phone is required.',
                ], 400);
            } else if(empty($payment_option)){
                return response()->json([
                    'status' => 400,
                    'message' => 'Payment method is required.',
                ], 400);
            } else if(empty($user_id)){
                return response()->json([
                    'status' => 400,
                    'message' => 'User is required.',
                ], 400);
            } else {
                
                $shipping_info = array(
                    'name' => $name,
                    'email' => $email,
                    'address' => $address,
                    'country' => $country,
                    'city' => $city,
                    'postal_code' => $postal_code,
                    'phone' => $phone,
                    'checkout_type' => $checkout_type
                );

                $order = new Order;
                $order->user_id = $user_id;
                $order->shipping_address = json_encode($shipping_info);
                $order->payment_type = $payment_option;
                $order->code = date('Ymd-his');
                $order->date = strtotime('now');

                if($order->save()){
                    $subtotal = 0;
                    $tax = 0;
                    $shipping = 0;

                    $cart = Cart::where('user_id',$user_id)->get();
                    foreach ($cart as $key => $cartItem){
                        $product = Product::find($cartItem->product_id);

                        $subtotal += $cartItem->price*$cartItem->quantity;
                        $tax += $cartItem->tax*$cartItem->quantity;
                        $shipping += $cartItem->shipping*$cartItem->quantity;

                        $product_variation = null;
                        foreach (json_decode($cartItem->options) as $choice){
                            if(!empty($choice['color'])){
                                $product_variation .= Color::where('code', $choice['color'])->first()->name;
                            }
                        }

                        foreach (json_decode($cartItem->options) as $cKey => $choice){
                            if($cKey != 'color'){
                                if ($product_variation != null) {
                                    $product_variation .= '-'.str_replace(' ', '', $choice);
                                }
                                else {
                                    $product_variation .= str_replace(' ', '', $choice);
                                }
                            }
                        }
                        
                        // return response()->json([
                        //     'status' => 200,
                        //     'message' => 'Product added into cart.',
                        //     'data' => $product_variation
                        // ], $this->successStatus);
                        // die;

                        if($product_variation != null){
                            $variations = json_decode($product->variations);
                            $variations->$product_variation->qty -= $cartItem->quantity;
                            $product->variations = json_encode($variations);
                            $product->save();
                        }

                        $order_detail = new OrderDetail;
                        $order_detail->order_id  =$order->id;
                        $order_detail->seller_id = $product->user_id;
                        $order_detail->product_id = $product->id;
                        $order_detail->variation = $product_variation;
                        $order_detail->price = $cartItem->price * $cartItem->quantity;
                        $order_detail->tax = $cartItem->tax * $cartItem->quantity;
                        $order_detail->shipping_cost = $cartItem->shipping*$cartItem->quantity;
                        $order_detail->quantity = $cartItem->quantity;
                        $order_detail->save();

                        $product->num_of_sale++;
                        $product->save();
                    }

                    $order->grand_total = $subtotal + $tax + $shipping;
                    if(!empty($coupon_discount)){
                        $order->grand_total -= $coupon_discount;        
                        $order->coupon_discount = $coupon_discount;
        
                        $coupon_usage = new CouponUsage;
                        $coupon_usage->user_id = $user_id;
                        $coupon_usage->coupon_id = $coupon_id;
                        $coupon_usage->save();
                    }

                    $order->save();

                    $pdf = PDF::setOptions([
                        'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true,
                        'logOutputFile' => storage_path('logs/log.htm'),
                        'tempDir' => storage_path('logs/')
                    ])->loadView('invoices.customer_invoice', compact('order'));

                    $output = $pdf->output();
                    file_put_contents('public/invoices/'.'Order#'.$order->code.'.pdf', $output);

                    $array['view'] = 'emails.invoice';
                    $array['subject'] = 'Order Placed - '.$order->code;
                    $array['from'] = env('MAIL_USERNAME');
                    $array['content'] = 'Hi. Your order has been placed';
                    $array['file'] = 'public/invoices/Order#'.$order->code.'.pdf';
                    $array['file_name'] = 'Order#'.$order->code.'.pdf';

                    //sends email to customer with the invoice pdf attached
                    if(env('MAIL_USERNAME') != null && env('MAIL_PASSWORD') != null){
                        Mail::to($email)->queue(new InvoiceEmailManager($array));
                    }
                    unlink($array['file']);

                    $cart = Cart::where('user_id',$user_id)->delete();
                }

                return response()->json([
                    'status' => 200,
                    'message' => 'Product added into cart.',
                    'data' => []
                ], $this->successStatus);
            }
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        }
    }

    public function payumoney(Request $request){
        try {

            $txnid = $request->txnid;
            $amount = $request->amount;
            $productinfo = $request->productinfo;
            $firstname = $request->firstname;
            $email = $request->email;
            $udf1 = $request->udf1;
            $key = $request->key;
            $salt = env('PAYUMONEY_SALT');

            $hashSequence= $key.'|'.$txnid.'|'.$amount.'|'.$productinfo.'|'.$firstname.'|'.$email.'|'.$udf1.'||||||||||'.$salt;
            $hash = strtolower(hash("sha512", $hashSequence));

            if($hash == $request->hash){
                $user_id = $udf1;
                $name = $firstname;
                $email = $email;
                $address = $request->address1.' '.$request->address2;
                $country = $request->country;
                $city = $request->city;
                $postal_code = $request->zipcode;
                $phone = $request->phone;
                $checkout_type = 'logged';
                $payment_option = 'payumoney';
                $coupon_discount = NULL;
                $coupon_id = NULL;

                $shipping_info = array(
                    'name' => $name,
                    'email' => $email,
                    'address' => $address,
                    'country' => $country,
                    'city' => $city,
                    'postal_code' => $postal_code,
                    'phone' => $phone,
                    'checkout_type' => $checkout_type
                );

                $order = new Order;
                $order->user_id = $user_id;
                $order->shipping_address = json_encode($shipping_info);
                $order->payment_type = $payment_option;
                $order->code = date('Ymd-his');
                $order->date = strtotime('now');

                if($order->save()){
                    $subtotal = 0;
                    $tax = 0;
                    $shipping = 0;

                    $cart = Cart::where('user_id',$user_id)->get();
                    foreach ($cart as $key => $cartItem){
                        $product = Product::find($cartItem->product_id);

                        $subtotal += $cartItem->price*$cartItem->quantity;
                        $tax += $cartItem->tax*$cartItem->quantity;
                        $shipping += $cartItem->shipping*$cartItem->quantity;

                        $product_variation = null;
                        foreach (json_decode($cartItem->options) as $choice){
                            if(!empty($choice['color'])){
                                $product_variation .= Color::where('code', $choice['color'])->first()->name;
                            }
                        }

                        foreach (json_decode($cartItem->options) as $cKey => $choice){
                            if($cKey != 'color'){
                                if ($product_variation != null) {
                                    $product_variation .= '-'.str_replace(' ', '', $choice);
                                }
                                else {
                                    $product_variation .= str_replace(' ', '', $choice);
                                }
                            }
                        }
                        
                        // return response()->json([
                        //     'status' => 200,
                        //     'message' => 'Product added into cart.',
                        //     'data' => $product_variation
                        // ], $this->successStatus);
                        // die;

                        if($product_variation != null){
                            $variations = json_decode($product->variations);
                            $variations->$product_variation->qty -= $cartItem->quantity;
                            $product->variations = json_encode($variations);
                            $product->save();
                        }

                        $order_detail = new OrderDetail;
                        $order_detail->order_id  =$order->id;
                        $order_detail->seller_id = $product->user_id;
                        $order_detail->product_id = $product->id;
                        $order_detail->variation = $product_variation;
                        $order_detail->price = $cartItem->price * $cartItem->quantity;
                        $order_detail->tax = $cartItem->tax * $cartItem->quantity;
                        $order_detail->shipping_cost = $cartItem->shipping*$cartItem->quantity;
                        $order_detail->quantity = $cartItem->quantity;
                        $order_detail->save();

                        $product->num_of_sale++;
                        $product->save();
                    }

                    $order->grand_total = $subtotal + $tax + $shipping;
                    if(!empty($coupon_discount)){
                        $order->grand_total -= $coupon_discount;        
                        $order->coupon_discount = $coupon_discount;
        
                        $coupon_usage = new CouponUsage;
                        $coupon_usage->user_id = $user_id;
                        $coupon_usage->coupon_id = $coupon_id;
                        $coupon_usage->save();
                    }

                    $order->save();

                    $pdf = PDF::setOptions([
                        'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true,
                        'logOutputFile' => storage_path('logs/log.htm'),
                        'tempDir' => storage_path('logs/')
                    ])->loadView('invoices.customer_invoice', compact('order'));

                    $output = $pdf->output();
                    file_put_contents('public/invoices/'.'Order#'.$order->code.'.pdf', $output);

                    $array['view'] = 'emails.invoice';
                    $array['subject'] = 'Order Placed - '.$order->code;
                    $array['from'] = env('MAIL_USERNAME');
                    $array['content'] = 'Hi. Your order has been placed';
                    $array['file'] = 'public/invoices/Order#'.$order->code.'.pdf';
                    $array['file_name'] = 'Order#'.$order->code.'.pdf';

                    //sends email to customer with the invoice pdf attached
                    if(env('MAIL_USERNAME') != null && env('MAIL_PASSWORD') != null){
                        Mail::to($email)->queue(new InvoiceEmailManager($array));
                    }
                    unlink($array['file']);

                    $cart = Cart::where('user_id',$user_id)->delete();
                }
            }
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        }
    }

    public function removeFromCart($id){
        try {
            $cart  = Cart::find($id);
            if($cart){
                $cart->delete();
                return response()->json([
                    'status' => 200,
                    'message' => 'Product removed from cart.',
                    'data' => []
                ], $this->successStatus);
            } else {
                return response()->json([
                    'status' => 400,
                    'message' => 'Cart item not found.',
                    'data' => []
                ], 400);
            }
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        }
    }

    public function updateQuantity(Request $request){
        try {
            $cart_id = $request->id;
            $user_id = $request->user_id;
            $quantity = $request->quantity;

            if(empty($cart_id)){
                return response()->json([
                    'status' => 400,
                    'message' => 'Cart id is required.',
                ], 400);
            } else if(empty($user_id)){
                return response()->json([
                    'status' => 400,
                    'message' => 'User is required.',
                ], 400);
            } else if(empty($quantity)){
                return response()->json([
                    'status' => 400,
                    'message' => 'Quantity is required.',
                ], 400);
            } else {
                $cart  = Cart::where('user_id', $user_id)->where('id',$cart_id)->first();
                if($cart){
                    $cart->quantity = $quantity;
                    $cart->save();

                    return response()->json([
                        'status' => 200,
                        'message' => 'Quantity updated.',
                        'data' => []
                    ], $this->successStatus);
                } else {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Cart product not found.',
                    ], 400);
                }
            }
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        }
    }

    public function get_cart($user_id) {
        try {
            $cart = Cart::where('user_id',$user_id)->get();
            $statusArray = [];
            foreach ($cart as $key => $value) {

                $product = Product::find($value->product_id);

                $options = json_decode($value->options);
                $str = '';
                foreach($options as $oKey => $oVal){
                    if($oKey == 'color'){
                        $str = ' - '.\App\Color::where('code', $oVal)->first()->name;
                    } else {
                        $str = ' - '.$oVal;
                    }
                }

                $statusArray[$key]['id'] = $value->id;
                $statusArray[$key]['name'] = $product->name.$str;
                $statusArray[$key]['thumbnail_img'] = ($product->thumbnail_img)?url('public/'.$product->thumbnail_img):'';
                $statusArray[$key]['price'] = number_format($value->price,2);
                $statusArray[$key]['quantity'] = $value->quantity;
                $statusArray[$key]['tax'] = $value->tax;
                $statusArray[$key]['shipping'] = $value->shipping;
            }
            
            return response()->json([
                'status' => 200,
                'message' => trans('messages.product_list'),
                'data' => $statusArray
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        }
    }
}
