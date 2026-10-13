<?php

namespace App\Domains\Billing\Http\Controllers\Admin;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Billing\Contracts\BillingQueryServiceInterface;
use App\Domains\Billing\Contracts\PaymentServiceInterface;
use App\Domains\Billing\DTOs\PaymentFilters;
use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Http\Requests\Admin\RefundPaymentRequest;
use App\Domains\Billing\Http\Resources\PaymentResource;
use App\Domains\Billing\Models\Payment;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Every payment on the platform, and refunds. */
class PaymentController extends InertiaController
{
    public function index(Request $request, BillingQueryServiceInterface $billing): Response
    {
        $filters = PaymentFilters::fromArray($request->only(['search', 'status', 'purpose', 'client_id']));

        return Inertia::render('admin/billing/payments', [
            'payments' => PaymentResource::collection($billing->search($filters)),
            'filters' => [
                'search' => $filters->search,
                'status' => $filters->status?->value,
                'purpose' => $filters->purpose?->value,
            ],
            'statuses' => PaymentStatus::recordedOptions(),
            'purposes' => PaymentPurpose::options(),
            'mockGateway' => $billing->usesMockGateway(),
        ]);
    }

    public function refund(RefundPaymentRequest $request, Payment $payment, PaymentServiceInterface $payments): RedirectResponse
    {
        /** @var StaffMember $staff */
        $staff = $request->user('staff');

        $payments->refund($payment, $staff, $request->note());

        return $this->backWithSuccess("{$payment->order_id} refunded. What was bought stays; change the plan separately if needed.");
    }
}
