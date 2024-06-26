<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class PaymentSessionTimeout
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        if (Session::has('payment_start_time')) {
            $paymentStartTime = Session::get('payment_start_time');
            $now = Carbon::now();
            $sessionDuration = $now->diffInMinutes($paymentStartTime);

            if ($sessionDuration > 15) {
                // Session expired, clear the session data
                Session::forget('payment_start_time');
                Session::forget('transaction_id');
                Session::forget('amount');
                Session::forget('user_id');

                // Redirect to session expired page or handle accordingly
                return redirect()->route('expired');
            }
        } else {
            // Set the payment start time if not set
            Session::put('payment_start_time', Carbon::now());
        }

        return $next($request);
    }
}
