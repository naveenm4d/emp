<?php

namespace App\Core\Http\Controllers\Webhook;

use App\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

/**
 * Called by the deploy workflow (.github/workflows/deploy.yml) around the FTP
 * upload, since shared hosting gives the pipeline no shell.
 */
class DeploymentController extends Controller
{
    /**
     * Before the upload: maintenance mode with a prerendered page, so requests
     * never run half-uploaded code. /webhooks/deploy/* stays reachable.
     */
    public function down(): JsonResponse
    {
        Artisan::call('down', ['--render' => 'errors::503', '--retry' => 60]);

        return response()->json(['status' => 'down']);
    }

    /** After the upload: migrate, rebuild caches and come back up. */
    public function finish(): JsonResponse
    {
        set_time_limit(300);

        $succeeded = Artisan::call('app:finish-deployment') === 0;

        return response()->json(
            ['status' => $succeeded ? 'up' : 'failed', 'output' => Artisan::output()],
            $succeeded ? 200 : 500,
        );
    }
}
