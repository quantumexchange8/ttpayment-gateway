<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Token;
use App\Models\Transaction;
use App\Notifications\TransactionNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
        $amount = $request->query('amount') / 1000000;
        $transactionNo = $request->query('orderNumber'); // TXN00000001 or no need
        $merchantId = $request->query('merchantId'); // MID000001
        $merchantClientId = $request->query('userId'); // Merchant client user id

        if (empty($request->all())) {
           
            return Inertia::render('Welcome');

        } else if ($request->merchantId && $request->merchantId && $request->orderNumber && $request->userId && $request->vCode) {
            
            $validTime = 15; //minutes
            $now = Carbon::now();

            if (!$request->session()->has('payment_expiration_time')) {
                // Calculate the expiration time as now + validTime minutes
                $expirationTime = $now->copy()->addMinutes($validTime);
        
                // Store the expiration time in the session
                $request->session()->put('payment_expiration_time', $expirationTime);
            } else {
                // Retrieve the expiration time from the session
                $expirationTime = Carbon::parse($request->session()->get('payment_expiration_time'));
            }

            if ($now >= $expirationTime) {
                return Inertia::render('Timeout');
            } else {

                $merchant = Merchant::where('id', $merchantId)->with(['merchantWalletAddress.walletAddress'])->first();
                $merchantClientId = $request->userId;
    
                if($merchant->deposit_type == 0 ) {

                    $transaction = Transaction::create([
                        'merchant_id' => $merchantId,
                        'client_id' => $merchantClientId,
                        'transaction_type' => 'deposit',
                        'payment_method' => 'manual',
                        'status' => 'pending',
                        'amount' => $amount,
                        'transaction_number' => $transactionNo,
                    ]);

                    return Inertia::render('Manual/ValidPayment', [
                        'merchant' => $merchant,
                        'merchantClientId' => $merchantClientId, //userid
                        'vCode' => $request->vCode, //vCode
                        'orderNumber' => $request->orderNumber, //orderNumber
                        'expirationTime' => $expirationTime,
                        'transaction' => $transaction,
                    ]);
                } else if ($merchant->deposit_type == 1) {

                    $transaction = Transaction::create([
                        'merchant_id' => $merchantId,
                        'client_id' => $merchantClientId,
                        'transaction_type' => 'deposit',
                        'payment_method' => 'auto',
                        'status' => 'pending',
                        'amount' => $amount,
                        'transaction_number' => $transactionNo,
                    ]);
    
                    return Inertia::render('Auto/ValidPayment', [
                        'merchant' => $merchant,
                        'amount' => $amount,
                        'expirationTime' => $expirationTime,
                        'transaction' => $transaction,
                    ]);
                }

            }

            // $merchant = Merchant::where('id', $merchantId)->with(['merchantWalletAddress.walletAddress'])->first();
            // $merchantClientId = $request->userId;

            // if($merchant->deposit_type == 0 ) {

            //     return Inertia::render('Manual/ValidPayment', [
            //         'merchant' => $merchant,
            //         'merchantClientId' => $merchantClientId, //userid
            //         'vCode' => $request->vCode, //vCode
            //         'orderNumber' => $request->orderNumber, //orderNumber
            //         // 'expirationTime' => $expirationTime
            //     ]);
            // } else if ($merchant->deposit_type == 1) {

            //     return Inertia::render('Auto/ValidPayment', [
            //         'merchant' => $merchant,
            //         // 'transaction' => $transaction->id,
            //     ]);

            // }
        }
        
    }

    public function updateClientTransaction(Request $request)
    {
        dd($request->all());
        $datas = $request->all();
        
        $merchant = Merchant::where('id', $request->merchantId)->with(['merchantWalletAddress.walletAddress', 'merchantEmail'])->first();
        
        if ($merchant->deposit_type === 1) {
            $transactionData = $request->latestTransaction;
            $transaction = Transaction::find($request->transaction);
            $nowDateTime = Carbon::now();
            $amount = $transactionData['value'] / 1000000 ;
            
            if ($transaction->amount != $amount) {
                $transaction->update([
                    'txID' => $transactionData['transaction_id'],
                    'block_time' => $transactionData['block_timestamp'],
                    'from_wallet' => $transactionData['from'],
                    'to_wallet' => $transactionData['to'],
                    'txn_amount' => $amount,
                    'status' => 'pending',
                    'transaction_date' => $nowDateTime
                ]);
    
            } else {
                $transaction->update([
                    'txID' => $transactionData['transaction_id'],
                    'block_time' => $transactionData['block_timestamp'],
                    'from_wallet' => $transactionData['from'],
                    'to_wallet' => $transactionData['to'],
                    'txn_amount' => $amount,
                    'status' => 'success',
                    'transaction_date' => $nowDateTime
                ]);
    
            }
        } else {
            // user input value
            $transaction = Transaction::find($request->transaction);
            $amount = $request->amount;
            $txid = $request->txid;
            $to_wallet = $request->to_wallet;

            //get from txid value
            $contract_address = $request->contractAddress;
            $from_address = $request->fromAddress;
            $to_address = $request->toAddress;
            $amountVal = $request->amountVal / 1000000;
            $timestamp  = $request->timeStamp;
            $seconds = $timestamp / 1000;
            $dateTime = Carbon::createFromTimestamp($seconds);
            
            if ($amount != $amountVal) {
                $transaction->update([
                    'txID' => $txid,
                    'from_wallet' => $from_address,
                    'to_wallet' => $to_address,
                    'amount' => $amount,
                    'txn_amount' => $amountVal,
                    'status' => 'pending',
                    'transaction_date' => $dateTime,
                    'description' => 'different amount',
                ]);
            } else {
                $transaction->update([
                    'txID' => $txid,
                    'from_wallet' => $from_address,
                    'to_wallet' => $to_address,
                    'amount' => $amount,
                    'txn_amount' => $amountVal,
                    'status' => 'pending',
                    'transaction_date' => $dateTime,
                ]);
            }

            return redirect(route('returnTransaction'));
        }


        // Log::debug($merchant);
        // Log::debug($merchant->merchantEmail);

        // foreach ($merchant->merchantEmail as $email) {
        //     Notification::route('mail', $email->email)
        //     ->notify(new TransactionNotification($merchant->name, $transactionData['transaction_id'], $transactionData['from'], $transactionData['to'], $amount, $transaction->status));
        // }

        // return redirect(route('returnTransaction'));
    }

    public function returnTransaction(Request $request)
    {

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

        $request->session()->flush();

        $url = $selectedPayout['paymentUrl'] . 'dashboard';
        $redirectUrl = $url;

        return Inertia::location($redirectUrl);
    }

    public function sessionTimeOut()
    {

        return Inertia::render('Timeout');
    }

    public function returnSession(Request $request)
    {
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
