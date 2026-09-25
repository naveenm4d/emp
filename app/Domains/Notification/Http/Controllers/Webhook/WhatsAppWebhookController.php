<?php

namespace App\Domains\Notification\Http\Controllers\Webhook;

use App\Core\Http\Controllers\Controller;
use App\Domains\Notification\Contracts\NotificationServiceInterface;
use App\Domains\Notification\DTOs\ProviderStatusUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Meta WhatsApp Cloud API callbacks (routes/webhooks.php). The only
 * machine-to-machine endpoint: no session, no CSRF, signature-verified.
 */
class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly NotificationServiceInterface $notifications,
    ) {}

    /** Meta's subscription handshake. */
    public function verify(Request $request): Response|JsonResponse
    {
        $token = config('services.whatsapp.webhook_verify_token');

        if ($request->query('hub_mode') === 'subscribe'
            && filled($token)
            && hash_equals((string) $token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
        }

        return response()->json(['error' => 'Verification failed.'], 403);
    }

    /**
     * Delivery status callbacks. Always acknowledges with 200 so Meta does
     * not retry payloads we intentionally ignore.
     */
    public function handle(Request $request): JsonResponse
    {
        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['statuses'] ?? []) as $status) {
                    if (! isset($status['id'], $status['status'])) {
                        continue;
                    }

                    $this->notifications->applyStatusUpdate(new ProviderStatusUpdate(
                        messageId: (string) $status['id'],
                        status: (string) $status['status'],
                        occurredAt: isset($status['timestamp']) ? CarbonImmutable::createFromTimestamp((int) $status['timestamp']) : null,
                        error: $status['errors'][0]['title'] ?? null,
                        payload: $status,
                    ));
                }
            }
        }

        return response()->json(['status' => 'received']);
    }
}
