<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckExpiredDeposit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:deposit-expired-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check deposit statuses has expired over 1 day';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pendingPayment = Transaction::where('status', 'pending')
        ->whereNull('txID')
        ->where('created_at', '<', Carbon::now()->subDay()) // Transactions created more than 24 hours ago
        ->get();
        
        foreach ($pendingPayment as $pending) {
            Log::debug('expired status', ['transaction' => $pending->toArray()]);


            $pending->update([
                'status' => 'fail',
            ]);
        }
    }
}
