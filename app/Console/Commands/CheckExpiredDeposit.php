<?php

namespace App\Console\Commands;

use App\Models\PayoutConfig;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

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
    protected $description = 'Check deposit statuses has expired over 15 minutes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pendingPayment = Transaction::where('status', 'pending')
                ->where('transaction_type', 'deposit')
                ->whereNull('txID')
                // ->where('created_at', '<', Carbon::now()->addMinutes(15)) // Transactions created more than 15 minutes
                ->get();

        $nowTime = Carbon::now();
        
        foreach ($pendingPayment as $pending) {

            $checkPendingTime = $pending->created_at;
            $expiredTime = Carbon::parse($pending->expired_at);

            if ($nowTime->greaterThan($expiredTime)) {
                Log::debug('expired status', ['transaction' => $pending->toArray()]);
                //function for after $pending created at time is more than 15minutes
                $pending->update([
                    'status' => 'fail',
                ]);

                $payoutSetting = PayoutConfig::where('merchant_id', $pending->merchant_id)->where('live_paymentUrl', $pending->origin_domain)->first();
                $vCode = md5($pending->transaction_number . $payoutSetting->appId . $payoutSetting->merchant_id);
                $token = Str::random(32);

                $params = [
                    'merchant_id' => $pending->merchant_id,
                    'client_id' => $pending->client_id,
                    'client_email' => $pending->client_email,
                    'transaction_type' => $pending->transaction_type,
                    'from_wallet' => $pending->from_wallet,
                    'to_wallet' => $pending->to_wallet,
                    'txID' => $pending->txID,
                    'block_time' => $pending->block_time,
                    'transfer_amount' => $pending->txn_amount,
                    'amount' => $pending->amount,
                    'transaction_number' => $pending->transaction_number,
                    'amount' => $pending->amount,
                    'status' => $pending->status,
                    'transfer_amount_type' => $pending->transfer_status,
                    'payment_method' => $pending->payment_method,
                    'created_at' => $pending->created_at,
                    'description' => $pending->description,
                    'origin_domain' => $pending->origin_domain,
                    'vCode' => $vCode,
                    'token' => $token,
                ];

                $callBackUrl = $payoutSetting->live_paymentUrl . $payoutSetting->callBackUrl;
                $response = Http::post($callBackUrl, $params);

                Log::debug('deposit expired', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        }
    }
}
