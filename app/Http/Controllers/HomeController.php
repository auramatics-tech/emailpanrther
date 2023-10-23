<?php

namespace Acelle\Http\Controllers;

use Illuminate\Http\Request;
use Acelle\Model\Subscription;
use Illuminate\Support\Facades\Http;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        event(new \Acelle\Events\UserUpdated($request->user()->customer));
        $currentTimezone = $request->user()->customer->getTimezone();

        // Last month
        $customer = $request->user()->customer;
        $sendingCreditsUsed = $customer->getCurrentActiveSubscription()->getCreditsUsedDuringPlanCycle('send'); // all time sending credits used
        $sendingCreditsLimit = $customer->getCurrentActiveSubscription()->getCreditsLimit('send');
        $sendingCreditsUsedPercentage = $customer->getCurrentActiveSubscription()->getCreditsUsedPercentageDuringPlanCycle('send');

        if (config('app.cartpaye')) {
            return view('dashboard.cartpaye');
        } else {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('EE_AUTH'),
                'content-type' => 'application/json'
            ])->get(env('EE_BASE') . "/stats");
    
            $ee_stats = $response->json();
            return view('dashboard', [
                'sendingCreditsUsed' => $sendingCreditsUsed,
                'sendingCreditsUsedPercentage' => $sendingCreditsUsedPercentage,
                'sendingCreditsLimit' => $sendingCreditsLimit,
                'currentTimezone' => $currentTimezone,
                'ee_stats' => $ee_stats
            ]);
        }
    }
}
