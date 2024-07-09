<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Token;
use App\Models\Transaction;
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
        $datas = $request->all();
        Log::debug('Incoming Data', $datas);

        $amount = $request->query('amount') / 1000000;
        $transactionNo = $request->query('orderNumber'); // TXN00000001 or no need
        $merchantId = $request->query('merchantId'); // MID000001
        $merchantClientId = $request->query('userId'); // Merchant client user id
        $tt_txn = RunningNumberService::getID('transaction');

        if (empty($request->all())) {
           
            return Inertia::render('Welcome');

        } else if ($request->merchantId && $request->merchantId && $request->orderNumber && $request->userId && $request->vCode) {
            $sessionToken = $request->query('token');

            if (!$request->session()->has('session_token')) {
                // Store the token in the session
                $request->session()->put('session_token', $sessionToken);
            } else {
                // Retrieve the token from the session
                $storedToken = $request->session()->get('session_token');
    
                // Validate the token
                
                if ($sessionToken !== $storedToken) {
                    $request->session()->flush();
                    return Inertia::render('Welcome');
                }
            }

            $findTxnNo = Transaction::where('transaction_number', $transactionNo)->first();
            $findtt_txn = Transaction::where('tt_txn', $tt_txn)->first();
            
            if ($findTxnNo || $findtt_txn) {
                $request->session()->flush();
                return Inertia::render('Welcome');
            } else {
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
                    $randomWalletAddress = $merchant->merchantWalletAddress->random();
                    $tokenAddress = $randomWalletAddress->walletAddress->token_address;

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
                            'tt_txn' => $tt_txn,
                            'to_wallet' => $tokenAddress,
                        ]);
    
                        return Inertia::render('Manual/ValidPayment', [
                            'merchant' => $merchant,
                            'merchantClientId' => $merchantClientId, //userid
                            'vCode' => $request->vCode, //vCode
                            'orderNumber' => $request->orderNumber, //orderNumber
                            'expirationTime' => $expirationTime,
                            'transaction' => $transaction,
                            'tokenAddress' => $tokenAddress,
                        ]);
                    } else if ($merchant->deposit_type == 1) {

                        $storedToken = $request->session()->get('session_token');
    
                        $transaction = Transaction::create([
                            'merchant_id' => $merchantId,
                            'client_id' => $merchantClientId,
                            'transaction_type' => 'deposit',
                            'payment_method' => 'auto',
                            'status' => 'pending',
                            'amount' => $amount,
                            'transaction_number' => $transactionNo,
                            'tt_txn' => $tt_txn,
                            'to_wallet' => $tokenAddress,
                        ]);
        
                        return Inertia::render('Auto/ValidPayment', [
                            'merchant' => $merchant,
                            'amount' => $amount,
                            'expirationTime' => $expirationTime,
                            'transaction' => $transaction,
                            'tokenAddress' => $tokenAddress,
                            'storedToken' => $storedToken,
                        ]);
                    }
    
                }
            }
            
        }
        
    }

    public function updateClientTransaction(Request $request)
    {
        // dd($request->all());
        $datas = $request->all();
        Log::debug('capture txid', $datas);

        $merchant = Merchant::where('id', $request->merchantId)->with(['merchantWalletAddress.walletAddress', 'merchantEmail'])->first();
        
        if ($merchant->deposit_type == 1) {
            $transactionData = $request->latestTransaction;
            $transaction = Transaction::find($request->transaction);
            $nowDateTime = Carbon::now();
            $amount = $transactionData['value'] / 1000000 ;
            Log::debug('get value', $transactionData);
            
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
            
            
            // foreach ($merchant->merchantEmail as $emails) {
            //     $email = $emails->email;

            //     Notification::route('mail', $email)->notify(new TransactionNotification($merchant->name, $transactionData['transaction_id'], $transactionData['from'], $transactionData['to'], $amount, $transaction->status));
            // }


            // foreach ($merchant->merchantEmail as $emails) {
            //     $email = $emails->email;

            //     Notification::route('mail', $email)->notify(new TransactionNotification($merchant->name, $transactionData['transaction_id'], $transactionData['from'], $transactionData['to'], $amount, $transaction->status));
            // }

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
        ]);
    }

    public function returnUrl(Request $request)
    {
        $transaction = $request->transaction;
        $token = $request->storedToken;
        $transactionVal = Transaction::find($transaction); 
        
        $amount = $transactionVal->amount;
        $payoutSetting = config('payment-gateway');
        $domain = $_SERVER['HTTP_HOST'];
        $paymentGateway = config('payment-gateway');
        $intAmount = intval($amount * 100);

        if ($domain === 'login.metafinx.com') {
            $selectedPayout = $payoutSetting['live'];
        } else {
            $selectedPayout = $payoutSetting['robotec'];
        }

        $vCode = md5($transactionVal->transaction_number . $selectedPayout['appId'] . $selectedPayout['merchantId']);

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
            'amount' => $transactionVal->amount,
            'status' => $transactionVal->status,
            'payment_method' => $transactionVal->payment_method,
            'created_at' => $transactionVal->created_at,
            'description' => $transactionVal->description,
            'vCode' => $vCode,
            'token' => $token,
        ];

        $request->session()->flush();

        $url = $selectedPayout['paymentUrl'] . $selectedPayout['returnUrl'];
        $callBackUrl = $selectedPayout['paymentUrl'] . $selectedPayout['callBackUrl'];
        $redirectUrl = $url . "?" . http_build_query($params);
        
        $response = Http::post($callBackUrl, $params);
        Log::debug($response);

        // return $this->postRedirect($callBackUrl, $params);

        if ($response['success']) {
            $params['reponse_status'] = 'success';

            return redirect()->away($selectedPayout['paymentUrl'] . "?" . http_build_query($params));
        } else {
            $params['reponse_status'] = 'failed';

            return redirect()->away($selectedPayout['paymentUrl'] . "?" . http_build_query($params));
        }

        // if ($response->successful()) {
        //     // If the response is successful, redirect to the return URL with parameters
        //     return redirect()->away($selectedPayout['paymentUrl'] . "?" . http_build_query($params));
        // } else {
        //     // Handle the error, for example, redirect back with an error message
        //     return redirect()->back()->withErrors(['message' => 'Failed to process the payment.']);
        // }

    }

    private function postRedirect($url, $data)
    {
        $html = '<html><body>';
        $html .= '<form id="form" action="' . htmlspecialchars($url) . '" method="POST">';
        $html .= '<input type="hidden" name="_token" value="' . csrf_token() . '">';

        foreach ($data as $key => $value) {
            $html .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
        }
        
        $html .= '</form>';
        $html .= '<script type="text/javascript">document.getElementById("form").submit();</script>';
        $html .= '</body></html>';

        return response($html);
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
            'amount' => $transction->amount,
            'transaction_number' => $transction->transaction_number,
            'amount' => $transction->amount,
            'status' => 'pending',
            'payment_method' => $transction->payment_method,
            'created_at' => $transction->created_at,
            'description' => $transction->description,
        ];

        // $apiUrl = route('returnParams');
        
        $request->session()->flush();

        $payoutSetting = config('payment-gateway');
        $domain = $_SERVER['HTTP_HOST'];

        if ($domain === 'login.metafinx.com') {
            $selectedPayout = $payoutSetting['live'];
        } else {
            $selectedPayout = $payoutSetting['robotec'];
        }

        $url = $selectedPayout['paymentUrl'] . $selectedPayout['returnUrl'];
        $redirectUrl = $url . "?" .  http_build_query($params);

        return Inertia::location($redirectUrl);
    }
}
