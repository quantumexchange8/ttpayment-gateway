<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\MerchantWallet;
use App\Models\MerchantWalletAdrress;
use App\Models\PayoutConfig;
use App\Models\RateProfile;
use App\Models\Transaction;
use App\Models\WalletAddress;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckDepositStatus extends Command
{
    protected $signature = 'check:deposit-status';
    protected $description = 'Check deposit statuses has txid';

    public function handle()
    {
        $pendingPayments = Transaction::where('status', 'pending')
            ->where('transaction_type', 'deposit')
            ->where('payment_method', 'auto')
            ->latest()
            ->get();

        if ($pendingPayments->isEmpty()) {
            Log::info('No pending payments found. Exiting command.');
            return;
        }

        // 将 Collection 转换为数组
        $pendingPaymentsArray = $pendingPayments->toArray();
        
        while ($pending = $pendingPayments->shift()) {
            // $pending 是 Transaction 模型实例
            $this->processPendingPayment($pending);
        }

        Log::info('CheckDepositStatus command completed. All pending payments processed.');
    }

    protected function processPendingPayment(Transaction $pending)
    {
        Log::debug('Processing pending payment', ['transaction' => $pending->toArray()]);

        $merchant = Merchant::find($pending->merchant_id);
        if (!$merchant) {
            Log::error('Merchant not found', ['merchant_id' => $pending->merchant_id]);
            return;
        }

        $merchantWallet = MerchantWallet::where('merchant_id', $merchant->id)->first();
        if (!$merchantWallet) {
            Log::error('Merchant wallet not found', ['merchant_id' => $merchant->id]);
            return;
        }

        $payoutSetting = PayoutConfig::where('merchant_id', $pending->merchant_id)
            ->where('live_paymentUrl', $pending->origin_domain)
            ->first();

        if (!$payoutSetting) {
            Log::error('Payout setting not found', ['merchant_id' => $pending->merchant_id]);
            return;
        }

        if ($pending->payment_type === 'trc-20') {
            $this->processTrc20Payment($pending, $merchant, $merchantWallet, $payoutSetting);
        } elseif ($pending->payment_type === 'bep-20') {
            $this->processBep20Payment($pending, $merchant, $merchantWallet, $payoutSetting);
        }
    }

    protected function processTrc20Payment(Transaction $pending, Merchant $merchant, MerchantWallet $merchantWallet, PayoutConfig $payoutSetting)
    {
        if ($merchant->deposit_type === "2" || $merchant->deposit_type === "3")  {

            $response = Http::withHeaders(['TRON-PRO-API-KEY' => $payoutSetting->api_key])->get('https://api.trongrid.io/v1/accounts/' . $pending->to_wallet . '/transactions/trc20', [
                'min_timestamp' => $pending->created_at->timestamp * 1000,
                'only_to' => true,
            ]);
            // $response = Http::get('https://api.trongrid.io/v1/accounts/' . $pending->to_wallet . '/transactions/trc20', [
            //     'min_timestamp' => $pending->created_at->timestamp * 1000,
            //     'only_to' => true,
            // ]);
    
            if (!$response->successful()) {
                Log::error('Failed to fetch TRC-20 transactions', ['response' => $response->json()]);
                return;
            }
    
            $transactionInfo = $response->json();
            if (empty($transactionInfo['data'])) {
                Log::debug('No TRC-20 transactions found');
                return;
            }
    
            Log::debug('transaction', ['response receive' => $transactionInfo]);

            foreach ($transactionInfo['data'] as $transaction) {

                $this->updateTransaction($pending, $transaction, $merchant, $merchantWallet, $payoutSetting, 'trc-20');
            }

        } else {
            $response = Http::withHeaders(['TRON-PRO-API-KEY' => $payoutSetting->api_key])->get('https://api.trongrid.io/v1/accounts/' . $pending->to_wallet . '/transactions/trc20', [
                'min_timestamp' => $pending->created_at->timestamp * 1000,
                'only_to' => true,
            ]);
            // $response = Http::get('https://api.trongrid.io/v1/accounts/' . $pending->to_wallet . '/transactions/trc20', [
            //     'min_timestamp' => $pending->created_at->timestamp * 1000,
            //     'only_to' => true,
            // ]);
    
            if (!$response->successful()) {
                Log::error('Failed to fetch TRC-20 transactions', ['response' => $response->json()]);
                return;
            }
    
            $transactionInfo = $response->json();
            if (empty($transactionInfo['data'])) {
                Log::debug('No TRC-20 transactions found');
                return;
            }

            Log::debug('transaction', ['response receive' => $transactionInfo]);

            foreach ($transactionInfo['data'] as $transaction) {
    
                $apiAmount = $transaction['value'] / 1000000; // 转换为实际金额
    
                // 检查金额是否在允许的范围内
                $startRange = $pending->amount - $payoutSetting->diff_amount;
                $endRange = $pending->amount + $payoutSetting->diff_amount;
    
                if ($apiAmount >= $startRange && $apiAmount <= $endRange) {
    
                    // 如果金额匹配，则更新交易
                    $this->updateTransaction($pending, $transaction, $merchant, $merchantWallet, $payoutSetting, 'trc-20');
                    break;
                } else {
                    Log::debug('Skipping transaction due to amount mismatch', [
                        'pending_amount' => $pending->amount,
                        'api_amount' => $apiAmount,
                    ]);
                }
            }
        }
    }

    protected function processBep20Payment(Transaction $pending, Merchant $merchant, MerchantWallet $merchantWallet, PayoutConfig $payoutSetting)
    {
        $blockTimeStamp = $pending->created_at->timestamp;
        $getStartBlock = Http::get('https://api.bscscan.com/api', [
            'module' => 'block',
            'action' => 'getblocknobytime',
            'timestamp' => $blockTimeStamp,
            'closest' => 'after',
            'apikey' => $payoutSetting->api_key,
        ]);

        if (!$getStartBlock->successful()) {
            Log::error('Failed to get start block', ['response' => $getStartBlock->json()]);
            return;
        }

        $startBlock = $getStartBlock['result'];

        $txListResponse = Http::get('https://api.bscscan.com/api', [
            'module' => 'account',
            'action' => 'txlist',
            'address' => $pending->to_wallet,
            'startblock' => $startBlock,
            'endblock' => 99999999,
            'sort' => 'desc',
            'apikey' => $payoutSetting->api_key,
        ]);

        $tokenTxResponse = Http::get('https://api.bscscan.com/api', [
            'module' => 'account',
            'action' => 'tokentx',
            'address' => $pending->to_wallet,
            'startblock' => $startBlock,
            'endblock' => 99999999,
            'sort' => 'desc',
            'apikey' => $payoutSetting->api_key,
        ]);

        if (!$txListResponse->successful() || !$tokenTxResponse->successful()) {
            Log::error('Failed to fetch BEP-20 transactions', [
                'txListResponse' => $txListResponse->json(),
                'tokenTxResponse' => $tokenTxResponse->json(),
            ]);
            return;
        }

        $transactions = array_merge($txListResponse->json()['result'], $tokenTxResponse->json()['result']);
        foreach ($transactions as $transaction) {

            $apiAmount = $transaction['value'] / 1000000000000000000; // 转换为实际金额

            // 检查金额是否在允许的范围内
            $startRange = $pending->amount - $payoutSetting->diff_amount;
            $endRange = $pending->amount + $payoutSetting->diff_amount;

            if ($apiAmount >= $startRange && $apiAmount <= $endRange) {
                // 如果金额匹配，则更新交易
                $this->updateTransaction($pending, $transaction, $merchant, $merchantWallet, $payoutSetting, 'bep-20');
                break; // 找到匹配的交易后跳出循环
            } else {
                Log::debug('Skipping transaction due to amount mismatch', [
                    'pending_amount' => $pending->amount,
                    'api_amount' => $apiAmount,
                ]);
            }
            
        }
    }

    protected function updateTransaction(Transaction $pending, array $transaction, Merchant $merchant, MerchantWallet $merchantWallet, PayoutConfig $payoutSetting, string $paymentType)
    {
        $txID = $transaction['transaction_id'] ?? $transaction['hash'] ?? null;
        if (!$txID || Transaction::where('txID', $txID)->exists()) {
            Log::debug('Transaction ID already exists or is invalid', ['txID' => $txID]);
            return;
        }

        $findWallet = WalletAddress::where('token_address', $pending->to_wallet)->first();
        $findWalletAddress = MerchantWalletAdrress::where('merchant_id', $pending->merchant_id)->where('wallet_address_id', $findWallet->id)->first();

        $txnAmount = $paymentType === 'trc-20' ? $transaction['value'] / 1000000 : $transaction['value'] / 1000000000000000000;
        $timestamp = $paymentType === 'trc-20' ? $transaction['block_timestamp'] / 1000 : $transaction['timeStamp'];
        $transactionDate = Carbon::createFromTimestamp($timestamp)->setTimezone('GMT+8');

        $merchantRateProfile = RateProfile::find($merchant->rate_id);
        $fee = ($txnAmount * $merchantRateProfile->deposit_fee) / 100;

        // 检查金额范围
        $startRange = $pending->amount - $payoutSetting->diff_amount;
        $endRange = $pending->amount + $payoutSetting->diff_amount;
        $apiAmount = $paymentType === 'trc-20' ? $transaction['value'] / 1000000 : $transaction['value'] / 1000000000000000000;

        if ($merchant->deposit_type === "2") {
            $formattedApiAmount = floor($apiAmount * 100) / 100;

            $transferStatus = ($formattedApiAmount == $pending->amount) ? 'valid' : 'invalid';
        } else {
            $transferStatus = ($apiAmount >= $startRange && $apiAmount <= $endRange) ? 'valid' : 'invalid';
        }

        $pending->update([
            'from_wallet' => $transaction['from'],
            'txID' => $txID,
            'block_time' => $paymentType === 'trc-20' ? $transaction['block_timestamp'] : $transaction['timeStamp'],
            'block_number' => $transaction['blockNumber'] ?? null,
            'txn_amount' => $txnAmount,
            'fee' => $fee,
            'total_amount' => $txnAmount - $fee,
            'transaction_date' => $transactionDate,
            'status' => 'success',
            'transfer_status' => $transferStatus,
            'txreceipt_status' => $transaction['txreceipt_status'] ?? null,
            'token_symbol' => $transaction['token_info']['symbol'] ?? $transaction['tokenSymbol'] ?? null,
        ]);

        $findWalletAddress->status = 'unassigned';
        $findWalletAddress->save();

        if ($pending->transaction_type === 'deposit') {
            $merchantWallet->gross_deposit += $txnAmount;
            $grossFee = ($merchantWallet->gross_deposit * $merchantRateProfile->withdrawal_fee) / 100;
            $merchantWallet->total_fee += $grossFee;
            $merchantWallet->net_deposit = $merchantWallet->gross_deposit - $grossFee;
            $merchantWallet->total_deposit += $txnAmount;
            $merchantWallet->save();
        }

        $this->sendCallback($pending, $payoutSetting);
    }

    protected function sendCallback(Transaction $pending, PayoutConfig $payoutSetting)
    {
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

        Log::debug('Callback response', ['status' => $response->status()]);
    }
}