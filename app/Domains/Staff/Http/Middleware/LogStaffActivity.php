<?php

namespace App\Domains\Staff\Http\Middleware;

use App\Domains\Client\Models\Client;
use App\Domains\Staff\Contracts\StaffActivityServiceInterface;
use App\Domains\Staff\Models\StaffActivity;
use App\Domains\Staff\Models\StaffMember;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs every successful change staff make in the admin console (the
 * activity log). Reads are not logged, and neither are requests that failed
 * validation or ended in a flash error. Actions a service already logged
 * with more detail (plan changes, extra guests) are skipped.
 */
class LogStaffActivity
{
    /** @var array<string, string> readable descriptions by route name */
    private const array DESCRIPTIONS = [
        'admin.logout' => 'Signed out',
        'admin.staff.store' => 'Added a staff member',
        'admin.clients.update' => 'Updated client account',
        'admin.clients.events.store' => 'Created an event',
        'admin.clients.events.update' => 'Updated an event',
        'admin.clients.events.state' => 'Changed an event\'s state',
        'admin.clients.events.registration.open' => 'Opened registration',
        'admin.clients.events.registration.close' => 'Closed registration',
        'admin.clients.events.message-limits' => 'Changed message limits',
        'admin.clients.events.destroy' => 'Deleted an event',
        'admin.templates.store' => 'Imported a template',
        'admin.templates.update' => 'Updated a template',
        'admin.templates.destroy' => 'Deleted a template',
        'admin.notifications.retry' => 'Retried a failed message',
    ];

    /** @var list<string> request fields never written to the log */
    private const array SECRETS = ['password', 'password_confirmation', 'current_password', '_token', '_method'];

    public function __construct(
        private readonly StaffActivityServiceInterface $activities,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Resolved before the request runs: logging out forgets who it was.
        $staff = $request->user('staff');

        $response = $next($request);

        if ($staff instanceof StaffMember && $this->shouldLog($request, $response)) {
            $action = (string) $request->route()?->getName();
            $subject = $this->subject($request);

            $this->activities->record(
                staff: $staff,
                action: $action,
                description: $this->describe($action, $request, $subject),
                subject: $subject,
                clientId: $this->clientId($request, $subject),
                changes: $this->payload($request),
            );
        }

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || $request->attributes->get(StaffActivity::RECORDED)
            || $response->getStatusCode() >= 400) {
            return false;
        }

        // Flashed during this request: a validation error or a domain error.
        $flashed = $request->hasSession() ? (array) $request->session()->get('_flash.new', []) : [];

        return ! in_array('errors', $flashed, true) && ! in_array('error', $flashed, true);
    }

    /** The first model bound to the route, e.g. the event in /clients/{client}/events/{event}. */
    private function subject(Request $request): ?Model
    {
        $models = array_values(array_filter(
            $request->route()?->parameters() ?? [],
            fn (mixed $value) => $value instanceof Model,
        ));

        // The most specific one: the last bound model (the event rather than its client).
        return $models === [] ? null : $models[count($models) - 1];
    }

    private function clientId(Request $request, ?Model $subject): ?string
    {
        if ($subject instanceof Client) {
            return $subject->id;
        }

        $client = $request->route('client');

        if ($client instanceof Client) {
            return $client->id;
        }

        $clientId = $subject?->getAttribute('client_id');

        return is_string($clientId) ? $clientId : null;
    }

    private function describe(string $action, Request $request, ?Model $subject): string
    {
        $description = self::DESCRIPTIONS[$action] ?? $action;
        $name = $subject?->getAttribute('title') ?? $subject?->getAttribute('name');

        return is_string($name) && $name !== '' ? "{$description}: {$name}" : $description;
    }

    /** @return array<string, mixed> the request input, without secrets; files as their names */
    private function payload(Request $request): array
    {
        $input = $request->except(self::SECRETS);

        array_walk_recursive($input, function (mixed &$value) {
            if ($value instanceof UploadedFile) {
                $value = $value->getClientOriginalName();
            }
        });

        return $input;
    }
}
