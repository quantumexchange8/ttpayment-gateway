<?php

namespace App\Console\Commands;

use App\Models\MerchantWallet;
use App\Models\PayoutConfig;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                    ->latest()
                    ->get();

        
        foreach ($pendingPayments as $pending) {
            Log::debug('all pending data', ['transaction' => $pending->toArray()]);

            $tokenAddress = $pending->to_wallet;
            $createdAt = $pending->created_at;
            $min_timeStamp = $createdAt->timestamp * 1000;
            $merchant = $pending->merchant_id;
            $merchantWallet = MerchantWallet::where('merchant_id', $merchant)->first();
                       
            $response = Http::get('https://nile.trongrid.io/v1/accounts/'. $tokenAddress .'/transactions/trc20', [
                'min_timestamp' => $min_timeStamp,
                'only_to' => true,
            ]);

            // $response = Http::get('https://api.trongrid.io/v1/accounts/'. $tokenAddress .'/transactions/trc20', [
            //     'min_timestamp' => $min_timeStamp,
            //     'only_to' => true,
            // ]);
            
            if ($response->successful()) {
                $transactionInfo = $response->json();

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
                            $fee = 0.00;

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
                                $merchantWallet->gross_deposit += $txnAmount;
                                $merchantWallet->net_deposit += $pending->total_amount;
                                $merchantWallet->deposit_fee += $pending->fee;

                                $merchantWallet->save();
                            }
    
                            $payoutSetting = PayoutConfig::where('merchant_id', $pending->merchant_id)->first();
                            // $payoutSetting = config('payment-gateway');
    
                            // $selectedPayout = $payoutSetting['robotec_live'];
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
                            
                        } else {
                            Log::debug('txid', ['transaction_id' => $transaction['transaction_id']]);
                        }
                    }
                }
            } else {
                return response()->json(['error' => 'Failed to fetch transactions']);
            }
        }
    }
}
