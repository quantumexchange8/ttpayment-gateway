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

        $pendingPayment = Transaction::where('status', 'pending')->whereBetween('created_at', [now()->subMinutes(30), now()])->get();

        foreach ($pendingPayment as $pending) {
            Log::debug($pending);

            $tokenAddress = $pending->to_wallet;
            $createdAt = $pending->created_at;
            // $min_timeStamp = strtotime($createdAt);
            $maxCreatedAt = $createdAt->addMinutes(15);
            // $max_timeStamp = strtotime($maxCreatedAt);
            
            $response = Http::withHeaders([
                'accept' => 'application/json',
            ])->get('https://nile.trongrid.io/v1/accounts/' . $tokenAddress .'/transactions/trc20', [
                'min_timestamp' => $createdAt,
                'max_timestamp' => $maxCreatedAt,
            ]);
    
            if ($response->successful()) {
                $transactionInfo = $response->json();
                Log::debug($transactionInfo);
                
                return $response->json();
            } else {
                return response()->json(['error' => 'Failed to fetch transactions'], 500);
            }
        }

    }
}
