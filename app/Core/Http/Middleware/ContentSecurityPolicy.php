<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to the guest-facing site. Invitation HTML generated from client
 * templates is injected into the page. MarkupSanitizer cleans it; this
 * policy is the second line of defence: only our own (nonce'd) scripts
 * run, and media/fonts load from known origins only.
 */
class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $media = config('csp.media_origins');
        $scripts = [...$this->templateDiskOrigin(), ...config('csp.script_origins')];
        $dev = Vite::isRunningHot() ? $this->devServerOrigins() : [];

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...$scripts, ...$dev],
            // Templates bring their own <style> (inside a shadow root).
            'style-src' => ["'self'", "'unsafe-inline'", ...config('csp.font_style_origins'), ...$dev],
            'font-src' => ["'self'", 'data:', ...config('csp.font_origins'), ...$dev],
            'img-src' => ["'self'", 'data:', 'blob:', ...$media],
            'media-src' => ["'self'", 'blob:', ...$media],
            'connect-src' => ["'self'", ...config('csp.script_origins'), ...$dev],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        $response->headers->set('Content-Security-Policy', collect($directives)
            ->map(fn (array $sources, string $directive) => $directive.' '.implode(' ', array_unique($sources)))
            ->implode('; '));
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /** @return list<string> where templates' js/ files are served from (it may not be 'self', e.g. S3) */
    private function templateDiskOrigin(): array
    {
        $url = Storage::disk((string) config('emp.template_disk'))->url('templates');
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! $scheme || ! $host) {
            return [];
        }

        $port = parse_url($url, PHP_URL_PORT);

        return ["{$scheme}://{$host}".($port ? ":{$port}" : '')];
    }

    /** @return list<string> the Vite dev server (http + ws) while `npm run dev` is running */
    private function devServerOrigins(): array
    {
        $hot = rtrim((string) file_get_contents(Vite::hotFile()));
        $origin = parse_url($hot, PHP_URL_SCHEME).'://'.parse_url($hot, PHP_URL_HOST).(parse_url($hot, PHP_URL_PORT) ? ':'.parse_url($hot, PHP_URL_PORT) : '');

        return [$origin, preg_replace('#^http#', 'ws', $origin) ?? $origin];
    }
}
