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
use Illuminate\Validation\ValidationException;
use Ladumor\OneSignal\OneSignal;

class TransactionController extends Controller
{
    protected $apiKey;

    public function index()
    {
        return view('payment');
    }

    public function payment(Request $request)
    {
        $datas = $request->all();
        Log::debug('Incoming Data', $datas);

        $referer = request()->headers->get('referer');
        // Log::debug('Incoming referer', ['referer' => $referer]);

        $amount = $request->query('amount');
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

        } else if ($request->merchantId && $request->orderNumber && $request->userId && $request->vCode && $amount) {

            // Check vCode
            $validateVCode = md5($amount . $appId->appId . $transactionNo . $merchantId . $appId->secret_key);

            if ($validateVCode != $vCode) {
                // $request->session()->flush();
                return Inertia::render('Welcome');
            }

            // check transaction number for both crm and gateway exist or not
            $findTxnNo = Transaction::where('merchant_id', $merchantId)->where('amount', $amount)->where('transaction_number', $transactionNo)->where('status', 'pending')->first();
            $checkOrderNo = Transaction::where('merchant_id', $merchantId)->where('transaction_number', $transactionNo)->first();
            $paymentMethod = PayoutConfig::where('merchant_id', $merchantId)->where('live_paymentUrl', $referer)->first();

            // if transaction exist return to it
            if ($findTxnNo) {

                if ($findTxnNo->payment_method === 'auto') {
                    if (Carbon::now() > $findTxnNo->expired_at) {
                        $findTxnNo->status = 'fail';
                        $findTxnNo->save();
    
                        return Inertia::render('Welcome');
                    }
                }
                

                $merchant = Merchant::where('id', $merchantId)->with(['merchantWalletAddress.walletAddress'])->first();
                
                // get back existing wallet details
                $tokenAddress = $findTxnNo->to_wallet;
                $expirationTime = $findTxnNo->expired_at;
                $transaction = $findTxnNo;
                $storedToken = $request->session()->get('session_token');

                

                if ($merchant->deposit_type === "2") {

                    
                    if ($paymentMethod->payment_method === 'trc-20') {
                        return Inertia::render('Auto/TxidPayment', [
                            'merchant' => $merchant,
                            'expirationTime' => $expirationTime,
                            'transaction' => $transaction,
                            'tokenAddress' => $tokenAddress,
                            'lang' => $lang,
                            'referer' => $referer,
                            'apikey' => $paymentMethod->api_key,
                            'amount' => $amount,
                        ]);
                    }

                    if ($paymentMethod->payment_method === 'bep-20') {
                        return Inertia::render('Auto/Bep20Payment', [
                            'merchant' => $merchant,
                            'expirationTime' => $expirationTime,
                            'transaction' => $transaction,
                            'tokenAddress' => $tokenAddress,
                            'lang' => $lang,
                            'referer' => $referer,
                            'apikey' => $paymentMethod->api_key,
                            'amount' => $amount,
                        ]);
                    }

                } else {

                    if ($paymentMethod->payment_method === 'bep-20') {
                        return Inertia::render('Auto/Bep20Payment', [
                            'merchant' => $merchant,
                            'expirationTime' => $expirationTime,
                            'transaction' => $transaction,
                            'tokenAddress' => $tokenAddress,
                            'storedToken' => $storedToken,
                            'lang' => $lang,
                            'referer' => $referer,
                            'apikey' => $paymentMethod->api_key,
                            'amount' => $amount,
                        ]);
                    }
    
                    if ($paymentMethod->payment_method === 'trc-20') {
                        return Inertia::render('Auto/ValidPayment', [
                            'merchant' => $merchant,
                            'expirationTime' => $expirationTime,
                            'transaction' => $transaction,
                            'tokenAddress' => $tokenAddress,
                            'storedToken' => $storedToken,
                            'lang' => $lang,
                            'referer' => $referer,
                            'apikey' => $paymentMethod->api_key,
                            'amount' => $amount,
                        ]);
                    }
                }

                
                
            } else {
                // not exist create new
                // $validTime = 15; //minutes
                $now = Carbon::now();
    
                $merchant = Merchant::where('id', $merchantId)->with(['merchantWalletAddress.walletAddress'])->first();
                $randomWalletAddress = $merchant->merchantWalletAddress->random();
                $tokenAddress = $randomWalletAddress->walletAddress->token_address;

                $merchantClientId = $request->userId;
    
                if($merchant->deposit_type == "0" ) {

                    $transaction = Transaction::create([
                        'merchant_id' => $merchantId,
                        'client_id' => $merchantClientId,
                        'client_name' => $merchantClientName,
                        'client_email' => $merchantClientEmail,
                        'transaction_type' => 'deposit',
                        'payment_method' => 'manual',
                        'status' => 'pending',
                        'amount' => $amount,
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
                } else if ($merchant->deposit_type == "1") {

                    $storedToken = $request->session()->get('session_token');
    
                    if ($paymentMethod->payment_method === 'bep-20') {

                        $transaction = Transaction::create([
                            'merchant_id' => $merchantId,
                            'payment_type' => 'bep-20',
                            'client_id' => $merchantClientId,
                            'client_name' => $merchantClientName,
                            'client_email' => $merchantClientEmail,
                            'transaction_type' => 'deposit',
                            'payment_method' => 'auto',
                            'status' => 'pending',
                            'amount' => $amount,
                            'transaction_number' => $transactionNo,
                            'tt_txn' => RunningNumberService::getID('transaction'),
                            'to_wallet' => $tokenAddress,
                            'origin_domain' => $referer,
                            'expired_at' => Carbon::now()->addMinute(20),
                        ]);

                        return Inertia::render('Auto/Bep20Payment', [
                            'merchant' => $merchant,
                            'amount' => $amount,
                            'expirationTime' => $transaction->expired_at,
                            'transaction' => $transaction,
                            'tokenAddress' => $tokenAddress,
                            'storedToken' => $storedToken,
                            'lang' => $lang,
                            'referer' => $referer,
                            'apikey' => $paymentMethod->api_key,
                        ]);
                    }

                    if ($paymentMethod->payment_method === 'trc-20') {

                        $transaction = Transaction::create([
                            'merchant_id' => $merchantId,
                            'payment_type' => 'trc-20',
                            'client_id' => $merchantClientId,
                            'client_name' => $merchantClientName,
                            'client_email' => $merchantClientEmail,
                            'transaction_type' => 'deposit',
                            'payment_method' => 'auto',
                            'status' => 'pending',
                            'amount' => $amount,
                            'transaction_number' => $transactionNo,
                            'tt_txn' => RunningNumberService::getID('transaction'),
                            'to_wallet' => $tokenAddress,
                            'origin_domain' => $referer,
                            'expired_at' => Carbon::now()->addMinute(20),
                        ]);

                        return Inertia::render('Auto/ValidPayment', [
                            'merchant' => $merchant,
                            'amount' => $amount,
                            'expirationTime' => $transaction->expired_at,
                            'transaction' => $transaction,
                            'tokenAddress' => $tokenAddress,
                            'storedToken' => $storedToken,
                            'lang' => $lang,
                            'referer' => $referer,
                            'apikey' => $paymentMethod->api_key,
                        ]);
                    }
                } else if ($merchant->deposit_type == "2") {
                    $storedToken = $request->session()->get('session_token');

                    if ($paymentMethod->payment_method === 'bep-20') {

                        $transaction = Transaction::create([
                            'merchant_id' => $merchantId,
                            'payment_type' => 'bep-20',
                            'client_id' => $merchantClientId,
                            'client_name' => $merchantClientName,
                            'client_email' => $merchantClientEmail,
                            'transaction_type' => 'deposit',
                            'payment_method' => 'manual',
                            'status' => 'pending',
                            'amount' => $amount,
                            'transaction_number' => $transactionNo,
                            'tt_txn' => RunningNumberService::getID('transaction'),
                            'to_wallet' => $tokenAddress,
                            'origin_domain' => $referer,
                            'expired_at' => null,
                        ]);

                        return Inertia::render('Auto/Bep20Payment', [
                            'merchant' => $merchant,
                            'amount' => $amount,
                            'expirationTime' => $transaction->expired_at,
                            'transaction' => $transaction,
                            'tokenAddress' => $tokenAddress,
                            'storedToken' => $storedToken,
                            'lang' => $lang,
                            'referer' => $referer,
                            'apikey' => $paymentMethod->api_key,
                        ]);
                    }

                    if ($paymentMethod->payment_method === 'trc-20') {

                        $transaction = Transaction::create([
                            'merchant_id' => $merchantId,
                            'payment_type' => 'trc-20',
                            'client_id' => $merchantClientId,
                            'client_name' => $merchantClientName,
                            'client_email' => $merchantClientEmail,
                            'transaction_type' => 'deposit',
                            'payment_method' => 'manual',
                            'status' => 'pending',
                            'amount' => $amount,
                            'transaction_number' => $transactionNo,
                            'tt_txn' => RunningNumberService::getID('transaction'),
                            'to_wallet' => $tokenAddress,
                            'origin_domain' => $referer,
                            'expired_at' => null,
                        ]);

                        return Inertia::render('Auto/TxidPayment', [
                            'merchant' => $merchant,
                            'amount' => $amount,
                            'expirationTime' => $transaction->expired_at,
                            'transaction' => $transaction,
                            'tokenAddress' => $tokenAddress,
                            'storedToken' => $storedToken,
                            'lang' => $lang,
                            'referer' => $referer,
                            'apikey' => $paymentMethod->api_key,
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

        $merchant = Merchant::where('id', $request->merchantId)->with(['merchantWalletAddress.walletAddress', 'merchantEmail', 'merchantWallet'])->first();
        $paymentMethod = PayoutConfig::where('merchant_id', $merchant->id)->first();

        if ($merchant->deposit_type == "1") {

            if ($paymentMethod->payment_method === 'trc-20') {
                $transactionData = $request->latestTransaction;
                $transaction = Transaction::find($request->transaction);
                $nowDateTime = Carbon::now();
                $amount = $transactionData['value'] / 1000000 ;
                $inputAmount = $transaction->amount;
                Log::debug('get value', $transactionData);

                $check = Transaction::where('txID', $transactionData['transaction_id'])->first();
                $merchantRateProfile = RateProfile::find($merchant->rate_id);
                $fee = (($amount * $merchantRateProfile->deposit_fee) / 100);
                $symbol = $transactionData['token_info']['symbol'];

                if (empty($check)) {

                    if ($symbol === "USDT") {

                        $transfer_amount = $transactionData['value'] / 1000000 ; // 100
                        $start_range = $transfer_amount - $paymentMethod->diff_amount; //85
                        $end_range = $transfer_amount + $paymentMethod->diff_amount; // 115

                        if ($inputAmount >= $start_range && $inputAmount <= $end_range) {
                            $transaction->update([
                                'txID' => $transactionData['transaction_id'],
                                'block_time' => $transactionData['block_timestamp'],
                                'from_wallet' => $transactionData['from'],
                                'to_wallet' => $transactionData['to'],
                                'txn_amount' => $amount,
                                'fee' => $fee,
                                'total_amount' => $amount - $fee,
                                'status' => 'success',
                                'transfer_status' => 'valid',
                                'transaction_date' => $nowDateTime
                            ]);
                        } else {
                            $transaction->update([
                                'txID' => $transactionData['transaction_id'],
                                'block_time' => $transactionData['block_timestamp'],
                                'from_wallet' => $transactionData['from'],
                                'to_wallet' => $transactionData['to'],
                                'txn_amount' => $amount,
                                'fee' => $fee,
                                'total_amount' => $amount - $fee,
                                'status' => 'success',
                                'transfer_status' => 'invalid',
                                'transaction_date' => $nowDateTime
                            ]);
                        }

                        
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
                            $payoutSetting = PayoutConfig::where('merchant_id', $merchant->id)->where('live_paymentUrl', $request->referer)->first();

                            // Log::debug('payout', [
                            //     'payoutSetting' => $payoutSetting,
                            //     'merchant_id' => $merchant->id,
                            //     'referer' => $request->referer,
                            //     'payoutSetting' => $payoutSetting,
                            // ]);

                            $vCode = md5($transaction->transaction_number . $paymentMethod->appId . $merchant->id);
                            // Log::debug('md5', [
                            //     'vcode' => $vCode,
                            //     'txn_no' => $transaction->transaction_number,
                            //     'appId' => $paymentMethod->appId,
                            //     'merchant_id' => $merchant->id,
                            //     'request_merchant' => $request->merchantId
                            // ]);

                            $params = [
                                'merchant_id' => $transaction->merchant_id,
                                'client_id' => $transaction->client_id,
                                'transaction_type' => $transaction->transaction_type,
                                'from_wallet' => $transaction->from_wallet,
                                'to_wallet' => $transaction->to_wallet,
                                'txID' => $transaction->txID,
                                'block_time' => $transaction->block_time,
                                'transfer_amount' => $transaction->txn_amount,
                                'input_amount' => $inputAmount,
                                'transaction_number' => $transaction->transaction_number,
                                'status' => $transaction->status,
                                'transfer_amount_type' => $transaction->transfer_status,
                                'payment_method' => $transaction->payment_method,
                                'created_at' => $transaction->created_at,
                                'description' => $transaction->description,
                                'vCode' => $vCode,
                                // 'token' => $token,
                            ];

                            $url = $payoutSetting->live_paymentUrl . $payoutSetting->returnUrl;
                            $callBackUrl = $payoutSetting->live_paymentUrl . $payoutSetting->callBackUrl;

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
            }

            if ($paymentMethod->payment_method === 'bep-20') {
                $transactionData = $request->latestTransaction;
                $transaction = Transaction::find($request->transaction);
                $nowDateTime = Carbon::now();
                $amount = $transactionData['value'] / 1000000000000000000 ;
                $inputAmount = $transaction->amount;

                $check = Transaction::where('txID', $transactionData['hash'])->first();
                $merchantRateProfile = RateProfile::find($merchant->rate_id);
                $fee = (($amount * $merchantRateProfile->deposit_fee) / 100);

                if (empty($check)) {

                    $transfer_amount = $transactionData['value'] / 1000000 ; // 100
                    $start_range = $transfer_amount - $paymentMethod->diff_amount; //85
                    $end_range = $transfer_amount + $paymentMethod->diff_amount; // 115

                    if ($inputAmount >= $start_range && $inputAmount <= $end_range) {
                        $transaction->update([
                            'txID' => $transactionData['hash'],
                            'block_time' => $transactionData['timeStamp'],
                            'block_number' => $transactionData['blockNumber'],
                            'from_wallet' => $transactionData['from'],
                            'to_wallet' => $transactionData['to'],
                            'txn_amount' => $transactionData['value'] / 1000000000000000000,
                            'fee' => $fee,
                            'total_amount' => $amount - $fee,
                            'status' => 'success',
                            'transfer_status' => 'valid',
                            'txreceipt_status' => $transactionData['txreceipt_status'],
                            'transaction_date' => $nowDateTime,
                            'token_symbol' => $transactionData['tokenSymbol'] ?? null,
                        ]);
                    } else {
                        $transaction->update([
                            'txID' => $transactionData['hash'],
                            'block_time' => $transactionData['timeStamp'],
                            'block_number' => $transactionData['blockNumber'],
                            'from_wallet' => $transactionData['from'],
                            'to_wallet' => $transactionData['to'],
                            'txn_amount' => $transactionData['value'] / 1000000000000000000,
                            'fee' => $fee,
                            'total_amount' => $amount - $fee,
                            'status' => 'success',
                            'transfer_status' => 'valid',
                            'txreceipt_status' => $transactionData['txreceipt_status'],
                            'transaction_date' => $nowDateTime,
                            'token_symbol' => $transactionData['tokenSymbol'] ?? null,
                        ]);
                    }

                    

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
                        $payoutSetting = PayoutConfig::where('merchant_id', $request->merchantId)->where('live_paymentUrl', $request->referer)->first();

                        $vCode = md5($transaction->transaction_number . $payoutSetting->appId . $payoutSetting->merchant_id);

                        $params = [
                            'merchant_id' => $transaction->merchant_id,
                            'client_id' => $transaction->client_id,
                            'transaction_type' => $transaction->transaction_type,
                            'from_wallet' => $transaction->from_wallet,
                            'to_wallet' => $transaction->to_wallet,
                            'txID' => $transaction->txID,
                            'block_time' => $transaction->block_time,
                            'transfer_amount' => $transaction->txn_amount,
                            'input_amount' => $inputAmount,
                            'transaction_number' => $transaction->transaction_number,
                            'status' => $transaction->status,
                            'transfer_amount_type' => $transaction->transfer_status,
                            'payment_method' => $transaction->payment_method,
                            'created_at' => $transaction->created_at,
                            'description' => $transaction->description,
                            'vCode' => $vCode,
                            // 'token' => $token,
                        ];

                        $url = $payoutSetting->live_paymentUrl . $payoutSetting->returnUrl;
                        $callBackUrl = $payoutSetting->live_paymentUrl . $payoutSetting->callBackUrl;

                        $response = Http::post($callBackUrl, $params);
                    }
                }
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

            return redirect()->route('returnTransaction', ['transaction_id' => $transaction->id]);
        }
    }

    public function updateTxid(Request $request)
    {

        $request->validate([
            'txid' => 'required|string',
            'transaction' => 'required|exists:transactions,id',
        ]);

        $transaction = Transaction::find($request->transaction);
        $validateTxid = Transaction::where('txID', $request->txid)->first();
        $merchant = Merchant::find($transaction->merchant_id);
        $payoutConfig = PayoutConfig::where('merchant_id', $merchant->id)->where('live_paymentUrl', $transaction->origin_domain)->first();

        if (Transaction::where('txID', $request->txid)->exists()) {
            return response()->json(['errors' => ['txid' => 'Invalid TxID / TxID already exists']], 422);
        }

        $response = Http::get('https://apilist.tronscanapi.com/api/transaction-info', [
            'hash' => $request->txid,
        ]);

        if (!$response->successful()) {
            return response()->json(['errors' => ['txid' => 'Failed to fetch TRC-20 hash']], 422);
        }
        
        Log::info('Transaction-info response', ['hash' => $response]);

        $transactionInfo = $response->json();
        if (empty($transactionInfo['trc20TransferInfo'])) {
            return response()->json(['errors' => ['txid' => 'Invalid or incomplete TRC-20 transaction']], 422);
        }

        foreach ($transactionInfo['trc20TransferInfo'] as $trcTransfer) {
            
            $txnAmount = $trcTransfer['amount_str'] / 1000000;
            $merchantRateProfile = RateProfile::find($merchant->rate_id);
            $fee = ($txnAmount * $merchantRateProfile->deposit_fee) / 100;

            $start_range = $transaction->amount - $payoutConfig->diff_amount;
            $end_range = $transaction->amount + $payoutConfig->diff_amount;

            $transferStatus = ($txnAmount >= $start_range && $txnAmount <= $end_range) ? 'valid' : 'invalid';

            $timestamp = $transactionInfo['timestamp'];
            $transactionDate = Carbon::createFromTimestampMs($timestamp)->toDateTimeString();

            $transaction->update([
                'from_wallet' => $trcTransfer['from_address'],
                'txID' => $request->txid,
                'block_time' => $transactionInfo['timestamp'],
                'txn_amount' => $txnAmount,
                'fee' => $fee,
                'total_amount' => $txnAmount - $fee,
                'status' => 'success',
                'transfer_status' => $transferStatus,
                'transaction_date' => $transactionDate,
                'token_symbol' => $transactionInfo['tokenType'],
            ]);

            $vCode = md5($transaction->transaction_number . $payoutConfig->appId . $payoutConfig->merchant_id);
            $token = Str::random(32);

            $params = [
                'merchant_id' => $transaction->merchant_id,
                'client_id' => $transaction->client_id,
                'transaction_type' => $transaction->transaction_type,
                'from_wallet' => $transaction->from_wallet,
                'to_wallet' => $transaction->to_wallet,
                'txID' => $transaction->txID,
                'block_time' => $transaction->block_time,
                'block_number' => $transaction->block_number,
                'transfer_amount' => $transaction->txn_amount,
                'transfer_amount_type' => $transaction->transfer_status,
                'transaction_number' => $transaction->transaction_number,
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'txreceipt_status' => $transaction->txreceipt_status,
                'payment_method' => $transaction->payment_method,
                'payment_type' => $transaction->payment_type,
                'created_at' => $transaction->created_at,
                'description' => $transaction->description,
                'vCode' => $vCode,
                'token' => $token,
            ];

            $callBackUrl = $payoutConfig->live_paymentUrl . $payoutConfig->callBackUrl;
            $response = Http::post($callBackUrl, $params);

            return response()->json(['success' => 'Transaction updated successfully.']);
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
        $matchingPayoutSetting = $payoutSetting->firstWhere('live_paymentUrl', $referer);
        
        $vCode = md5($transactionVal->transaction_number . $matchingPayoutSetting->appId . $matchingPayoutSetting->merchant_id);
        

        $params = [
            'merchant_id' => $transactionVal->merchant_id,
            'client_id' => $transactionVal->client_id,
            'transaction_type' => $transactionVal->transaction_type,
            'from_wallet' => $transactionVal->from_wallet,
            'to_wallet' => $transactionVal->to_wallet,
            'txID' => $transactionVal->txID,
            'block_time' => $transactionVal->block_time,
            'block_number' => $transactionVal->block_number,
            'transfer_amount' => $transactionVal->txn_amount,
            'input_amount' => $transactionVal->amount,
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
