<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentReturnController extends Controller
{
    //

    public function returnUrl(Request $request)
    {
        $params = $request->all();
        Log::debug('new', $params);

        return response()->json($params);
    }
}
