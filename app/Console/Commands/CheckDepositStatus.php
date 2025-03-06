<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\MerchantWallet;
use App\Models\PayoutConfig;
use App\Models\RateProfile;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use function PHPUnit\Framework\isEmpty;

class CheckDepositStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:deposit-status';
    protected $apiKey;
    protected $production;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check deposit statuses has txid';

    public function __construct()
    {
        $this->apiKey = env('BSCSCAN_API_KEY');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $pendingPayments = Transaction::where('status', 'pending')
                    ->where('transaction_type', 'deposit')
                    ->latest()
                    ->get();

        // $this->apiKey = 'EPSDNBABH6WB61JG79399KZY9RPSD3FYZ4';
        // $this->production = env('APP_ENV');
        Log::debug('api key ', ['api key' => $this->apiKey]);
        
        foreach ($pendingPayments as $pending) {
            Log::debug('all pending data', ['transaction' => $pending->toArray()]);

            $tokenAddress = $pending->to_wallet;
            $createdAt = $pending->created_at;
            // $expiredAt = Carbon::parse($pending->expired_at);
            $min_timeStamp = $createdAt->timestamp * 1000;
            $blockTimeStamp = $createdAt->timestamp;
            $merchantID = $pending->merchant_id;
            $merchant = Merchant::find($merchantID);
            $merchantWallet = MerchantWallet::where('merchant_id', $merchant->id)->first();

            if ($pending->payment_type === 'trc-20') {

                $response = Http::get('https://api.trongrid.io/v1/accounts/'. $tokenAddress .'/transactions/trc20', [
                    'min_timestamp' => $min_timeStamp,
                    'only_to' => true,
                ]);

                // $response = Http::get('https://nile.trongrid.io/v1/accounts/'. $tokenAddress .'/transactions/trc20', [
                //     'min_timestamp' => $min_timeStamp,
                //     'only_to' => true,
                // ]);

                Log::debug('Response received', $response->json());
            
                if ($response->successful()) {
                    $transactionInfo = $response->json();

                    if (!empty($transactionInfo['data'])) {
                        foreach($transactionInfo as $transactions) {
                            Log::debug('transactions', $transactions);
        
                            foreach($transactions as $transaction) {
                                Log::debug('data', $transaction);
                                Log::debug('data test', ['transaction_id' => $transaction['transaction_id']]);
        
                                if (Transaction::where('txID', $transaction['transaction_id'])->doesntExist()) {
                                    Log::debug('Transaction ID does not exist');
            
                                    $txnAmount = $transaction['value'] / 1000000;
                                    $timestamp = $transaction['block_timestamp'] / 1000;
                                    $transaction_date = Carbon::createFromTimestamp($timestamp)->setTimezone('GMT+8');
                                    $merchantRateProfile = RateProfile::find($merchant->rate_id);
                                    $fee = (($txnAmount * $merchantRateProfile->deposit_fee) / 100);
                                    $symbol = $transaction['token_info']['symbol'];
                                    
                                    $payoutSetting = PayoutConfig::where('merchant_id', $pending->merchant_id)->where('live_paymentUrl', $pending->origin_domain)->first();

                                    if ($symbol === "USDT") {

                                        

                                        $inputAmount = $pending->amount; // Amount the user is expected to receive
                                        $start_range = $txnAmount - $payoutSetting->diff_amount;
                                        $end_range = $txnAmount + $payoutSetting->diff_amount;

                                        if ($inputAmount >= $start_range && $inputAmount <= $end_range) {

                                            $pending->update([
                                                'from_wallet' => $transaction['from'],
                                                'txID' => $transaction['transaction_id'],
                                                'block_time' => $transaction['block_timestamp'],
                                                'txn_amount' => $txnAmount,
                                                'fee' => $fee,
                                                'total_amount' => $txnAmount - $fee,
                                                'transaction_date' => $transaction_date,
                                                'status' => 'success',
                                                'transfer_status' => 'valid',
                                            ]);

                                        } else {
                                            $pending->update([
                                                'from_wallet' => $transaction['from'],
                                                'txID' => $transaction['transaction_id'],
                                                'block_time' => $transaction['block_timestamp'],
                                                'txn_amount' => $txnAmount,
                                                'fee' => $fee,
                                                'total_amount' => $txnAmount - $fee,
                                                'transaction_date' => $transaction_date,
                                                'status' => 'success',
                                                'transfer_status' => 'invalid',
                                            ]);
                                        }

                                        if ($pending->transaction_type === 'deposit') {
                                            $merchantWallet = MerchantWallet::where('merchant_id', $merchant->id)->first();
            
                                            $merchantWallet->gross_deposit += $txnAmount; //gross amount 
                                            $gross_fee = (($merchantWallet->gross_deposit * $merchantRateProfile->withdrawal_fee) / 100);
                                            $merchantWallet->total_fee += $gross_fee; // total fee
                                            $merchantWallet->net_deposit = $merchantWallet->gross_deposit - $gross_fee; // net amount
                                            
                                            $merchantWallet->total_deposit += $txnAmount;
            
                                            $merchantWallet->save();
            
                                        }
                                    } else {
                                        $pending->update([
                                            'from_wallet' => $transaction['from'],
                                            'txID' => $transaction['transaction_id'],
                                            'block_time' => $transaction['block_timestamp'],
                                            'txn_amount' => $txnAmount,
                                            'fee' => $fee,
                                            'total_amount' => $txnAmount - $fee,
                                            'transaction_date' => $transaction_date,
                                            'status' => 'fail',
                                            'description' => 'unknown symbol',
                                        ]);
                                    }
            
                                    
            
                                    $vCode = md5($pending->transaction_number . $payoutSetting->appId . $payoutSetting->merchant_id);
                                    $token = Str::random(32);
            
                                    $params = [
                                        'merchant_id' => $pending->merchant_id,
                                        'client_id' => $pending->client_id,
                                        'transaction_type' => $pending->transaction_type,
                                        'from_wallet' => $pending->from_wallet,
                                        'to_wallet' => $pending->to_wallet,
                                        'txID' => $pending->txID,
                                        'block_time' => $pending->block_time,
                                        'transfer_amount' => $pending->txn_amount,
                                        'transaction_number' => $pending->transaction_number,
                                        'amount' => $pending->amount,
                                        'status' => $pending->status,
                                        'transfer_amount_type' => $pending->transfer_status,
                                        'payment_method' => $pending->payment_method,
                                        'created_at' => $pending->created_at,
                                        'description' => $pending->description,
                                        'vCode' => $vCode,
                                        'token' => $token,
                                    ];
            
                                    $callBackUrl = $payoutSetting->live_paymentUrl . $payoutSetting->callBackUrl;
                                    $response = Http::post($callBackUrl, $params);
                                    
                                    // Log::debug('deposit Callback', $response);
                                    Log::debug('deposit Callback', [
                                        'status' => $response->status(),
                                    ]);
                                    
                                } else {
                                    Log::debug('txid', ['transaction_id' => $transaction['transaction_id']]);
                                }
                            }
                        }
                    }
                    
                } else {
                    return response()->json(['error' => 'Failed to fetch transactions']);
                }

            }

            if ($pending->payment_type === 'bep-20') {

                $getStartBlock = Http::get('https://api.bscscan.com/api', [
                    'module' => 'block',
                    'action' => 'getblocknobytime',
                    'timestamp' => $blockTimeStamp,
                    'closest' => 'after',
                    'apikey' => $this->apiKey,
                ]);

                $response = Http::get('https://api.bscscan.com/api', [
                    'module' => 'account',
                    'action' => 'txlist',
                    'address' => $tokenAddress,
                    'page' => 1,
                    'sort' => 'desc',
                    'startblock' => $getStartBlock['result'],
                    'endblock' => 99999999,
                    'apikey' => $this->apiKey,
                ]);

                Log::debug('Response received', $response->json());

                if ($response->successful()) {
                    $transactionInfo = $response->json();

                    if (!empty($transactionInfo['result'])) {
                        foreach($transactionInfo['result'] as $transaction) {
                            Log::debug('bep-20 transactions', [
                                'transactions' => $transaction, 
                                'transaction_id' => $transaction['hash'] ?? 'N/A',
                            ]);

                            if (Transaction::where('txID', $transaction['hash'])->doesntExist()) {
                                Log::debug('Transaction ID does not exist');

                                $txnAmount = $transaction['value'] / 1000000000000000000;
                                $timestamp = $transaction['timeStamp'];
                                $transaction_date = Carbon::createFromTimestamp($timestamp)->setTimezone('GMT+8');
                                $merchantRateProfile = RateProfile::find($merchant->rate_id);
                                $fee = (($txnAmount * $merchantRateProfile->deposit_fee) / 100);

                                $payoutSetting = PayoutConfig::where('merchant_id', $pending->merchant_id)->where('live_paymentUrl', $pending->origin_domain)->first();
                                
                                $inputAmount = $pending->amount; // Amount the user is expected to receive
                                $start_range = $txnAmount - $payoutSetting->diff_amount;
                                $end_range = $txnAmount + $payoutSetting->diff_amount;

                                if ($inputAmount >= $start_range && $inputAmount <= $end_range) {
                                    $pending->update([
                                        'from_wallet' => $transaction['from'],
                                        'txID' => $transaction['hash'],
                                        'block_time' => $transaction['timeStamp'],
                                        'block_number' => $transaction['blockNumber'],
                                        'txn_amount' => $txnAmount,
                                        'fee' => $fee,
                                        'total_amount' => $txnAmount - $fee,
                                        'transaction_date' => $transaction_date,
                                        'status' => 'success',
                                        'txreceipt_status' => $transaction['txreceipt_status'],
                                        'transfer_status' => 'valid',
                                    ]);
                                } else {
                                    $pending->update([
                                        'from_wallet' => $transaction['from'],
                                        'txID' => $transaction['hash'],
                                        'block_time' => $transaction['timeStamp'],
                                        'block_number' => $transaction['blockNumber'],
                                        'txn_amount' => $txnAmount,
                                        'fee' => $fee,
                                        'total_amount' => $txnAmount - $fee,
                                        'transaction_date' => $transaction_date,
                                        'status' => 'success',
                                        'txreceipt_status' => $transaction['txreceipt_status'],
                                        'transfer_status' => 'invalid',
                                    ]);
                                }

                                

                                if ($pending->transaction_type === 'deposit') {
                                    $merchantWallet = MerchantWallet::where('merchant_id', $merchant->id)->first();
    
                                    $merchantWallet->gross_deposit += $txnAmount; //gross amount 
                                    $gross_fee = (($merchantWallet->gross_deposit * $merchantRateProfile->withdrawal_fee) / 100);
                                    $merchantWallet->total_fee += $gross_fee; // total fee
                                    $merchantWallet->net_deposit = $merchantWallet->gross_deposit - $gross_fee; // net amount
                                    
                                    $merchantWallet->total_deposit += $txnAmount;
    
                                    $merchantWallet->save();
    
                                }
        
                                $vCode = md5($pending->transaction_number . $payoutSetting->appId . $payoutSetting->merchant_id);
                                $token = Str::random(32);
        
                                $params = [
                                    'merchant_id' => $pending->merchant_id,
                                    'client_id' => $pending->client_id,
                                    'transaction_type' => $pending->transaction_type,
                                    'from_wallet' => $pending->from_wallet,
                                    'to_wallet' => $pending->to_wallet,
                                    'txID' => $pending->txID,
                                    'block_time' => $pending->block_time,
                                    'block_number' => $pending->block_number,
                                    'transfer_amount' => $pending->txn_amount,
                                    'transfer_amount_type' => $pending->transfer_status,
                                    'transaction_number' => $pending->transaction_number,
                                    'amount' => $pending->amount,
                                    'status' => $pending->status,
                                    'txreceipt_status' => $pending->txreceipt_status,
                                    'payment_method' => $pending->payment_method,
                                    'payment_type' => $pending->payment_type,
                                    'created_at' => $pending->created_at,
                                    'description' => $pending->description,
                                    'vCode' => $vCode,
                                    'token' => $token,
                                ];
        
                                $callBackUrl = $payoutSetting->live_paymentUrl . $payoutSetting->callBackUrl;
                                $response = Http::post($callBackUrl, $params);
                                
                                // Log::debug('deposit Callback', $response);
                                Log::debug('deposit Callback', [
                                    'status' => $response->status(),
                                ]);

                            } else {
                                Log::debug('bep-20 txid', ['transaction_id' => $transaction['hash']]);
                            }
                        }
                    }
                }
            }
        }
    }
}
