<?php

namespace App\Domains\Billing\Contracts;

/** The hourly billing run (billing:renew-subscriptions). */
interface RenewalServiceInterface
{
    /**
     * Charges subscriptions due within a day, ends canceled ones that ran out,
     * starts downgrades whose plan ended,
     * sends yearly renewal reminders, retries payments that weren't applied and
     * cancels checkouts nobody finished.
     *
     * @return array{renewed: int, failed: int, ended: int, downgraded: int, reminded: int, applied: int, abandoned: int}
     */
    public function run(): array;
}
