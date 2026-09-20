<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $paidRevenue = Order::query()->whereIn('status', ['paid', 'fulfilled'])->sum('total');

        return view('admin.dashboard', [
            'stats' => [
                'revenue' => $paidRevenue,
                'orders' => Order::count(),
                'pendingPayments' => Payment::whereIn('status', ['initiated', 'pending'])->count(),
                'resources' => Resource::count(),
                'users' => User::count(),
                'entitlements' => Entitlement::count(),
                'downloads' => Download::count(),
                'webhookErrors' => WebhookEvent::where('status', 'rejected')->count(),
            ],
            'orders' => Order::with(['user', 'payments'])->latest()->limit(6)->get(),
            'payments' => Payment::with('order.user')->latest()->limit(6)->get(),
            'webhooks' => WebhookEvent::latest()->limit(6)->get(),
            'resources' => Resource::with(['author', 'resourceType', 'schoolClass'])->latest()->limit(6)->get(),
        ]);
    }

    public function orders(): View
    {
        return view('admin.orders.index', [
            'orders' => Order::with(['user', 'items.resource', 'payments'])->latest()->paginate(25),
        ]);
    }

    public function payments(): View
    {
        return view('admin.payments.index', [
            'payments' => Payment::with('order.user', 'order.loan.book')->latest()->paginate(25),
            'environment' => config('payments.environment'),
            'enabledProviders' => config('payments.enabled'),
            'defaultProvider' => config('payments.default'),
        ]);
    }

    public function webhooks(): View
    {
        return view('admin.webhooks.index', [
            'webhooks' => WebhookEvent::latest()->paginate(25),
        ]);
    }

    public function users(): View
    {
        return view('admin.users.index', [
            'users' => User::withCount(['orders', 'resources', 'entitlements'])->latest()->paginate(25),
        ]);
    }
}
