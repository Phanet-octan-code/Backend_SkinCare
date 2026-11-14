<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ProcessSubscriptionsCommand extends Command
{
    protected $signature = 'skincare:process-subscriptions';
    protected $description = 'Process all due recurring skincare subscriptions and trigger renewal orders';

    public function handle(SubscriptionService $subscriptionService): int
    {
        $this->info('Starting recurring skincare subscription processing...');

        $results = $subscriptionService->processRenewals();

        $this->info("Processed: {$results['processed']} subscriptions");
        $this->info("Successful renewals: {$results['succeeded']}");

        if ($results['failed'] > 0) {
            $this->warn("Failed renewals: {$results['failed']}");
            foreach ($results['errors'] as $err) {
                $this->error("Subscription #{$err['subscription_id']}: {$err['error']}");
            }
        }

        return self::SUCCESS;
    }
}

