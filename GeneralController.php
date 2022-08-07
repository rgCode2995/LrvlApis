<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Country;
use App\Category;
use App\SubCategory;
use App\Brand;
use App\Slider;
use App\Banner;
use App\Policy;
use RuntimeException;
use DB;
use Validator;

class GeneralController extends Controller {

    public $successStatus = 200;
    public $user = null;
    public $profile = null;

    public function __construct() {

    }

    public function get_slider(Request $request) {
        try {

            $categoryObj = Slider::select(['id','photo'])->where('published', 1);

            if($request->get('limit')){
                $categoryObj = $categoryObj->limit($request->get('limit'));
            }

            $categoryObj = $categoryObj->orderby('id','DESC')->get()->toArray();

            $statusArray = [];
                foreach ($categoryObj as $key => $value) {
                    $statusArray[$key]['id']      = $value['id'];
                    $statusArray[$key]['photo']    = asset($value['photo']);
                }
            return response()->json([
                'status' => 200,
                'message' => trans('messages.category_list'),
                'data' => $statusArray
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        } 
    }

    public function get_banner(Request $request) {
        try {

            $categoryObj = Banner::select(['id','url','photo'])->where('published', 1);

            
            if($request->get('position')){
                $categoryObj = $categoryObj->where('position', $request->get('position'));
            }

            if($request->get('limit')){
                $categoryObj = $categoryObj->limit($request->get('limit'));
            }

            $categoryObj = $categoryObj->orderby('id','DESC')->get()->toArray();

            $statusArray = [];
                foreach ($categoryObj as $key => $value) {
                    $statusArray[$key]['id']      = $value['id'];
                    $statusArray[$key]['url']      = $value['url'];
                    $statusArray[$key]['photo']    = asset($value['photo']);
                }
            return response()->json([
                'status' => 200,
                'message' => trans('messages.category_list'),
                'data' => $statusArray
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        } 
    }

    public function get_brand(Request $request) {
        try {

            $categoryObj = Brand::select(['id','name','logo']);

            if($request->get('top_10') == 1){
                $categoryObj = $categoryObj->where('top',1);
            }

            if($request->get('limit')){
                $categoryObj = $categoryObj->limit($request->get('limit'));
            }

            $categoryObj = $categoryObj->orderby('name','ASC')->get()->toArray();

            $statusArray = [];
                foreach ($categoryObj as $key => $value) {
                    $statusArray[$key]['id']      = $value['id'];
                    $statusArray[$key]['name']    = $value['name'];
                    $statusArray[$key]['logo']   = ($value['logo'])?url('public/'.$value['logo']):'';
                }
            return response()->json([
                'status' => 200,
                'message' => trans('messages.category_list'),
                'data' => $statusArray
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        } 
    }
    public function get_category(Request $request) {
        try {

            $categoryObj = Category::select(['id','name','banner','icon']);

            if($request->get('top_10') == 1){
                $categoryObj = $categoryObj->where('top',1);
            }

            if($request->get('limit')){
                $categoryObj = $categoryObj->limit($request->get('limit'));
            }

            // $categoryObj = $categoryObj->orderby('name','ASC')->get();
            $categoryObj = $categoryObj->get();
            $statusArray = [];
                foreach ($categoryObj as $key => $value) {
                    // $subcategory = SubCategory::select(['id','name'])->where('category_id', $value['id'])->orderby('name','ASC')->get()->toArray();

                    $sArray = array();
                    foreach($value->subcategories as $sKey => $sVal){
                        $sArray[$sKey]['id'] = $sVal->id;
                        $sArray[$sKey]['name'] = $sVal->name;

                        $subArray = array();
                        foreach($sVal->subsubcategories as $ssKey => $ssVal){
                            $subArray[$ssKey]['id'] = $ssVal->id;
                            $subArray[$ssKey]['name'] = $ssVal->name;
                        }

                        $sArray[$sKey]['subsubcategory'] = $subArray;
                    }

                    $statusArray[$key]['id']      = $value->id;
                    $statusArray[$key]['name']    = $value->name;
                    $statusArray[$key]['banner']   = ($value->banner)?url('public/'.$value->banner):'';
                    $statusArray[$key]['icon']   = ($value->icon)?url('public/'.$value->icon):'';
                    $statusArray[$key]['subcategory'] = $sArray;
                   
                }
            return response()->json([
                'status' => 200,
                'message' => trans('messages.category_list'),
                'data' => $statusArray
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        } 
    }
    public function get_subcategory(Request $request) {
        try {
            $category_id    = $request->get("category_id");
            $categoryObj = SubCategory::select(['id','name'])
                            ->where(function($query) use ($category_id)  {
                                    if(isset($category_id) && 0 < (int)$category_id) {
                                       $query->where('category_id', $category_id);
                                    }
                                 })
                            ->orderby('name','ASC')
                            ->get()->toArray();
            $statusArray = [];
                foreach ($categoryObj as $key => $value) {
                    $statusArray[$key]['id']      = $value['id'];
                    $statusArray[$key]['name']    = $value['name'];
                   
                }
            return response()->json([
                'status' => 200,
                'message' => trans('messages.category_list'),
                'data' => $statusArray
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        } 
    }

    public function skills_get(Request $request) {
        try {
            $id_category    = $request->get("id_category");

            $categoryObj = Skills::select(['id','name'])
                                ->where(function($query) use ($id_category)  {
                                    if(isset($id_category) && 0 < (int)$id_category) {
                                       $query->where('id_category', $id_category);
                                    }
                                 })
                                ->where('status', "Active")->orderby('name','ASC')->get()->toArray();
            return response()->json([
                'status' => 200,
                'message' => trans('messages.success_list'),
                'data' => $categoryObj
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        } 
    }

    public function city_get(Request $request) {
        try {
            $id_country = $request->get("id_country");
            if(isset($id_country) && 0 < $id_country){
                $categoryObj = Cities::select('id','name')
                                    ->where('id_country', $id_country)
                                    ->where('status', "Active")
                                    ->orderby('name','ASC')
                                    ->get()->toArray();
            }else{
                $categoryObj = Cities::select('id','name')
                                    ->where('status', "Active")
                                    ->orderby('name','ASC')
                                    ->get()->toArray();
            }
            return response()->json([
                'status' => 200,
                'message' => trans('messages.city_list'),
                'data' => $categoryObj
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        }
        
    }
    public function get_country(Request $request) {
        try {
            $categoryObj = Country::select('id','name','code')
                                ->orderby('name','ASC')
                                ->get()->toArray();
            
            return response()->json([
                'status' => 200,
                'message' => trans('messages.country_list'),
                'data' => $categoryObj
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        }
        
    }

    public function salary_type_get(Request $request) {
        try {
            $categoryObj = SalaryType::select('id','name')
                                ->where('status', "Active")
                                ->orderby('name','ASC')
                                ->get()->toArray();
            
            return response()->json([
                'status' => 200,
                'message' => trans('messages.salary_list'),
                'data' => $categoryObj
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        }
        
    }
    public function job_type_get(Request $request) {
        try {
            $categoryObj = JobTypes::select('id','name')
                                ->where('status', "Active")
                                ->orderby('name','ASC')
                                ->get()->toArray();
            
            return response()->json([
                'status' => 200,
                'message' => trans('messages.job_type_list'),
                'data' => $categoryObj
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        }
        
    }

    public function currency_get(Request $request) {
        try {
            $categoryObj = Currency::select('id','country','currency','code','symbol')
                                ->where('status', "Active")
                                ->orderby('id','ASC')
                                ->get()->toArray();
            
            return response()->json([
                'status' => 200,
                'message' => trans('messages.job_type_list'),
                'data' => $categoryObj
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        }
        
    }

    public function company_get(Request $request) {
        try {
            $start  = $request->get("start");
            $limit  = $request->get("limit");
           
            $start = (isset($start) && !empty($start))?$start:0;
            $start  = (($start - 1) * $limit);
            $limit = (isset($limit) && !empty($limit)) ? $limit : 10;

            $companyarray   = Company::select("id","id_industry", "logo", "name", "description", "website", "address", "id_city", "id_country", "latitude", "longitude","created_at")
                                ->where('status', "Active")
                                ->orderby('name','ASC')
                                ->offset($start)
                                ->limit($limit)
                                ->get()
                                ->toArray();
            $statusArray = [];
            if (!empty($companyarray)) {
                $cities_array = Cities::getAll();
                $country_array = Country::getAll();
                $industry_array = Industry::getAll();
                
                foreach ($companyarray as $key => $value) {
                    $id_city        = isset($value['id_city'])      ? $value['id_city']     : 0;
                    $id_country     = isset($value['id_country'])   ? $value['id_country']  : 0;
                    $id_industry    = isset($value['id_industry'])  ? $value['id_industry'] : 0;
                    
                    $statusArray[$key]['id']            = $value['id'];
                    $statusArray[$key]['name']          = $value['name'];
                    $statusArray[$key]['logo']          = $value['logo'];
                    $statusArray[$key]['description']   = $value['description'];
                    $statusArray[$key]['website']       = $value['website'];
                    $statusArray[$key]['address']       = $value['address'];
                    $statusArray[$key]['industry']      = isset($industry_array[$id_industry]['name']) ? $industry_array[$id_industry]['name'] : '';
                    $statusArray[$key]['city']          = isset($cities_array[$id_city]['name']) ? $cities_array[$id_city]['name'] : '';
                    $statusArray[$key]['country']       = isset($country_array[$id_country]['name']) ? $country_array[$id_country]['name'] : '';
                    $statusArray[$key]['latitude']      = $value['latitude'];
                    $statusArray[$key]['longitude']     = $value['longitude'];
                    $statusArray[$key]['created_at']    = $value['created_at'];
                    
                }
            }
            return response()->json([
                'status' => 200,
                'message' => trans('messages.company_list'),
                'data' => $statusArray
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        } 
    }

    public function get_cms(Request $request) {

        $rules = [
                'name'     => 'required',
            ];
            $messages = [
                'name.required' => trans('validation.required'),
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
        $data = Policy::select('id','name','content')->where('name', $request->name)->first();
        if (empty($data)) {
            return response()->json([
                            'status' => 400,
                            'message' => 'No any cms found',
                            'errors' => [
                                'error' => 'No any cms found.',
                            ]
                                ], $this->successStatus);
        }
        return response()->json([
                            'status' => 200,
                            'message' => "Cms get Successfully ",
                            'data' =>  $data
                                ], $this->successStatus);
    }
}
