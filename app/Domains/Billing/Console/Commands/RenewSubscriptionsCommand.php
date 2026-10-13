<?php

namespace App\Domains\Billing\Console\Commands;

use App\Domains\Billing\Contracts\RenewalServiceInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:renew-subscriptions')]
#[Description('Charge Business renewals that are due, end canceled subscriptions, send renewal reminders and tidy up payments')]
class RenewSubscriptionsCommand extends Command
{
    public function handle(RenewalServiceInterface $renewals): int
    {
        $result = $renewals->run();

        $this->components->info(sprintf(
            'Renewed %d, failed %d, ended %d, downgraded %d, reminded %d, applied %d, abandoned %d.',
            $result['renewed'], $result['failed'], $result['ended'], $result['downgraded'], $result['reminded'], $result['applied'], $result['abandoned'],
        ));

        return self::SUCCESS;
    }
}
