<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Seller;
use RuntimeException;
use DB;

class SellerController extends Controller {

    public $successStatus = 200;
    public $limit = 10;


    public function __construct() {

    }

    public function get_sellers(Request $request) {
        try {
            $array = array();

            foreach (Seller::all() as $key => $seller) {
                if($seller->user != null && $seller->user->shop != null){
                    $total_sale = 0;
                    foreach ($seller->user->products as $key => $product) {
                        $total_sale += $product->num_of_sale;
                    }
                    $array[$seller->id] = $total_sale;
                }
            }
            asort($array);

            $count = 0;
            $statusArray = [];
            
            foreach ($array as $key => $value) {
                if ($count < 20){
                    $count ++;
                    $seller = Seller::find($key);
                    $total = 0;
                    $rating = 0;
                    foreach ($seller->user->products as $skey => $seller_product) {
                        $total += $seller_product->reviews->count();
                        $rating += $seller_product->reviews->sum('rating');
                    }
                  
                    $statusArray[] = array(
                        'id' => $seller->user->shop->user_id,
                        'slug' => $seller->user->shop->slug,
                        'logo' => asset($seller->user->shop->logo),
                        'name' => $seller->user->shop->name,
                        'review' => $total,
                        'rating' => $total
                    );

                }
            }
            
            return response()->json([
                'status' => 200,
                'message' => trans('messages.seller_list'),
                'data' => $statusArray
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        } 
    }

    public function get_product($slug){
        try {
            $statusArray = [];
            
            $product  = Product::where('slug', $slug)->first();

            // $price = 0;
            // $sell_price = 0;
            // if($product->tax_type == 'amount'){
            //     $price = $product->unit_price + $product->tax;
            // } else if($product->tax_type == 'percent'){
            //     $tax = number_format((($product->unit_price*$product->tax)/100),1);
            //     $price = number_format($product->unit_price + $tax,2);
            // }

            // if($product->discount_type == 'amount'){
            //     $sell_price = $price - $product->discount;
            // } else if($product->discount_type == 'percent'){
            //     $discount = number_format((($price*$product->discount)/100),1);
            //     $sell_price = number_format($price - $discount,2);
            // }

            $photos = [];
            foreach(json_decode($product->photos) as $iKey => $iVal){
                $photos[] = url('public/'.$iVal);
            }

            $relatedProduct = Product::where('subcategory_id', $product->subcategory_id)->where('id', '!=', $product->id)->limit(10)->get();
            $relatedProducts = [];
            foreach($relatedProduct as $rKey => $rVal){
                $relatedProducts[] = array(
                    'id' => $rVal->id,
                    'name' => $rVal->name,
                    'slug' => $rVal->slug,
                    'thumbnail_img' => ($rVal->thumbnail_img)?url('public/'.$rVal->thumbnail_img):'',
                    'price' => home_base_price($rVal->id),
                    'sell_price' => home_discounted_base_price($rVal->id),
                    'rating' => $rVal->rating
                );
            }
            $colors = [];
            foreach(json_decode($product->colors) as $cKey => $cVal){
                $color = Color::where('code', $cVal)->first();
                $colors[] = array(
                    'name' => $color->name,
                    'code' => $color->code
                );
            }

            $variations = [];
            foreach(json_decode($product->variations, true) as $key => $val){
                $variations[] = array(
                    'name' => $key,
                    'price' => $val['price'], 
                    'sku' => $val['sku'],
                    'qty' => $val['qty']
                );
            }

            $statusArray['id'] = $product->id;
            $statusArray['name'] = $product->name;
            $statusArray['slug'] = $product->slug;
            $statusArray['photos'] = $photos;
            $statusArray['category'] = $product->category;
            $statusArray['subcategory'] = $product->subcategory;
            $statusArray['subsubcategory'] = $product->subsubcategory;
            $statusArray['unit'] = $product->unit;
            $statusArray['price'] = home_price($product->id);
            $statusArray['discounted_price'] = home_discounted_price($product->id);
            $statusArray['choice_options'] = json_decode($product->choice_options);
            $statusArray['colors'] = $colors;
            $statusArray['variations'] = $variations;
            $statusArray['seller'] = ($product->added_by == 'seller')?$product->user:'Inhouse product';
            $statusArray['description'] = $product->description;
            $statusArray['rating'] = $product->rating;
            $statusArray['reviews'] = $product->reviews;
            $statusArray['related_product'] = $relatedProducts;
            
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

    public function get_filter(Request $request) {
        try {
            $brands = array();
            $subsubcategory_id = $request->subsubcategory_id;
            $subcategory_id = $request->subcategory_id;
            $category_id = $request->category_id;

            if(!empty($subsubcategory_id)){
                foreach (json_decode(\App\SubSubCategory::find($subsubcategory_id)->brands) as $brand) {
                    if(!in_array($brand, $brands)){
                        array_push($brands, $brand);
                    }
                }
            } else if(!empty($subcategory_id)){
                foreach (\App\SubCategory::find($subcategory_id)->subsubcategories as $key => $subsubcategory){
                    foreach (json_decode($subsubcategory->brands) as $brand) {
                        if(!in_array($brand, $brands)){
                            array_push($brands, $brand);
                        }
                    }
                }
            } else if(!empty($category_id)){
                foreach (\App\Category::find($category_id)->subcategories as $key => $subcategory){
                    foreach ($subcategory->subsubcategories as $key => $subsubcategory){
                        foreach (json_decode($subsubcategory->brands) as $brand) {
                            if(!in_array($brand, $brands)){
                                array_push($brands, $brand);
                            }
                        }
                    }
                }
            } else {
                foreach (\App\Brand::all() as $key => $brand){
                    if(!in_array($brand->id, $brands)){
                        array_push($brands, $brand->id);
                    }
                }
            }

            $brandArray=[];
            foreach($brands as $key => $id){
                $brand = \App\Brand::find($id);
                if ($brand != null){
                    $brandArray[] = array(
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'logo' => asset($brand->logo)
                    );
                }
            }

            $sellerArray=[];
            foreach (\App\Seller::all() as $key => $seller){
                $sellerArray[] = array(
                    'id' => $seller->id,
                    'name' => $seller->user->shop->name
                );
            }

            $productsObj = Product::where('published',1);
            if($request->get('q')){
                $productsObj = $productsObj->where('name', 'like', '%'.$request->get('q').'%'); 
            }
            if($category_id){
                $category = explode(',',$category_id);
                $productsObj = $productsObj->whereIn('category_id',$category); 
            }
            if($subcategory_id){
                $subcategory = explode(',',$subcategory_id);
                $productsObj = $productsObj->whereIn('subcategory_id',$subcategory); 
            }
            if($subsubcategory_id){
                $subsubcategory = explode(',',$subsubcategory_id);
                $productsObj = $productsObj->whereIn('subsubcategory_id',$subsubcategory); 
            }
            if($request->get('brand_id')){
                $brand = explode(',',$request->get('brand_id'));
                $productsObj = $productsObj->whereIn('brand_id',$brand); 
            }
            if($request->get('seller_id')){
                $seller = explode(',',$request->get('seller_id'));
                $productsObj = $productsObj->whereIn('user_id',$seller); 
            }
            $productsObj = $productsObj->get();
            
            $statusArray = [];
            $statusArray['brand'] = $brandArray;
            $statusArray['seller'] = $sellerArray;
            $statusArray['min_price'] = $productsObj->min('unit_price');
            $statusArray['max_price'] = $productsObj->max('unit_price');

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
