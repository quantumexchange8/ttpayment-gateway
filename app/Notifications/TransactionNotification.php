<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransactionNotification extends Notification
{
    use Queueable;

    protected $name;
    protected $txid;
    protected $from_wallet;
    protected $to_wallet;
    protected $amount;
    protected $status;

    /**
     * Create a new notification instance.
     */
    public function __construct($name, $txid, $from_wallet, $to_wallet, $amount, $status)
    {
        $this->name = $name;
        $this->txid = $txid;
        $this->from_wallet = $from_wallet;
        $this->to_wallet = $to_wallet;
        $this->amount = $amount;
        $this->status = $status;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('TT-Payment - Transaction')
                    ->line('Merchant: ' . $this->name)
                    ->line('TxID: ' . $this->txid)
                    ->line('From Wallet: ' . $this->from_wallet)
                    ->line('To Wallet: ' . $this->to_wallet)
                    ->line('Amount: $' . $this->amount)
                    ->line('Status: ' . $this->status);
                    // ->action('Notification Action', url('/'))
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
