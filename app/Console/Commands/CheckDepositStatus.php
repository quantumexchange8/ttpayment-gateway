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
    

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check deposit statuses has txid';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $pendingPayments = Transaction::where('status', 'pending')
                    ->where('transaction_type', 'deposit')
                    ->latest()
                    ->get();

        
        foreach ($pendingPayments as $pending) {
            Log::debug('all pending data', ['transaction' => $pending->toArray()]);

            $tokenAddress = $pending->to_wallet;
            $createdAt = $pending->created_at;
            $min_timeStamp = $createdAt->timestamp * 1000;
            $merchantID = $pending->merchant_id;
            $merchant = Merchant::find($merchantID);
            $merchantWallet = MerchantWallet::where('merchant_id', $merchant->id)->first();
                       
            $response = Http::get('https://nile.trongrid.io/v1/accounts/'. $tokenAddress .'/transactions/trc20', [
                'min_timestamp' => $min_timeStamp,
                'only_to' => true,
            ]);

            // $response = Http::get('https://api.trongrid.io/v1/accounts/'. $tokenAddress .'/transactions/trc20', [
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

                                if ($symbol === "USDT") {
                                    $pending->update([
                                        'from_wallet' => $transaction['from'],
                                        'txID' => $transaction['transaction_id'],
                                        'block_time' => $transaction['block_timestamp'],
                                        'txn_amount' => $txnAmount,
                                        'fee' => $fee,
                                        'total_amount' => $txnAmount - $fee,
                                        'transaction_date' => $transaction_date,
                                        'status' => 'success',
                                    ]);

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
        
                                $payoutSetting = PayoutConfig::where('merchant_id', $pending->merchant_id)->where('live_paymentUrl', $pending->origin_domain)->first();
        
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
                                    // 'amount' => $pending->amount,
                                    'status' => $pending->status,
                                    'payment_method' => $pending->payment_method,
                                    'created_at' => $pending->created_at,
                                    'description' => $pending->description,
                                    'vCode' => $vCode,
                                    'token' => $token,
                                ];
        
                                $callBackUrl = $payoutSetting->live_paymentUrl . $payoutSetting->callBackUrl;
                                $response = Http::post($callBackUrl, $params);
                                
                                Log::debug('deposit Callback', $response);
                                
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
    }
}
