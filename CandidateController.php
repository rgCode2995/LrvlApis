<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Follower;
use App\Models\User;
use App\Models\Profile;
use App\Models\Cities;
use App\Models\Country;
use App\Models\Category;
use App\Models\Company;
use App\Models\Industry;
use App\Models\SalaryType;
use App\Models\JobTypes;
use App\Models\Jobs;
use App\Models\JobQuestion;
use App\Models\JobQuestionAnswer;
use App\Models\CandidateJob;
use App\Models\CandidateWishlist;
use App\Models\Employer;
use App\Models\Notification;
use App\Models\JobCurrency;
use Illuminate\Support\Facades\Auth;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use RuntimeException;
use DB;
use Validator;

class CandidateController extends Controller {

    public $successStatus = 200;
    public $user = null;
    public $profile = null;
    private $coulumToDisplay = ["id","name","email","mobile_no","profile_image",'default_currency','default_language', "about","created_at"];

    public function __construct() {

       $this->middleware(function ($request, $next) {
            if (Auth::user() !== null) {
                $this->user = Auth::user();
                $this->profile = User::select($this->coulumToDisplay)->where('id',$this->user->id)->first();
            }
            $header = $request->header('Authorization');
            if ($header !== null) {
                $api_token = str_replace("Bearer ", "", $header);
                $this->user = User::where('api_token', $api_token)->where('deleted_at', null)->first();
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

    public function dashboard() {
        try {
            if (empty($this->user)) {
                return response()->json([
                            'status' => 404,
                            'message' => trans('messages.user_not_found'),
                            'errors' => array('user_not_found' => trans('messages.user_not_found'))
                                ], $this->successStatus);
            }
            $categoryarray  = Category::select(['id','name'])->where('status', "Active")->orderby('name','ASC')->get()->toArray();
            $locationarray  = Cities::select('id','name')->where('status', "Active")->orderby('name','ASC')->get()->toArray();
            $salaryTypearray= SalaryType::select('id','name')->where('status', "Active")->orderby('name','ASC')->get()->toArray();
            $JobTypesarray  = JobTypes::select('id','name')->where('status', "Active")->orderby('name','ASC')->get()->toArray();
            $companyarray 	= Company::select("id", "logo", "name", "description", "website")->where('status', "Active")->where('is_featured', 1)->orderby('id','ASC')->get()->toArray();
            //$jobsarray 		= Jobs::where('status', "Active")->where('is_featured', 1)->orderby('id','ASC')->get()->toArray();

            $success['job_count']       = Jobs::where('status', "Active")->get()->count();
            $success['categories']      = (isset($categoryarray)    && 0 < count($categoryarray))   ?   $categoryarray  :[];
            $success['locations']       = (isset($locationarray)    && 0 < count($locationarray))   ?   $locationarray  :[];
            $success['salary_type']     = (isset($salaryTypearray)  && 0 < count($salaryTypearray)) ?   $salaryTypearray:[];
            $success['job_type']        = (isset($JobTypesarray)    && 0 < count($JobTypesarray))   ?   $JobTypesarray  :[];
            $success['categories']		= (isset($categoryarray)    && 0 < count($categoryarray))	?	$categoryarray	:[];
            $success['features_company']= (isset($companyarray)     && 0 < count($companyarray))	?	$companyarray	:[];
            $success['features_job']	= [];
            $jobsarray  =   Jobs::select("jobs.*","employers.email","company.logo", "company.name",'job_currency.amount as salary_amount')
                                ->join('employers', 'employers.id', '=', 'jobs.id_employer')
                                ->join('company', 'company.id', '=', 'jobs.id_company')
                                ->join('job_currency', 'job_currency.id_job', '=', 'jobs.id')
                                ->where('jobs.is_featured', 1)
                                ->where('job_currency.id_currency', $this->profile->default_currency)
                                ->where('jobs.status', "Active")
                                ->groupBy('jobs.id')
                                ->orderby('jobs.id','desc')
                                ->limit(10)
                                ->get()
                                ->toArray();


            if (!empty($jobsarray)) {
                $cities_array       = Cities::getAll();
                $country_array      = Country::getAll();
                $industry_array     = Industry::getAll();
                $jobtypes_array     = JobTypes::getAll();
                $salarytype_array   = SalaryType::getAll();
                $category_array     = Category::getAll();
                $candidate_jobs     = CandidateJob::getAllByid($this->profile->id);
                $candidate_wishlist = CandidateWishlist::getAllByid($this->profile->id);
                foreach ($jobsarray as $key => $value) {
                    $id_city        = isset($value['id_city'])      ? $value['id_city']     : 0;
                    $id_country     = isset($value['id_country'])   ? $value['id_country']  : 0;
                    $id_industry    = isset($value['id_industry'])  ? $value['id_industry'] : 0;
                    $id_job_type    = isset($value['id_job_type'])  ? $value['id_job_type'] : 0;
                    $id_salary_type    = isset($value['id_salary_type'])  ? $value['id_salary_type'] : 0;
                    $id_category    = isset($value['id_category'])  ? $value['id_category'] : 0;

                    $success['features_job'][$key]['id']            = $value['id'];
                    $success['features_job'][$key]['id_employer']   = $value['id_employer'];
                    $success['features_job'][$key]['email']         = $value['email'];
                    $success['features_job'][$key]['id_company']    = $value['id_company'];
                    $success['features_job'][$key]['logo']          = $value['logo'];
                    $success['features_job'][$key]['name']          = $value['name'];
                    $success['features_job'][$key]['id_job_type']   = $value['id_job_type'];
                    $success['features_job'][$key]['job_type']      = isset($jobtypes_array[$id_job_type]['name']) ? $jobtypes_array[$id_job_type]['name'] : '';
                    $success['features_job'][$key]['id_category']   = $value['id_category'];
                    $success['features_job'][$key]['category']      = isset($category_array[$id_category]['name']) ? $category_array[$id_category]['name'] : '';
                    $success['features_job'][$key]['id_salary_type']= $value['id_salary_type'];
                    $success['features_job'][$key]['job_salary_type']      = isset($salarytype_array[$id_salary_type]['name']) ? $salarytype_array[$id_salary_type]['name'] : '';
                    $success['features_job'][$key]['salary_amount'] = $value['salary_amount'];
                    $success['features_job'][$key]['title']         = $value['title'];
                    $success['features_job'][$key]['salary_type']   = $value['salary_type'];
                    $success['features_job'][$key]['people_with_code']       = $value['people_with_code'];
                    $success['features_job'][$key]['description']   = $value['description'];
                    $success['features_job'][$key]['image']         = $value['image'];
                    $success['features_job'][$key]['id_country']    = $value['id_country'];
                    $success['features_job'][$key]['id_city']       = $value['id_city'];
                    $success['features_job'][$key]['address']       = $value['address'];
                    $success['features_job'][$key]['is_featured']   = $value['is_featured'];
                    $success['features_job'][$key]['is_urgent']     = $value['is_urgent'];

                    $success['features_job'][$key]['industry']      = isset($industry_array[$id_industry]['name']) ? $industry_array[$id_industry]['name'] : '';
                    $success['features_job'][$key]['city']          = isset($cities_array[$id_city]['name']) ? $cities_array[$id_city]['name'] : '';
                    $success['features_job'][$key]['country']       = isset($country_array[$id_country]['name']) ? $country_array[$id_country]['name'] : '';
                    $success['features_job'][$key]['latitude']      = $value['latitude'];
                    $success['features_job'][$key]['longitude']     = $value['longitude'];
                    $success['features_job'][$key]['created_at']    = $value['created_at'];
                    $job_question_array = JobQuestion::select("id","name")->where("id_job",$value['id'])->get()->toArray();
                    $success['features_job'][$key]['question_list'] = isset($job_question_array) && 0 < count($job_question_array)?$job_question_array:[];
                    $success['features_job'][$key]['is_applied']    = isset($candidate_jobs[$value['id']]) && 0 < strlen($candidate_jobs[$value['id']])?1:0;;
                    $success['features_job'][$key]['is_favorite']   = (isset($candidate_wishlist[$value['id']]) && 0 < $candidate_wishlist[$value['id']]['id'])?1:0;
                    $success['features_job'][$key]['your_status']   = isset($candidate_jobs[$value['id']]) && 0 < strlen($candidate_jobs[$value['id']])?$candidate_jobs[$value['id']]:"";

                }
            }
            return response()->json([
                        'status' => $this->successStatus,
                        'message' => "success",
                        'data' => $success
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

            $companyarray   = Company::select("id", "logo", "name", "description", "website")
                                ->where('status', "Active")
                                ->where('is_featured', 1)
                                ->orderby('id','ASC')
                                ->offset($start)
                                ->limit($limit)
                                ->get()
                                ->toArray();

            return response()->json([
                        'status' => $this->successStatus,
                        'message' => "success",
                        'data' => $companyarray
                            ], $this->successStatus);

        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                Bugsnag::notifyException($ex);
            }
        }
    }

    public function job_count_get(Request $request) {
        try {
            $id_company     = $request->get("id_company");
            $id_category    = $request->get("id_category");
            $id_job_type    = $request->get("id_job_type");
            $id_salary_type = $request->get("id_salary_type");
            $salary_type    = $request->get("salary_type");
            $id_city        = $request->get("id_city");
            $text_search    = $request->get("text_search");
            $hourly_slice   = $request->get("hourly_slice");
            $hourly_slice   = (isset($hourly_slice) && 0 < strlen($hourly_slice))?explode(",", $hourly_slice):[];
            DB::enableQueryLog();
            $jobsarray  =   Jobs::select("jobs.id")
                                ->join('job_currency', 'job_currency.id_job', '=', 'jobs.id')
                                ->where(function($query) use ($id_company)  {
                                    if(isset($id_company) && 0 < (int)$id_company) {
                                        $query->where('jobs.id_company', $id_company);
                                    }
                                 })
                                ->where(function($query) use ($id_category)  {
                                    if(isset($id_category) && 0 < (int)$id_category) {
                                        $query->where('jobs.id_category', $id_category);
                                    }
                                 })
                                ->where(function($query) use ($id_job_type)  {
                                    if(isset($id_job_type) && 0 < (int)$id_job_type) {
                                        $query->where('jobs.id_job_type', $id_job_type);
                                    }
                                })
                                ->where(function($query) use ($id_salary_type)  {
                                    if(isset($id_salary_type) && 0 < (int)$id_salary_type) {
                                        $query->where('jobs.id_salary_type', $id_salary_type);
                                    }
                                })
                                ->where(function($query) use ($salary_type)  {
                                    if(isset($salary_type) && 0< strlen($salary_type)) {
                                        $query->where('jobs.salary_type', $salary_type);
                                    }
                                })
                                ->where(function($query) use ($id_city)  {
                                    if(isset($id_city) && 0 < (int)$id_city) {
                                        $query->where('jobs.id_city', $id_city);
                                    }
                                })
                                ->where(function($query) use ($text_search)  {
                                    if(isset($text_search) && 0< strlen($text_search)) {
                                        $query->where('jobs.title', 'like', '%' . $text_search . '%');
                                    }
                                })
                                ->where(function($query) use ($hourly_slice)  {
                                    if(isset($hourly_slice) && 0< count($hourly_slice)) {
                                        $query->whereBetween('job_currency.amount', $hourly_slice);
                                        $query->where('jobs.salary_type', 'Hourly');
                                       // $query->where('job_currency.id_currency', $this->profile->default_currency);
                                    }
                                })
                                ->where('job_currency.id_currency', $this->profile->default_currency)
                                ->where('jobs.status', "Active")
                                ->groupBy('jobs.id')
                                ->get()
                                ->count();
                              // $query = DB::getQueryLog();

            $statusArray["job_count"] =  $jobsarray;
            return response()->json([
                'status' => 200,
                'message' => trans('messages.job_list'),
                'data' => $statusArray
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        }
    }
    public function job_get(Request $request) {
        try {
            $start  = $request->get("start");
            $limit  = $request->get("limit");
            $id_company     = $request->get("id_company");
            $id_category    = $request->get("id_category");
            $id_job_type    = $request->get("id_job_type");
            $id_salary_type = $request->get("id_salary_type");
            $salary_type    = $request->get("salary_type");
            $id_city        = $request->get("id_city");
            $text_search    = $request->get("text_search");
            $hourly_slice   = $request->get("hourly_slice");
            $is_featured    = $request->get("is_featured");
            $hourly_slice   = (isset($hourly_slice) && 0 < strlen($hourly_slice))?explode(",", $hourly_slice):[];
            $start = (isset($start) && !empty($start))?$start:0;
            $start  = (($start - 1) * $limit);
            $limit = (isset($limit) && !empty($limit)) ? $limit : 10;

            $jobsarray  =   Jobs::select("jobs.*","employers.email","company.logo", "company.name",'job_currency.amount as salary_amount')
                                ->join('employers', 'employers.id', '=', 'jobs.id_employer')
                                ->join('company', 'company.id', '=', 'jobs.id_company')
                                ->join('job_currency', 'job_currency.id_job', '=', 'jobs.id')
                                ->where(function($query) use ($id_company)  {
                                    if(isset($id_company)) {
                                        $query->where('jobs.id_company', $id_company);
                                    }
                                 })
                                ->where(function($query) use ($id_category)  {
                                    if(isset($id_category) && 0 < (int)$id_category) {
                                        $query->where('jobs.id_category', $id_category);
                                    }
                                 })
                                ->where(function($query) use ($id_job_type)  {
                                    if(isset($id_job_type) && 0 < (int)$id_job_type) {
                                        $query->where('jobs.id_job_type', $id_job_type);
                                    }
                                })
                                ->where(function($query) use ($id_salary_type)  {
                                    if(isset($id_salary_type) && 0 < (int)$id_salary_type) {
                                        $query->where('jobs.id_salary_type', $id_salary_type);
                                    }
                                })
                                ->where(function($query) use ($salary_type)  {
                                    if(isset($salary_type) && 0 < strlen($salary_type)) {
                                        $query->where('jobs.salary_type', $salary_type);
                                    }
                                })
                                ->where(function($query) use ($id_city)  {
                                    if(isset($id_city) && 0 < (int)$id_city) {
                                        $query->where('jobs.id_city', $id_city);
                                    }
                                })
                                ->where(function($query) use ($text_search)  {
                                    if(isset($text_search) && 0 < strlen($text_search)) {
                                        $query->where('jobs.title', 'like', '%' . $text_search . '%');
                                    }
                                })
                                ->where(function($query) use ($is_featured)  {
                                    if(isset($is_featured) && 0 < (int)$is_featured) {
                                        $query->where('jobs.is_featured', 1);
                                    }
                                })
                                ->where(function($query) use ($hourly_slice)  {
                                    if(isset($hourly_slice) && 0< count($hourly_slice)) {
                                        $query->whereBetween('job_currency.amount', $hourly_slice);
                                        $query->where('jobs.salary_type', 'Hourly');
                                        // $query->where('job_currency.id_currency', $this->profile->default_currency);
                                    }
                                })
                                ->where('job_currency.id_currency', $this->profile->default_currency)
                                ->where('jobs.status', "Active")
                                ->groupBy('jobs.id')
                                ->orderby('jobs.id','desc')
                                ->offset($start)
                                ->limit($limit)
                                ->get()
                                ->toArray();

            $statusArray = [];
            if (!empty($jobsarray)) {
                $cities_array       = Cities::getAll();
                $country_array      = Country::getAll();
                $industry_array     = Industry::getAll();
                $jobtypes_array     = JobTypes::getAll();
                $salarytype_array   = SalaryType::getAll();
                $category_array     = Category::getAll();
                $candidate_jobs     = CandidateJob::getAllByid($this->profile->id);
                $candidate_wishlist = CandidateWishlist::getAllByid($this->profile->id);
                foreach ($jobsarray as $key => $value) {
                    $id_city        = isset($value['id_city'])      ? $value['id_city']     : 0;
                    $id_country     = isset($value['id_country'])   ? $value['id_country']  : 0;
                    $id_industry    = isset($value['id_industry'])  ? $value['id_industry'] : 0;
                    $id_job_type    = isset($value['id_job_type'])  ? $value['id_job_type'] : 0;
                    $id_salary_type    = isset($value['id_salary_type'])  ? $value['id_salary_type'] : 0;
                    $id_category    = isset($value['id_category'])  ? $value['id_category'] : 0;

                    $statusArray[$key]['id']            = $value['id'];
                    $statusArray[$key]['id_employer']   = $value['id_employer'];
                    $statusArray[$key]['email']         = $value['email'];
                    $statusArray[$key]['id_company']    = $value['id_company'];
                    $statusArray[$key]['logo']          = $value['logo'];
                    $statusArray[$key]['name']          = $value['name'];
                    $statusArray[$key]['id_job_type']   = $value['id_job_type'];
                    $statusArray[$key]['job_type']      = isset($jobtypes_array[$id_job_type]['name']) ? $jobtypes_array[$id_job_type]['name'] : '';
                    $statusArray[$key]['id_category']   = $value['id_category'];
                    $statusArray[$key]['category']      = isset($category_array[$id_category]['name']) ? $category_array[$id_category]['name'] : '';
                    $statusArray[$key]['id_salary_type']= $value['id_salary_type'];
                    $statusArray[$key]['job_salary_type']      = isset($salarytype_array[$id_salary_type]['name']) ? $salarytype_array[$id_salary_type]['name'] : '';
                    $statusArray[$key]['salary_amount'] = $value['salary_amount'];
                    $statusArray[$key]['title']         = $value['title'];
                    $statusArray[$key]['salary_type']   = $value['salary_type'];
                    $statusArray[$key]['people_with_code']       = $value['people_with_code'];
                    $statusArray[$key]['description']   = $value['description'];
                    $statusArray[$key]['image']         = $value['image'];
                    $statusArray[$key]['id_country']    = $value['id_country'];
                    $statusArray[$key]['id_city']       = $value['id_city'];
                    $statusArray[$key]['address']       = $value['address'];
                    $statusArray[$key]['is_featured']   = $value['is_featured'];
                    $statusArray[$key]['is_urgent']     = $value['is_urgent'];

                    $statusArray[$key]['industry']      = isset($industry_array[$id_industry]['name']) ? $industry_array[$id_industry]['name'] : '';
                    $statusArray[$key]['city']          = isset($cities_array[$id_city]['name']) ? $cities_array[$id_city]['name'] : '';
                    $statusArray[$key]['country']       = isset($country_array[$id_country]['name']) ? $country_array[$id_country]['name'] : '';
                    $statusArray[$key]['latitude']      = $value['latitude'];
                    $statusArray[$key]['longitude']     = $value['longitude'];
                    $statusArray[$key]['created_at']    = $value['created_at'];
                    $job_question_array = JobQuestion::select("id","name")->where("id_job",$value['id'])->get()->toArray();
                    $statusArray[$key]['question_list'] = isset($job_question_array) && 0 < count($job_question_array)?$job_question_array:[];
                    $statusArray[$key]['is_applied']    = isset($candidate_jobs[$value['id']]) && 0 < strlen($candidate_jobs[$value['id']])?1:0;;
                    $statusArray[$key]['is_favorite']   = (isset($candidate_wishlist[$value['id']]) && 0 < $candidate_wishlist[$value['id']]['id'])?1:0;
                    $statusArray[$key]['your_status']   = isset($candidate_jobs[$value['id']]) && 0 < strlen($candidate_jobs[$value['id']])?$candidate_jobs[$value['id']]:"";

                }
            }
            return response()->json([
                'status' => 200,
                'message' => trans('messages.job_list'),
                'data' => $statusArray
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        }
    }

    public function job_get_bycode(Request $request) {
        try {
            $rules = [
                'code' => 'required',
            ];
            $messages = [
                'code.required' => trans('messages.code_required'),
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
            $code     = $request->get("code");

            $jobsarray  =   Jobs::select("jobs.*","employers.email","company.logo", "company.name")
                                ->join('employers', 'employers.id', '=', 'jobs.id_employer')
                                ->join('company', 'company.id', '=', 'jobs.id_company')
                                ->where('jobs.unique_id', $code)
                                ->where('jobs.status', "Active")
                                ->get()
                                ->toArray();

            $statusArray = [];
            if (!empty($jobsarray)) {
                $cities_array       = Cities::getAll();
                $country_array      = Country::getAll();
                $industry_array     = Industry::getAll();
                $jobtypes_array     = JobTypes::getAll();
                $salarytype_array   = SalaryType::getAll();
                $category_array     = Category::getAll();
                $candidate_jobs     = CandidateJob::getAllByid($this->profile->id);
                foreach ($jobsarray as $key => $value) {
                    $id_city        = isset($value['id_city'])      ? $value['id_city']     : 0;
                    $id_country     = isset($value['id_country'])   ? $value['id_country']  : 0;
                    $id_industry    = isset($value['id_industry'])  ? $value['id_industry'] : 0;
                    $id_job_type    = isset($value['id_job_type'])  ? $value['id_job_type'] : 0;
                    $id_salary_type    = isset($value['id_salary_type'])  ? $value['id_salary_type'] : 0;
                    $id_category    = isset($value['id_category'])  ? $value['id_category'] : 0;

                    $statusArray['id']            = $value['id'];
                    $statusArray['id_employer']   = $value['id_employer'];
                    $statusArray['email']         = $value['email'];
                    $statusArray['id_company']    = $value['id_company'];
                    $statusArray['logo']          = $value['logo'];
                    $statusArray['name']          = $value['name'];
                    $statusArray['id_job_type']   = $value['id_job_type'];
                    $statusArray['job_type']      = isset($jobtypes_array[$id_job_type]['name']) ? $jobtypes_array[$id_job_type]['name'] : '';
                    $statusArray['id_category']   = $value['id_category'];
                    $statusArray['category']      = isset($category_array[$id_category]['name']) ? $category_array[$id_category]['name'] : '';
                    $statusArray['id_salary_type']= $value['id_salary_type'];
                    $statusArray['job_salary_type']      = isset($salarytype_array[$id_salary_type]['name']) ? $salarytype_array[$id_salary_type]['name'] : '';
                    $statusArray['salary_amount'] = $value['salary_amount'];
                    $statusArray['title']         = $value['title'];
                    $statusArray['salary_type']   = $value['salary_type'];
                    $statusArray['people_with_code']       = $value['people_with_code'];
                    $statusArray['description']   = $value['description'];
                    $statusArray['image']         = $value['image'];
                    $statusArray['id_country']    = $value['id_country'];
                    $statusArray['id_city']       = $value['id_city'];
                    $statusArray['address']       = $value['address'];
                    $statusArray['is_featured']   = $value['is_featured'];
                    $statusArray['is_urgent']     = $value['is_urgent'];

                    $statusArray['industry']      = isset($industry_array[$id_industry]['name']) ? $industry_array[$id_industry]['name'] : '';
                    $statusArray['city']          = isset($cities_array[$id_city]['name']) ? $cities_array[$id_city]['name'] : '';
                    $statusArray['country']       = isset($country_array[$id_country]['name']) ? $country_array[$id_country]['name'] : '';
                    $statusArray['latitude']      = $value['latitude'];
                    $statusArray['longitude']     = $value['longitude'];
                    $statusArray['created_at']    = $value['created_at'];
                    $job_question_array = JobQuestion::select("id","name")->where("id_job",$value['id'])->get()->toArray();
                    $statusArray['question_list'] = isset($job_question_array) && 0 < count($job_question_array)?$job_question_array:[];
                    $statusArray['is_applied']    = isset($candidate_jobs[$value['id']]) && 0 < strlen($candidate_jobs[$value['id']])?1:0;;
                    $statusArray['your_status']   = isset($candidate_jobs[$value['id']]) && 0 < strlen($candidate_jobs[$value['id']])?$candidate_jobs[$value['id']]:"";

                }
            }
            return response()->json([
                'status' => 200,
                'message' => trans('messages.job_list'),
                'data' => $statusArray
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        }
    }

    public function job_apply(Request $request) {
        try {
            $rules = [
                'id_job' => 'required',
            ];
            $messages = [
                'id_job.required' => trans('messages.id_job_required'),
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

            $id_job         = $request->id_job;
            $jobs_exist = Jobs::where('id',$id_job)
                                    ->where('status', "Active")
                                    ->get()->first();
            if (!$jobs_exist) {
                return response()->json([
                            'status' => 400,
                            'message' =>  trans('messages.job_not_exist'),
                            'errors' =>  trans('messages.job_not_exist'),
                                ], $this->successStatus);
            }
            $jobs_applied_exist = CandidateJob::select('id')
                                            ->where("id_job",$id_job)
                                            ->where("id_user",$this->profile->id)
                                            ->get()->first();
            if ($jobs_applied_exist) {
                return response()->json([
                            'status' => 400,
                            'message' =>  trans('messages.jobs_applied'),
                            'errors' =>  trans('messages.jobs_applied'),
                                ], $this->successStatus);
            }
            $candidate_job = new CandidateJob();
            $candidate_job->id_user =   $this->profile->id;
            $candidate_job->id_job  =   $id_job;
            $candidate_job->status  =   "Applied";
            $candidate_job->save();

            $message_noti = $this->profile->name . ' Applied On your job';

            $notification = ['id_from' => $this->profile->id, 'id_to' => $jobs_exist['id_employer'],'id_job'=>$id_job, 'from_type' => "Candidate", 'message' => $message_noti];
            #this is for add notification in db
            Notification::notificationAdd($notification);
            #sent to employer
            pushNotification($jobs_exist['id_employer'], $message_noti,1);

            return response()->json([
                        'status' => $this->successStatus,
                        'message' => trans('messages.apply_job'),
                        'data' => []
                            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                Bugsnag::notifyException($ex);
            }
        }
    }

    public function job_interview(Request $request) {
        try {

            $rules = [
                'id_candidate_job' => 'required',
                'id_job' => 'required',
                'question_json' => 'required',
            ];
            $messages = [
                'id_candidate_job.required' => trans('messages.id_candidate_job_required'),
                'id_job.required' => trans('messages.id_job_required'),
                'question_json.required' => trans('messages.question_json_required'),
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
            $id_candidate_job= $request->id_candidate_job;
            $id_job          = $request->id_job;
            $question_json  = $request->question_json;
            $question_json  = json_decode($question_json,true);
            $jobs_exist = Jobs::where('id',$id_job)
                                    ->where('status', "Active")
                                    ->get()->first();
            if (!$jobs_exist) {
                return response()->json([
                            'status' => 400,
                            'message' =>  trans('messages.job_not_exist'),
                            'errors' =>  trans('messages.job_not_exist'),
                                ], $this->successStatus);
            }
            if ($id_candidate_job == 0) {
                $c_job = new CandidateJob();
                $c_job->id_user =   $this->profile->id;
                $c_job->id_job  =   $id_job;
                $c_job->status  =   "Selected";
                $c_job->save();
                $id_candidate_job = $c_job->id;
            }
            $candidate_job     = CandidateJob::where("status","Selected")->where("id",$id_candidate_job)->get()->first();
            if((isset($candidate_job->id) &&  0 < $candidate_job->id) && 0 < count($question_json)){
                $candidate_job->status= "Interviewed";
                $candidate_job->save();
                foreach ($question_json as $key => $value) {
                   $charges[] = [
                        'id_candidate_job'  => $candidate_job->id,
                        'id_job_question'   => $value['id'],
                        'video_file'        => $value['video_file'],
                        'video_thumb'       => $value['video_thumb'],
                    ];
                }
                JobQuestionAnswer::insert($charges);
                #this is for add notification in db
                $message_noti = $this->profile->name . ' has interviewed On your job';
                $notification = ['id_from' => $this->profile->id, 'id_to' => $jobs_exist['id_employer'],'id_job'=>$id_job, 'from_type' => "Candidate", 'message' => $message_noti];
                Notification::notificationAdd($notification);
                #sent to employer
                pushNotification($jobs_exist['id_employer'], $message_noti,2);

                return response()->json([
                            'status' => $this->successStatus,
                            'message' => trans('messages.job_status_changed'),
                            'data' => []
                                ], $this->successStatus);
            }else{
                return response()->json([
                            'status' => 400,
                            'message' =>  trans('messages.id_candidate_job_not_exist'),
                            'errors' =>  trans('messages.id_candidate_job_not_exist'),
                                ], $this->successStatus);
            }
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                Bugsnag::notifyException($ex);
            }
        }
    }

    public function applied_jobs_get(Request $request) {
        try {
            $start  = $request->get("start");
            $limit  = $request->get("limit");

            $start = (isset($start) && !empty($start))?$start:0;
            $start  = (($start - 1) * $limit);
            $limit = (isset($limit) && !empty($limit)) ? $limit : 10;
            $jobsarray   = CandidateJob::select("candidate_job.id as id_candidate_job","candidate_job.id_job","candidate_job.id_user","candidate_job.status","candidate_job.status_comment","candidate_job.created_at","candidate_job.updated_at","jobs.id","jobs.id_employer","jobs.id_company","jobs.title","jobs.id_job_type","jobs.id_category","jobs.id_salary_type","jobs.salary_type","jobs.salary_amount","jobs.description","jobs.people_with_code","jobs.image","jobs.id_country","jobs.id_city","jobs.address","jobs.is_featured","jobs.is_urgent","jobs.latitude","jobs.longitude")
                                ->join('jobs', 'jobs.id', '=', 'candidate_job.id_job')
                                ->where('candidate_job.id_user', $this->profile->id)
                                ->orderby('candidate_job.id','desc')
                                ->offset($start)
                                ->limit($limit)
                                ->get()
                                ->toArray();
                                // print_r($jobsarray);
                                // exit;
            $statusArray = [];
            $appliedProfile = [];
            $shortlistedProfile = [];
            $selectedProfile = [];
            if (!empty($jobsarray)) {
                $cities_array       = Cities::getAll();
                $country_array      = Country::getAll();
                $industry_array     = Industry::getAll();
                $jobtypes_array     = JobTypes::getAll();
                $salarytype_array   = SalaryType::getAll();
                $category_array     = Category::getAll();
                foreach ($jobsarray as $key => $value) {
                    $candidate_jobs     = CandidateJob::getAllByid($this->profile->id);
                    $candidate_wishlist = CandidateWishlist::getAllByid($this->profile->id);
                    $companyDetail = Company::select("logo","name")->where('id', $value['id_company'])->get()->first();
                    $employerDetail = Employer::select("email")->where('id', $value['id_employer'])->get()->first();
                    $jobqueansDetail = JobQuestionAnswer::select('candidate_job_question_answer.id', 'candidate_job_question_answer.id_candidate_job', 'candidate_job_question_answer.id_job_question', 'candidate_job_question_answer.video_file', 'candidate_job_question_answer.video_thumb', 'candidate_job_question_answer.created_at', 'job_question.name')
                        ->join('job_question', 'job_question.id', '=', 'candidate_job_question_answer.id_job_question')
                        ->where('id_candidate_job', $value['id_candidate_job'])->get()->toArray();
                    $id_city        = isset($value['id_city'])      ? $value['id_city']     : 0;
                    $id_country     = isset($value['id_country'])   ? $value['id_country']  : 0;
                    $id_industry    = isset($value['id_industry'])  ? $value['id_industry'] : 0;
                    $id_job_type    = isset($value['id_job_type'])  ? $value['id_job_type'] : 0;
                    $id_salary_type    = isset($value['id_salary_type'])  ? $value['id_salary_type'] : 0;
                    $id_category    = isset($value['id_category'])  ? $value['id_category'] : 0;

                    $statusArray[$key]['id_candidate_job']            = $value['id_candidate_job'];
                    $statusArray[$key]['id_user']       = $value['id_user'];

                    $statusArray[$key]['your_status']   = $value['status'];
                    $statusArray[$key]['status_comment']= $value['status_comment'];
                    $statusArray[$key]['created_at']    = $value['created_at'];
                    $statusArray[$key]['id_job']        = $value['id'];

                    $statusArray[$key]['id_employer']   = $value['id_employer'];
                    $statusArray[$key]['email']         = isset($employerDetail['email'])?$employerDetail['email']:"";;
                    $statusArray[$key]['id_company']    = $value['id_company'];
                    $statusArray[$key]['logo']          = isset($companyDetail['logo'])?$companyDetail['logo']:"";
                    $statusArray[$key]['name']          = isset($companyDetail['name'])?$companyDetail['name']:"";

                    $statusArray[$key]['title']         = $value['title'];
                    $statusArray[$key]['id_job_type']   = $value['id_job_type'];
                    $statusArray[$key]['job_type']      = isset($jobtypes_array[$id_job_type]['name']) ? $jobtypes_array[$id_job_type]['name'] : '';
                    $statusArray[$key]['id_category']   = $value['id_category'];
                    $statusArray[$key]['category']      = isset($category_array[$id_category]['name']) ? $category_array[$id_category]['name'] : '';
                    $statusArray[$key]['id_salary_type']= $value['id_salary_type'];
                    $statusArray[$key]['job_salary_type']      = isset($salarytype_array[$id_salary_type]['name']) ? $salarytype_array[$id_salary_type]['name'] : '';
                    $statusArray[$key]['salary_type']   = $value['salary_type'];
                    $statusArray[$key]['salary_amount'] = $value['salary_amount'];
                    $statusArray[$key]['people_with_code']       = $value['people_with_code'];
                    $statusArray[$key]['description']   = $value['description'];
                    $statusArray[$key]['image']         = $value['image'];
                    $statusArray[$key]['id_country']    = $value['id_country'];
                    $statusArray[$key]['id_city']       = $value['id_city'];
                    $statusArray[$key]['address']       = $value['address'];
                    $statusArray[$key]['is_featured']   = $value['is_featured'];
                    $statusArray[$key]['is_urgent']     = $value['is_urgent'];

                    $statusArray[$key]['city']          = isset($cities_array[$id_city]['name']) ? $cities_array[$id_city]['name'] : '';
                    $statusArray[$key]['country']       = isset($country_array[$id_country]['name']) ? $country_array[$id_country]['name'] : '';
                    $statusArray[$key]['latitude']      = $value['latitude'];
                    $statusArray[$key]['longitude']     = $value['longitude'];
                    $statusArray[$key]['is_applied']    = 1;
                    $statusArray[$key]['is_favorite']   = (isset($candidate_wishlist[$value['id']]) && 0 < $candidate_wishlist[$value['id']]['id'])?1:0;

                    $job_question_array = JobQuestion::select("id","name")->where("id_job",$value['id'])->get()->toArray();
                    $statusArray[$key]['question_list'] = isset($job_question_array) && 0 < count($job_question_array)?$job_question_array:[];
                    $statusArray[$key]['job_question_answer']     = $jobqueansDetail;

                    // $statusArray[$key]['applied_list']  = $appliedProfile;
                    // $statusArray[$key]['sort_list']     = $shortlistedProfile;
                    // $statusArray[$key]['finalize_list'] = $selectedProfile;

                }
            }

            return response()->json([
                'status' => 200,
                'message' => trans('messages.job_list'),
                'data' => $statusArray
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        }
    }

    public function job_wishlist(Request $request) {
        try {
            $rules = [
                'id_job' => 'required',
            ];
            $messages = [
                'id_job.required' => trans('messages.id_job_required'),
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

            $jobs_id         = $request->id_job;
            $jobs_exist = Jobs::where('id',$jobs_id)
                                    ->where('status', "Active")
                                    ->get()->first();
            if (!$jobs_exist) {
                return response()->json([
                            'status' => 400,
                            'message' =>  trans('messages.job_not_exist'),
                            'errors' =>  trans('messages.job_not_exist'),
                                ], $this->successStatus);
            }
            $jobs_wishlist_exist = CandidateWishlist::select('id')
                                            ->where("jobs_id",$jobs_id)
                                            ->where("users_id",$this->profile->id)
                                            ->get()->first();
            if ($jobs_wishlist_exist) {
                CandidateWishlist::where("jobs_id",$jobs_id)
                                ->where("users_id",$this->profile->id)
                                ->delete();
                $message = trans('messages.wishlist_removed');
                $data['is_favorite'] = 0;
            }else{
                $candidate_job = new CandidateWishlist();
                $candidate_job->users_id =   $this->profile->id;
                $candidate_job->jobs_id  =   $jobs_id;
                $candidate_job->save();
                $message = trans('messages.wishlist_added');
                $data['is_favorite'] = 1;
            }
            return response()->json([
                        'status' => $this->successStatus,
                        'message' => $message,
                        'data' => $data
                            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                Bugsnag::notifyException($ex);
            }
        }
    }

    public function wishlist_jobs_get(Request $request) {
        try {
            $start  = $request->get("start");
            $limit  = $request->get("limit");

            $start = (isset($start) && !empty($start))?$start:0;
            $start  = (($start - 1) * $limit);
            $limit = (isset($limit) && !empty($limit)) ? $limit : 10;
            $jobsarray   = CandidateWishlist::select("candidate_wishlist.id as id_wishlist","candidate_wishlist.jobs_id","candidate_wishlist.users_id","candidate_wishlist.created_at","candidate_wishlist.updated_at","jobs.id","jobs.id_employer","jobs.id_company","jobs.title","jobs.id_job_type","jobs.id_category","jobs.id_salary_type","jobs.salary_type","jobs.salary_amount","jobs.description","jobs.people_with_code","jobs.image","jobs.id_country","jobs.id_city","jobs.address","jobs.is_featured","jobs.is_urgent","jobs.latitude","jobs.longitude")
                                ->join('jobs', 'jobs.id', '=', 'candidate_wishlist.jobs_id')
                                ->where('candidate_wishlist.users_id', $this->profile->id)
                                ->orderby('candidate_wishlist.id','desc')
                                ->offset($start)
                                ->limit($limit)
                                ->get()
                                ->toArray();
                                // print_r($jobsarray);
                                // exit;
            $statusArray = [];
            $appliedProfile = [];
            $shortlistedProfile = [];
            $selectedProfile = [];
            if (!empty($jobsarray)) {
                $cities_array       = Cities::getAll();
                $country_array      = Country::getAll();
                $industry_array     = Industry::getAll();
                $jobtypes_array     = JobTypes::getAll();
                $salarytype_array   = SalaryType::getAll();
                $category_array     = Category::getAll();
                foreach ($jobsarray as $key => $value) {
                    $candidate_jobs     = CandidateJob::getAllByid($this->profile->id);
                    $candidate_wishlist = CandidateWishlist::getAllByid($this->profile->id);
                    $companyDetail = Company::select("logo","name")->where('id', $value['id_company'])->get()->first();
                    $employerDetail = Employer::select("email")->where('id', $value['id_employer'])->get()->first();

                    $id_city        = isset($value['id_city'])      ? $value['id_city']     : 0;
                    $id_country     = isset($value['id_country'])   ? $value['id_country']  : 0;
                    $id_industry    = isset($value['id_industry'])  ? $value['id_industry'] : 0;
                    $id_job_type    = isset($value['id_job_type'])  ? $value['id_job_type'] : 0;
                    $id_salary_type    = isset($value['id_salary_type'])  ? $value['id_salary_type'] : 0;
                    $id_category    = isset($value['id_category'])  ? $value['id_category'] : 0;

                    $statusArray[$key]['id_wishlist']            = $value['id_wishlist'];
                    $statusArray[$key]['id_user']       = $value['users_id'];
                    $statusArray[$key]['created_at']    = $value['created_at'];
                    $statusArray[$key]['id_job']        = $value['id'];

                    $statusArray[$key]['id_employer']   = $value['id_employer'];
                    $statusArray[$key]['email']         = isset($employerDetail['email'])?$employerDetail['email']:"";;
                    $statusArray[$key]['id_company']    = $value['id_company'];
                    $statusArray[$key]['logo']          = isset($companyDetail['logo'])?$companyDetail['logo']:"";
                    $statusArray[$key]['name']          = isset($companyDetail['name'])?$companyDetail['name']:"";

                    $statusArray[$key]['title']         = $value['title'];
                    $statusArray[$key]['id_job_type']   = $value['id_job_type'];
                    $statusArray[$key]['job_type']      = isset($jobtypes_array[$id_job_type]['name']) ? $jobtypes_array[$id_job_type]['name'] : '';
                    $statusArray[$key]['id_category']   = $value['id_category'];
                    $statusArray[$key]['category']      = isset($category_array[$id_category]['name']) ? $category_array[$id_category]['name'] : '';
                    $statusArray[$key]['id_salary_type']= $value['id_salary_type'];
                    $statusArray[$key]['job_salary_type']      = isset($salarytype_array[$id_salary_type]['name']) ? $salarytype_array[$id_salary_type]['name'] : '';
                    $statusArray[$key]['salary_type']   = $value['salary_type'];
                    $statusArray[$key]['salary_amount'] = $value['salary_amount'];
                    $statusArray[$key]['people_with_code']       = $value['people_with_code'];
                    $statusArray[$key]['description']   = $value['description'];
                    $statusArray[$key]['image']         = $value['image'];
                    $statusArray[$key]['id_country']    = $value['id_country'];
                    $statusArray[$key]['id_city']       = $value['id_city'];
                    $statusArray[$key]['address']       = $value['address'];
                    $statusArray[$key]['is_featured']   = $value['is_featured'];
                    $statusArray[$key]['is_urgent']     = $value['is_urgent'];

                    $statusArray[$key]['city']          = isset($cities_array[$id_city]['name']) ? $cities_array[$id_city]['name'] : '';
                    $statusArray[$key]['country']       = isset($country_array[$id_country]['name']) ? $country_array[$id_country]['name'] : '';
                    $statusArray[$key]['latitude']      = $value['latitude'];
                    $statusArray[$key]['longitude']     = $value['longitude'];
                    $statusArray[$key]['is_applied']    = isset($candidate_jobs[$value['id']]) && 0 < strlen($candidate_jobs[$value['id']])?1:0;;
                    $statusArray[$key]['is_favorite']   = (isset($candidate_wishlist[$value['id']]) && 0 < $candidate_wishlist[$value['id']]['id'])?1:0;
                    $statusArray[$key]['your_status']   = isset($candidate_jobs[$value['id']]) && 0 < strlen($candidate_jobs[$value['id']])?$candidate_jobs[$value['id']]:"";
                    $job_question_array = JobQuestion::select("id","name")->where("id_job",$value['id'])->get()->toArray();
                    $statusArray[$key]['question_list'] = isset($job_question_array) && 0 < count($job_question_array)?$job_question_array:[];

                }
            }

            return response()->json([
                'status' => 200,
                'message' => trans('messages.job_list'),
                'data' => $statusArray
            ], $this->successStatus);
        } catch (Exception $ex) {
            if (env('APP_DEBUG') == false && env('APP_ENV') != 'local') {
                 Bugsnag::notifyException($ex);
            }
        }
    }
    public function notification_get(Request $request) {
        $start  = $request->get("start");
        $limit  = $request->get("limit");
        $start = (isset($start) && !empty($start))?$start:0;
        $start  = (($start - 1) * $limit);
        $limit = (isset($limit) && !empty($limit)) ? $limit : 10;
        $notification_array   = Notification::select("*")
                                ->where('id_from', $this->profile->id)
                                ->where('from_type', "Employer")
                                ->orderby('id','DESC')
                                ->offset($start)
                                ->limit($limit)
                                ->get()
                                ->toArray();
        $statusArray = [];
        if($notification_array){
            foreach($notification_array as $key =>$value){
                $statusArray[$key]['id']            = $value['id'];
                $statusArray[$key]['message']       = $value['message'];
                $statusArray[$key]['employer_id']   = $value['id_to'];
                $statusArray[$key]['job_id']        = $value['id_job'];
                $statusArray[$key]['job_title']     = $jobs_exist['title'];
                $statusArray[$key]['created_at']    = $value['created_at'];
            }
        }
        return response()->json([
            'status' => 200,
            'message' => trans('messages.success_list'),
            'data' => $statusArray
        ], $this->successStatus);
    }
}
