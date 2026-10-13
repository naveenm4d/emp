<?php

namespace App\Domains\Billing\Providers;

use App\Core\Providers\DomainServiceProvider;
use App\Domains\Billing\Console\Commands\RenewSubscriptionsCommand;
use App\Domains\Billing\Contracts\BillingPriceRepositoryInterface;
use App\Domains\Billing\Contracts\BillingQueryServiceInterface;
use App\Domains\Billing\Contracts\CheckoutRepositoryInterface;
use App\Domains\Billing\Contracts\CheckoutServiceInterface;
use App\Domains\Billing\Contracts\PaymentGatewayInterface;
use App\Domains\Billing\Contracts\PaymentMethodRepositoryInterface;
use App\Domains\Billing\Contracts\PaymentMethodServiceInterface;
use App\Domains\Billing\Contracts\PaymentRepositoryInterface;
use App\Domains\Billing\Contracts\PaymentServiceInterface;
use App\Domains\Billing\Contracts\PriceServiceInterface;
use App\Domains\Billing\Contracts\RenewalServiceInterface;
use App\Domains\Billing\Contracts\SubscriptionRepositoryInterface;
use App\Domains\Billing\Contracts\SubscriptionServiceInterface;
use App\Domains\Billing\Events\PaymentFailed;
use App\Domains\Billing\Events\PaymentSucceeded;
use App\Domains\Billing\Gateways\MockPaymentGateway;
use App\Domains\Billing\Gateways\PayHereGateway;
use App\Domains\Billing\Listeners\FollowStaffPlanChange;
use App\Domains\Billing\Listeners\SendPaymentReceipt;
use App\Domains\Billing\Listeners\SendRenewalFailedNotice;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Policies\CheckoutPolicy;
use App\Domains\Billing\Policies\PaymentMethodPolicy;
use App\Domains\Billing\Policies\PaymentPolicy;
use App\Domains\Billing\Policies\SubscriptionPolicy;
use App\Domains\Billing\Repositories\BillingPriceRepository;
use App\Domains\Billing\Repositories\CheckoutRepository;
use App\Domains\Billing\Repositories\PaymentMethodRepository;
use App\Domains\Billing\Repositories\PaymentRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Billing\Services\BillingQueryService;
use App\Domains\Billing\Services\CheckoutService;
use App\Domains\Billing\Services\PaymentMethodService;
use App\Domains\Billing\Services\PaymentService;
use App\Domains\Billing\Services\PriceService;
use App\Domains\Billing\Services\RenewalService;
use App\Domains\Billing\Services\SubscriptionService;
use App\Domains\Client\Events\ClientPlanChanged;

class BillingServiceProvider extends DomainServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        BillingPriceRepositoryInterface::class => BillingPriceRepository::class,
        PaymentMethodRepositoryInterface::class => PaymentMethodRepository::class,
        PaymentRepositoryInterface::class => PaymentRepository::class,
        CheckoutRepositoryInterface::class => CheckoutRepository::class,
        SubscriptionRepositoryInterface::class => SubscriptionRepository::class,
        PriceServiceInterface::class => PriceService::class,
        PaymentServiceInterface::class => PaymentService::class,
        PaymentMethodServiceInterface::class => PaymentMethodService::class,
        SubscriptionServiceInterface::class => SubscriptionService::class,
        CheckoutServiceInterface::class => CheckoutService::class,
        RenewalServiceInterface::class => RenewalService::class,
        BillingQueryServiceInterface::class => BillingQueryService::class,
    ];

    /** @var array<class-string, class-string> */
    protected array $policies = [
        Payment::class => PaymentPolicy::class,
        Checkout::class => CheckoutPolicy::class,
        PaymentMethod::class => PaymentMethodPolicy::class,
        Subscription::class => SubscriptionPolicy::class,
    ];

    /** @var array<class-string, list<class-string>> */
    protected array $listen = [
        PaymentSucceeded::class => [SendPaymentReceipt::class],
        PaymentFailed::class => [SendRenewalFailedNotice::class],
        ClientPlanChanged::class => [FollowStaffPlanChange::class],
    ];

    public function register(): void
    {
        // PayHere when it has a merchant ID and secret; otherwise the mock (local runs never reach PayHere).
        $this->app->bind(PaymentGatewayInterface::class, function (): PaymentGatewayInterface {
            $config = (array) config('services.payhere');

            if (blank($config['merchant_id'] ?? null) || blank($config['merchant_secret'] ?? null)) {
                return new MockPaymentGateway;
            }

            return new PayHereGateway(
                merchantId: (string) $config['merchant_id'],
                merchantSecret: (string) $config['merchant_secret'],
                appId: $config['app_id'] ?? null,
                appSecret: $config['app_secret'] ?? null,
                sandbox: (bool) ($config['sandbox'] ?? true),
                notifyUrl: $config['notify_url'] ?? null,
            );
        });
    }

    protected function bootDomain(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RenewSubscriptionsCommand::class]);
        }
    }
}
