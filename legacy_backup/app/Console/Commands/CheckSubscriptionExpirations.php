<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Console\Command;

class CheckSubscriptionExpirations extends Command
{
    protected $signature = 'subscriptions:check-expirations';
    protected $description = 'Check and expire subscriptions that passed end date';

    public function handle()
    {
        $expired = Subscription::where('status', SubscriptionStatus::ACTIVE)
            ->where('ends_at', '<', now())
            ->update(['status' => SubscriptionStatus::EXPIRED]);

        $this->info("Updated {$expired} expired subscriptions.");
    }
}
