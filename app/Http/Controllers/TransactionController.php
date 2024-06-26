<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Token;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    public function index()
    {
        return view('payment');
    }

    public function payment(Request $request)
    {
        $amount = $request->query('amount');
        $transactionNo = $request->query('orderNumber'); // TXN00000001 or no need
        $merchantId = $request->query('merchantId'); // MID000001
        $merchantClientId = $request->query('userId'); // Merchant client user id
        $depositType = $request->query('depositType'); //0 ? 1

        if (empty($request->all())) {
           
            return Inertia::render('Welcome');

        } else if ($request->merchantId && $request->merchantId && $request->orderNumber && $request->userId && $request->vCode) {
            
            $validTime = 15; //minutes
            $now = Carbon::now();

            $transaction = Transaction::create([
                'merchant_id' => $merchantId,
                'client_id' => $merchantClientId,
                'transaction_type' => 'deposit',
                'payment_method' => $depositType == 1 ? 'auto' : 'manual',
                'status' => 'pending',
                'amount' => $amount,
                'transaction_number' => $transactionNo,

            ]);

            // if (!$request->session()->has('payment_expiration_time')) {
            //     // Calculate the expiration time as now + validTime minutes
            //     $expirationTime = $now->copy()->addMinutes($validTime);
        
            //     // Store the expiration time in the session
            //     $request->session()->put('payment_expiration_time', $expirationTime);
            // } else {
            //     // Retrieve the expiration time from the session
            //     $expirationTime = Carbon::parse($request->session()->get('payment_expiration_time'));
            // }

            // if ($now >= $expirationTime) {
            //     return Inertia::render('Timeout');
            // } else {

            //     $merchant = Merchant::where('id', $merchantId)->with(['merchantWalletAddress.walletAddress'])->first();
            //     $merchantClientId = $request->userId;
    
            //     if($merchant->deposit_type == 0 ) {

            //         return Inertia::render('Manual/ValidPayment', [
            //             'merchant' => $merchant,
            //             'merchantClientId' => $merchantClientId, //userid
            //             'vCode' => $request->vCode, //vCode
            //             'orderNumber' => $request->orderNumber, //orderNumber
            //             'expirationTime' => $expirationTime
            //         ]);
            //     } else if ($merchant->deposit_type == 1) {
    
            //         return Inertia::render('Auto/ValidPayment', [
            //             'merchant' => $merchant,
            //         ]);
    
            //     }

            // }

            $merchant = Merchant::where('id', $merchantId)->with(['merchantWalletAddress.walletAddress'])->first();
                $merchantClientId = $request->userId;
    
                if($merchant->deposit_type == 0 ) {

                    return Inertia::render('Manual/ValidPayment', [
                        'merchant' => $merchant,
                        'merchantClientId' => $merchantClientId, //userid
                        'vCode' => $request->vCode, //vCode
                        'orderNumber' => $request->orderNumber, //orderNumber
                        // 'expirationTime' => $expirationTime
                    ]);
                } else if ($merchant->deposit_type == 1) {
    
                    return Inertia::render('Auto/ValidPayment', [
                        'merchant' => $merchant,
                    ]);
    
                }
        }
        
    }

    // public function deposit(Request $request)
    // {
    //     dd($request->all());

    //     $transaction = Transaction::create([
    //         'merchant_id' => $request->
    //     ]);

    //     return redirect()->back();
    // }

    public function updateClientTransaction(Request $request)
    {
        
        $datas = $request->all();
        
        $merchant = Merchant::where('id', $request->merchantId)->with(['merchantWalletAddress.walletAddress'])->first();
        
        $transactionData = $request->input('datas.transactions');
        
        dd($datas);
        // $transaction = Transaction::create([
        //     'merchant_id' => $request->merchantId,
        //     'client_id' => $request->userId,
        //     'transaction_type' => 'deposit',
        //     'to_wallet' => $request->to_wallet,
        //     'txID' => $request->txid,
        //     'amount' => $request->amount,
        //     'payment_method' => $merchant->deposit_type == 1 ? 'Auto' : 'Manual',
        //     'status' => 'pending',
        //     'fee' => 0.00,
        //     'total_amount' => 0.00,
        // ]);

        // return Inertia::render('Manual/ReturnPayment', [
        //     'datas' => $datas,
        //     'total_amount' => $total_amount,
        // ]);
        return redirect(route('returnTransaction'));
    }

    public function returnTransaction(Request $request)
    {
        // dd($request->all());
        // $status = $request->query('status');
        // $transactionId = $request->query('transaction_id');

        return Inertia::render('Manual/ReturnPayment');
    }

    public function returnUrl(Request $request)
    {
        
        $amount = $request->amount;
        $payoutSetting = config('payment-gateway');
        $domain = $_SERVER['HTTP_HOST'];
        $paymentGateway = config('payment-gateway');
        $intAmount = intval($amount * 100);

        if ($domain === 'login.metafinx.com') {
            $selectedPayout = $payoutSetting['live'];
        } else {
            $selectedPayout = $payoutSetting['staging'];
        }

        $vCode = md5($intAmount . $selectedPayout['appId'] . $selectedPayout['merchantId']);

        $params = [
            'amount' => $intAmount,
            'orderNumber' => $request->orderNumber,
            'userId' => $request->merchantClientId,
            'merchantId' => $selectedPayout['merchantId'],
            'vCode' => $vCode,
            'receipt' => $request->receipt,
            'to_wallet' => $request->to_wallet,
            'txid' => $request->txid,
            'vCode' => $request->vCode,
            'status' => 'pending',
            'total_amount' => $request->total_amount,
        ];

        $url = $selectedPayout['paymentUrl'] . 'testing_payment/' . 'depositReturn';
        $redirectUrl = $url . "?" . http_build_query($params);

        return Inertia::location($redirectUrl);
    }

    public function sessionTimeOut()
    {

        return Inertia::render('Timeout');
    }

    public function returnSession(Request $request)
    {
        dd($request->all());
        $request->session()->flush();

        $payoutSetting = config('payment-gateway');
        $domain = $_SERVER['HTTP_HOST'];

        if ($domain === 'login.metafinx.com') {
            $selectedPayout = $payoutSetting['live'];
        } else {
            $selectedPayout = $payoutSetting['staging'];
        }

        $url = $selectedPayout['paymentUrl'] . 'dashboard';
        $redirectUrl = $url ;

        return Inertia::location($redirectUrl);
    }
}
