<?php

namespace App\Console\Commands;

use App\Models\Transaction;
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
                        // ->whereBetween('created_at', [now()->subMinutes(30), now()])
                        ->get();
        
        foreach ($pendingPayment as $pending) {
            Log::debug($pending);
            
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

                // $pending->update([
                //     'from_wallet' => $transactionInfo
                // ]);

                // Log::debug('responseUrl', $response);
                Log::debug('CallBack Api transactionInfo', $transactionInfo);

                return $response->json();
            } else {
                return response()->json(['error' => 'Failed to fetch transactions'], 500);
            }
        }
    }
}
