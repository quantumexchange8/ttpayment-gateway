<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $pendingPayment = Transaction::where('status', 'pending')
                        ->whereBetween('created_at', [now()->subMinutes(30), now()])
                        ->get();
        
        foreach ($pendingPayment as $pending) {
            Log::debug('all pending data', $pending);
            
            $tokenAddress = $pending->to_wallet;
            $createdAt = $pending->created_at;
            // $min_timeStamp = strtotime($createdAt->getTimestampMs());
            $min_timeStamp = $createdAt->timestamp * 1000;

            $response = Http::withHeaders([
                'accept' => 'application/json',
            ])->get('https://nile.trongrid.io/v1/accounts/' . $tokenAddress .'/transactions/trc20', [
                'min_timestamp' => $min_timeStamp,
                'only_to' => true,
            ]);
    
            if ($response->successful()) {
                $transactionInfo = $response->json();

                if (isset($transactionInfo['data'])) {
                    foreach ($transactionInfo['data'] as $transaction) {
                        Log::debug('Transaction Details', ['transaction' => $transaction->toArray()]);

                        if (Transaction::where('txID', $transaction['transaction_id'])->exists()) {
                            Log::debug('no exist txid', $transaction['transaction_id']);
                            
                        } else {
                            Log::debug('Transaction ID does not exist', $transaction['transaction_id']);

                            $txnAmount = $transaction['value'] / 1000000;
                            $timestamp = $transaction['block_timestamp'] / 1000;
                            $transaction_date = Carbon::createFromTimestamp($timestamp);

                            // $pending->update([
                            //     'from_wallet' => $transaction['from'],
                            //     'txID' => $transaction['transaction_id'],
                            //     'block_time' => $transaction['block_timestamp'],
                            //     'txn_amount' => $txnAmount,
                            //     'transaction_date' => $transaction_date,
                            //     'status' => 'success',
                            // ]);

                            $payoutSetting = config('payment-gateway');
                            $domain = $_SERVER['HTTP_HOST'];

                            $selectedPayout = $payoutSetting['robotec'];
                            Log::debug('$pending', $pending);
                        }
                    }
                } else {
                    Log::debug('No transaction data found.');
                }

                Log::debug('CallBack Api transactionInfo', $transactionInfo);

                return $response->json();
            } else {
                return response()->json(['error' => 'Failed to fetch transactions'], 500);
            }
        }
    }
}
