<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;

class APIController extends Controller
{
    public function pushOrder($id){
        $getResults = OrderDetail::pushOrder($id);
        return response()->json(['status' => $getResults]);
    }

}
