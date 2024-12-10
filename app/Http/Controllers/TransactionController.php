<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\MerchantWallet;
use App\Models\PayoutConfig;
use App\Models\RateProfile;
use App\Models\Token;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Notifications\TransactionNotification;
use App\Services\RunningNumberService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Ladumor\OneSignal\OneSignal;

class TransactionController extends Controller
{
    public function index()
    {
        return view('payment');
    }

    public function payment(Request $request)
    {
        $datas = $request->all();
        Log::debug('Incoming Data', $datas);

        $referer = request()->headers->get('referer');

        // $amount = $request->query('amount') / 1000000;
        $transactionNo = $request->query('orderNumber'); // TXN00000001 or no need
        $merchantId = $request->query('merchantId'); // MID000001
        $merchantClientId = $request->query('userId'); // Merchant client user id
        $merchantClientName = $request->query('userName'); // Merchant client user id
        $merchantClientEmail = $request->query('userEmail'); // Merchant client user id
        $vCode = $request->query('vCode'); // Merchant client user id
        $tt_txn = RunningNumberService::getID('transaction');
        $verifyToken = $request->query('token');
        $appId = PayoutConfig::where('merchant_id', $merchantId)->first();
        $lang = $request->query('locale'); // Language ? yes : default en

        if (empty($request->all())) {
            $request->session()->flush();
            return Inertia::render('Welcome');

        } else if ($request->merchantId && $request->orderNumber && $request->userId && $request->vCode) {
            
            //check user ID
            // $checkUser = Transaction::where('merchant_id', $merchantId)->where('client_id', $merchantClientId)->whereIn('status', ['pending'])->first();
            // if ($checkUser) {
            //     $request->session()->flush();
            //     return Inertia::render('Welcome');
            // }
            
            // $validateToken = TransactionLog::where('token', $verifyToken)->first();

            //check validate token
            // if (empty($validateToken)) {
            //     $Log = TransactionLog::create([
            //         'merchant_id' => $merchantId,
            //         'client_id' => $merchantClientId,
            //         'client_name' => $merchantClientName,
            //         'transaction_number' => $transactionNo,
            //         'token' => $verifyToken,
            //         'origin_domain' => $referer,
            //     ]);
            // } else {
            //     $request->session()->flush();
            //     return Inertia::render('Welcome');
            // }

            // Check vCode
            $validateVCode = md5($appId->appId . $transactionNo . $merchantId . $appId->secret_key);

            if ($validateVCode != $vCode) {
                // $request->session()->flush();
                return Inertia::render('Welcome');
            }

            // check session and store session token
            // $sessionToken = $request->query('token');

            // if (!$request->session()->has('session_token')) {
            //     // Store the token in the session
            //     $request->session()->put('session_token', $sessionToken);
            // } else {
            //     // Retrieve the token from the session
            //     $storedToken = $request->session()->get('session_token');
    
            //     // Validate the token
                
            //     if ($sessionToken !== $storedToken) {
            //         $request->session()->flush();
            //         return Inertia::render('Welcome');
            //     }
            // }

            // check transaction number for both crm and gateway exist or not
            $findTxnNo = Transaction::where('merchant_id', $merchantId)->where('transaction_number', $transactionNo)->where('status', 'pending')->first();
            $checkOrderNo = Transaction::where('merchant_id', $merchantId)->where('transaction_number', $transactionNo)->first();

            // $findtt_txn = Transaction::where('tt_txn', $tt_txn)->whereNot('status', 'pending')->first();
            
            // if transaction exist return to it
            if ($findTxnNo) {

                if (Carbon::now() > $findTxnNo->expired_at) {
                    $findTxnNo->status = 'fail';
                    $findTxnNo->save();

                    return Inertia::render('Welcome');
                }

                $merchant = Merchant::where('id', $merchantId)->with(['merchantWalletAddress.walletAddress'])->first();
                // $randomWalletAddress = $merchant->merchantWalletAddress->random();
                // $tokenAddress = $randomWalletAddress->walletAddress->token_address;
                
                // get back existing wallet details
                $tokenAddress = $findTxnNo->to_wallet;
                $expirationTime = $findTxnNo->expired_at;
                $transaction = $findTxnNo;
                $storedToken = $request->session()->get('session_token');
                
                return Inertia::render('Auto/ValidPayment', [
                    'merchant' => $merchant,
                    'expirationTime' => $expirationTime,
                    'transaction' => $transaction,
                    'tokenAddress' => $tokenAddress,
                    'storedToken' => $storedToken,
                    'lang' => $lang,
                    'referer' => $referer,
                ]);

                
            } else {
                // not exist create new
                // $validTime = 15; //minutes
                $now = Carbon::now();
    
                // if (!$request->session()->has('payment_expiration_time')) {
                //     // Calculate the expiration time as now + validTime minutes
                //     $expirationTime = $now->copy()->addMinutes($validTime);
            
                //     // Store the expiration time in the session
                //     $request->session()->put('payment_expiration_time', $expirationTime);
                // } else {
                //     // Retrieve the expiration time from the session
                //     $expirationTime = Carbon::parse($request->session()->get('payment_expiration_time'));
                // }
    
                // check timimg for the session
                // if ($now >= $expirationTime) {
                //     return Inertia::render('Timeout');
                // } else {

                if ($checkOrderNo) {
                    return Inertia::render('Welcome');
                }
    
                $merchant = Merchant::where('id', $merchantId)->with(['merchantWalletAddress.walletAddress'])->first();
                $randomWalletAddress = $merchant->merchantWalletAddress->random();
                $tokenAddress = $randomWalletAddress->walletAddress->token_address;

                $merchantClientId = $request->userId;
    
                if($merchant->deposit_type == 0 ) {

                    $transaction = Transaction::create([
                        'merchant_id' => $merchantId,
                        'client_id' => $merchantClientId,
                        'client_name' => $merchantClientName,
                        'client_email' => $merchantClientEmail,
                        'transaction_type' => 'deposit',
                        'payment_method' => 'manual',
                        'status' => 'pending',
                        // 'amount' => $amount,
                        'transaction_number' => $transactionNo,
                        'tt_txn' => RunningNumberService::getID('transaction'),
                        'to_wallet' => $tokenAddress,
                        'origin_domain' => $referer,
                        'expired_at' => Carbon::now()->addMinute(20),
                    ]);

                    return Inertia::render('Manual/ValidPayment', [
                        'merchant' => $merchant,
                        'merchantClientId' => $merchantClientId, //userid
                        'vCode' => $request->vCode, //vCode
                        'orderNumber' => $request->orderNumber, //orderNumber
                        'expirationTime' => $transaction->expired_at,
                        'transaction' => $transaction,
                        'tokenAddress' => $tokenAddress,
                        'lang' => $lang,
                        'origin_domain' => $referer,
                    ]);
                } else if ($merchant->deposit_type == 1) {

                    $storedToken = $request->session()->get('session_token');

                    $transaction = Transaction::create([
                        'merchant_id' => $merchantId,
                        'client_id' => $merchantClientId,
                        'client_name' => $merchantClientName,
                        'client_email' => $merchantClientEmail,
                        'transaction_type' => 'deposit',
                        'payment_method' => 'auto',
                        'status' => 'pending',
                        // 'amount' => $amount,
                        'transaction_number' => $transactionNo,
                        'tt_txn' => RunningNumberService::getID('transaction'),
                        'to_wallet' => $tokenAddress,
                        'origin_domain' => $referer,
                        'expired_at' => Carbon::now()->addMinute(20),
                    ]);
    
                    return Inertia::render('Auto/ValidPayment', [
                        'merchant' => $merchant,
                        // 'amount' => $amount,
                        'expirationTime' => $transaction->expired_at,
                        'transaction' => $transaction,
                        'tokenAddress' => $tokenAddress,
                        'storedToken' => $storedToken,
                        'lang' => $lang,
                        'referer' => $referer,0
                    ]);
                }
    
                // }
            }
            
        }
        
    }

    public function updateClientTransaction(Request $request)
    {
        // dd($request->all());
        $datas = $request->all();
        Log::debug('capture txid', $datas);

        $merchant = Merchant::where('id', $request->merchantId)->with(['merchantWalletAddress.walletAddress', 'merchantEmail', 'merchantWallet'])->first();

        if ($merchant->deposit_type == 1) {

            $transactionData = $request->latestTransaction;
            $transaction = Transaction::find($request->transaction);
            $nowDateTime = Carbon::now();
            $amount = $transactionData['value'] / 1000000 ;
            Log::debug('get value', $transactionData);

            $check = Transaction::where('txID', $transactionData['transaction_id'])->first();
            $merchantRateProfile = RateProfile::find($merchant->rate_id);
            $fee = (($amount * $merchantRateProfile->deposit_fee) / 100);
            $symbol = $transactionData['token_info']['symbol'];

            if (empty($check)) {

                if ($symbol === "USDT") {
                    $transaction->update([
                        'txID' => $transactionData['transaction_id'],
                        'block_time' => $transactionData['block_timestamp'],
                        'from_wallet' => $transactionData['from'],
                        'to_wallet' => $transactionData['to'],
                        'txn_amount' => $amount,
                        'fee' => $fee,
                        'total_amount' => $amount - $fee,
                        'status' => 'success',
                        'transaction_date' => $nowDateTime
                    ]);
                    if ($transaction->transaction_type === 'deposit') {
                        $merchantWallet = MerchantWallet::where('merchant_id', $request->merchantId)->first();
        
                        // wallet
                        $merchantWallet->gross_deposit += $transaction->txn_amount; //gross amount 
                        $gross_fee = (($merchantWallet->gross_deposit * $merchantRateProfile->withdrawal_fee) / 100);
                        $merchantWallet->total_fee += $gross_fee; // total fee
                        $merchantWallet->net_deposit = $merchantWallet->gross_deposit - $gross_fee; // net amount
                        $merchantWallet->total_deposit += $transaction->txn_amount;
                        $merchantWallet->save();

                        // callback here
                        $payoutSetting = PayoutConfig::where('merchant_id', $request->merchantId)->first();
                        $matchingPayoutSetting = $payoutSetting->firstWhere('live_paymentUrl', $request->referer);

                        $vCode = md5($transaction->transaction_number . $matchingPayoutSetting->appId . $matchingPayoutSetting->merchant_id);

                        $params = [
                            'merchant_id' => $transaction->merchant_id,
                            'client_id' => $transaction->client_id,
                            'transaction_type' => $transaction->transaction_type,
                            'from_wallet' => $transaction->from_wallet,
                            'to_wallet' => $transaction->to_wallet,
                            'txID' => $transaction->txID,
                            'block_time' => $transaction->block_time,
                            'transfer_amount' => $transaction->txn_amount,
                            'transaction_number' => $transaction->transaction_number,
                            'status' => $transaction->status,
                            'payment_method' => $transaction->payment_method,
                            'created_at' => $transaction->created_at,
                            'description' => $transaction->description,
                            'vCode' => $vCode,
                            // 'token' => $token,
                        ];

                        $url = $matchingPayoutSetting->live_paymentUrl . $matchingPayoutSetting->returnUrl;
                        $callBackUrl = $matchingPayoutSetting->live_paymentUrl . $matchingPayoutSetting->callBackUrl;

                        $response = Http::post($callBackUrl, $params);

                        // if ($response['success']) {
                        //     $params['response_status'] = 'success';
                        // } else {
                        //     $params['response_status'] = 'failed';
                        // }

        
                    } else {
                        $merchantWallet = MerchantWallet::where('merchant_id', $request->merchantId)->first();
        
                        // $merchantWallet->gross_withdrawal += $transaction->txn_amount;
                        // $merchantWallet->net_withdrawal += $transaction->total_amount;
                        // $merchantWallet->withdrawal_fee += $transaction->fee;
        
                        // $merchantWallet->save();
        
                        // $message = 'Approved $' . $amount . ', TxID - ' . $transactionData['transaction_id'];
        
                    }
                } else {
                    $transaction->update([
                        'txID' => $transactionData['transaction_id'],
                        'block_time' => $transactionData['block_timestamp'],
                        'from_wallet' => $transactionData['from'],
                        'to_wallet' => $transactionData['to'],
                        'txn_amount' => $amount,
                        'fee' => $fee,
                        'total_amount' => $amount - $fee,
                        'status' => 'fail',
                        'transaction_date' => $nowDateTime,
                        'description' => 'unknown symbol',
                    ]);
                }

            } else {
                Log::debug('txID repeated');
            }

            return redirect()->route('returnTransaction', ['transaction_id' => $transaction->id]);
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

            return redirect()->route('returnTransaction', ['transaction_id' => $transaction->id]);
        }
    }

    public function returnTransaction(Request $request)
    {

        $transaction = $request->transaction_id;
        $storedToken = $request->token;
        $transactionDetails = Transaction::find($transaction);
        $merchant = Merchant::where('id', $transactionDetails->merchant_id)->with(['merchantWalletAddress.walletAddress', 'merchantEmail'])->first();
        $referer = $request->referer;

        // $arrEmails = [];
        // foreach ($merchant->merchantEmail as $emails) {
        //     $email = $emails->email;
        //     $arrEmails = $email;
        //     dd($arrEmails);
        // }
        // dd($merchant->merchantEmail->email);

        // $emailsReceiver = [

        // ];

        // Notification::route('mail', $email)->notify(new TransactionNotification($merchant->name, $transactionDetails->txID, $transactionDetails->txID, $transactionDetails->to_wallet, $transactionDetails->txn_amount, $transactionDetails->status));

        return Inertia::render('Manual/ReturnPayment', [
            'transaction' => $transaction,
            'storedToken' => $storedToken,
            'merchant_id' => $transactionDetails->merchant_id,
            'referer' => $referer,
        ]);
    }

    public function returnUrl(Request $request)
    {
        $transaction = $request->transaction;
        $token = $request->storedToken;
        $merchant = $request->merchant_id;
        $transactionVal = Transaction::find($transaction); 
        $referer = $request->referer;

        // $amount = $transactionVal->amount;
        // $payoutSetting = config('payment-gateway');
        // $domain = $_SERVER['HTTP_HOST'];
        
        $payoutSetting = PayoutConfig::where('merchant_id', $merchant)->first();
        $matchingPayoutSetting = $payoutSetting->firstWhere('live_paymentUrl', $transactionVal->origin_domain);
        
        $vCode = md5($transactionVal->transaction_number . $matchingPayoutSetting->appId . $matchingPayoutSetting->merchant_id);
        

        $params = [
            'merchant_id' => $transactionVal->merchant_id,
            'client_id' => $transactionVal->client_id,
            'transaction_type' => $transactionVal->transaction_type,
            'from_wallet' => $transactionVal->from_wallet,
            'to_wallet' => $transactionVal->to_wallet,
            'txID' => $transactionVal->txID,
            'block_time' => $transactionVal->block_time,
            'transfer_amount' => $transactionVal->txn_amount,
            'transaction_number' => $transactionVal->transaction_number,
            'status' => $transactionVal->status,
            'payment_method' => $transactionVal->payment_method,
            'created_at' => $transactionVal->created_at,
            'description' => $transactionVal->description,
            'vCode' => $vCode,
            'token' => $token,
        ];

        $request->session()->flush();

        $url = $matchingPayoutSetting->live_paymentUrl . $matchingPayoutSetting->returnUrl;
        $callBackUrl = $matchingPayoutSetting->live_paymentUrl . $matchingPayoutSetting->callBackUrl;
        
        
        // $response = Http::post($callBackUrl, $params);

        // if ($response['success']) {
        //     $params['response_status'] = 'success';
        // } else {
        //     $params['response_status'] = 'failed';
        // }

        $redirectUrl = $url . "?" . http_build_query($params);
        return Inertia::location($redirectUrl);

    }

    public function sessionTimeOut(Request $request)
    {
        // dd($request->all());
        return Inertia::render('Timeout', [
            'transactionId' => $request->transaction_id,
        ]);
    }

    public function returnSession(Request $request)
    {
        $data = $request->all();
        Log::debug($data);

        $transactionId = $data['transaction'];
        $transction = Transaction::find($transactionId);

        $params = [
            'merchant_id' => $transction->merchant_id,
            'client_id' => $transction->client_id,
            'transaction_type' => $transction->transaction_type,
            // 'amount' => $transction->amount,
            'transaction_number' => $transction->transaction_number,
            'status' => 'pending',
            'payment_method' => $transction->payment_method,
            'created_at' => $transction->created_at,
            'description' => $transction->description,
            'response_status' => 'pending',
        ];

        // $apiUrl = route('returnParams');
        
        $request->session()->flush();

        // $payoutSetting = config('payment-gateway');
        // $domain = $_SERVER['HTTP_HOST'];
        // $selectedPayout = $payoutSetting['robotec_live'];

        $referer = $transction->origin_domain;

        $payoutSetting = PayoutConfig::where('merchant_id', $transction->merchant_id)->get();
        $matchingPayoutSetting = $payoutSetting->firstWhere('live_paymentUrl', $referer);

        $url = $matchingPayoutSetting->live_paymentUrl . $matchingPayoutSetting->returnUrl;
        $redirectUrl = $url . "?" .  http_build_query($params);

        return Inertia::location($redirectUrl);
    }
}