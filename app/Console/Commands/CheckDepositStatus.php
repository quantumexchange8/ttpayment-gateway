<?php

namespace App\Console\Commands;

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
            // $min_timeStamp = strtotime($createdAt->getTimestampMs());
            $min_timeStamp = $createdAt->timestamp * 1000;

            // $response = Http::withHeaders([
            //     'accept' => 'application/json',
            // ])->get('https://nile.trongrid.io/v1/accounts/' . $tokenAddress .'/transactions/trc20', [
            //     'min_timestamp' => $min_timeStamp,
            //     'only_to' => true,
            // ]);

            $response = Http::get('https://nile.trongrid.io/v1/accounts/'. $tokenAddress .'/transactions/trc20', [
                'min_timestamp' => $min_timeStamp,
                'only_to' => true,
            ]);
            
            if ($response->successful()) {
                // $transactionInfo = $response->json()->toArray();
                $transactionInfo = $response->json();

                $decodedTransactions = json_decode($transactionInfo);

                foreach($decodedTransactions['data'] as $data) {
                    Log::debug('data', $data);
                    Log::debug('log data->transaction_id', $data['transaction_id']);
                }

                // if (is_array($transactionInfo)) {
                //     Log::debug('CallBack Api transactionInfo', ['transaction' => $transactionInfo]);
                // } else {
                //     Log::warning('Unexpected transactionInfo type', ['type' => gettype($transactionInfo)]);
                // }

                // if (!empty($transactionInfo['data'])) {
                //     foreach ($transactionInfo['data'] as $transactions) {

                //         if (is_array($transactions)) {
                //             Log::debug('All transactions', $transactions);
                //         } else {
                //             Log::warning('Unexpected All transactions type', ['type' => gettype($transactions)]);
                //         }

                //         foreach ($transactions as $transaction) {

                //             if (is_array($transaction)) {
                //                 Log::debug('looped transaction', $transaction);
                //             } else {
                //                 Log::warning('Unexpected transactionInfo type', ['type' => gettype($transaction)]);
                //             }

                //             if (Transaction::where('txID', $transaction['transaction_id'])->doesntExist()) {
                //                 Log::debug('Transaction ID does not exist');
    
                //                 $txnAmount = $transaction['value'] / 1000000;
                //                 $timestamp = $transaction['block_timestamp'] / 1000;
                //                 $transaction_date = Carbon::createFromTimestamp($timestamp);
    
                //                 $pending->update([
                //                     'from_wallet' => $transaction['from'],
                //                     'txID' => $transaction['transaction_id'],
                //                     'block_time' => $transaction['block_timestamp'],
                //                     'txn_amount' => $txnAmount,
                //                     'transaction_date' => $transaction_date,
                //                     'status' => 'success',
                //                 ]);
    
                //                 $payoutSetting = config('payment-gateway');
                //                 $domain = $_SERVER['HTTP_HOST'];
    
                //                 $selectedPayout = $payoutSetting['robotec'];
                //                 $vCode = md5($pending->transaction_number . $selectedPayout['appId'] . $selectedPayout['merchantId']);
                //                 $token = Str::random(32);
    
                //                 $params = [
                //                     'merchant_id' => $pending->merchant_id,
                //                     'client_id' => $pending->client_id,
                //                     'transaction_type' => $pending->transaction_type,
                //                     'from_wallet' => $pending->from_wallet,
                //                     'to_wallet' => $pending->to_wallet,
                //                     'txID' => $pending->txID,
                //                     'block_time' => $pending->block_time,
                //                     'transfer_amount' => $pending->txn_amount,
                //                     'transaction_number' => $pending->transaction_number,
                //                     'amount' => $pending->amount,
                //                     'status' => $pending->status,
                //                     'payment_method' => $pending->payment_method,
                //                     'created_at' => $pending->created_at,
                //                     'description' => $pending->description,
                //                     'vCode' => $vCode,
                //                     'token' => $token,
                //                 ];
    
                //                 $callBackUrl = $selectedPayout['paymentUrl'] . $selectedPayout['callBackUrl'];
                //                 $response = Http::post($callBackUrl, $params);
    
                //                 // Log::debug('$pending', $pending);
                //             } else {
                //                 Log::debug('txid', $transaction['transaction_id']);
                //             }
                //         }

                //     }
                // } else {
                //     Log::debug('No transaction data found.');
                // }

                // Log::debug('CallBack Api transactionInfo', ['transaction' => $transactionInfo->toArray()]);
            } else {
                return response()->json(['error' => 'Failed to fetch transactions']);
            }
        }
    }
}
