<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Schedule;

// Nightly: flip overdue tenants to "expired" so SetTenantContext blocks them.
Schedule::call(function () {
    Tenant::whereIn('subscription_status', ['trial', 'active'])
        ->whereNotNull('subscription_renews_at')
        ->whereDate('subscription_renews_at', '<', now())
        ->update(['subscription_status' => 'expired']);
})->dailyAt('01:00');
