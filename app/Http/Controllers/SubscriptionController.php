<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    public function index(Request $request): View
    {
        $query = Subscription::with(['customer', 'product', 'bundle'])->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->whereHas('customer', function ($q) use ($s) {
                $q->where('name', 'LIKE', "%{$s}%")
                  ->orWhere('email', 'LIKE', "%{$s}%");
            });
        }

        $subscriptions = $query->paginate(15)->withQueryString();

        $counts = [
            'all' => Subscription::count(),
            'active' => Subscription::where('status', Subscription::STATUS_ACTIVE)->count(),
            'paused' => Subscription::where('status', Subscription::STATUS_PAUSED)->count(),
            'cancelled' => Subscription::where('status', Subscription::STATUS_CANCELLED)->count(),
        ];

        return view('subscriptions.index', compact('subscriptions', 'counts'));
    }

    public function show(Subscription $subscription): View
    {
        $subscription->load(['customer', 'product', 'bundle', 'sales' => fn($q) => $q->latest()->limit(10)]);
        return view('subscriptions.show', compact('subscription'));
    }

    public function pause(Subscription $subscription): RedirectResponse
    {
        $this->subscriptionService->pause($subscription);
        return back()->with('success', "Subscription #{$subscription->id} has been paused.");
    }

    public function resume(Subscription $subscription): RedirectResponse
    {
        $this->subscriptionService->resume($subscription);
        return back()->with('success', "Subscription #{$subscription->id} has been resumed.");
    }

    public function cancel(Request $request, Subscription $subscription): RedirectResponse
    {
        $reason = $request->input('reason', 'Cancelled by administrator');
        $this->subscriptionService->cancel($subscription, $reason);
        return back()->with('success', "Subscription #{$subscription->id} has been cancelled.");
    }

    public function triggerRenewals(): RedirectResponse
    {
        $results = $this->subscriptionService->processRenewals();
        return back()->with(
            'success',
            "Renewal batch completed: {$results['succeeded']} renewed, {$results['failed']} failed."
        );
    }
}

