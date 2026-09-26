<?php

namespace App\Domains\Template\Support;

use App\Domains\Template\Exceptions\InvalidTemplateException;

/**
 * Rejects template code that could run script or load active content.
 *
 * Templates are display-only: the guest frontend adds the only interactive
 * parts (RSVP buttons, music toggle). This is a deny-list run at import time
 * and backed by the frontend's Content-Security-Policy; before clients can
 * author templates, replace it with a full allow-list HTML sanitizer.
 */
final class MarkupSanitizer
{
    /** @var array<string, string> pattern => reason */
    private const MARKUP_RULES = [
        '/<\s*\/?\s*(script)\b/i' => 'contains a <$1> tag (put scripts in js/*.js instead)',
        '/<\s*\/?\s*(iframe|frame|frameset|object|embed|applet|base|meta|link|form|style|noscript|template)\b/i' => 'contains a forbidden tag (<$1>)',
        '/[\s\/"\']on[a-z]+\s*=/i' => 'contains an inline event handler (on…=)',
        '/\b(javascript|vbscript|livescript)\s*:/i' => 'contains a script URL',
        '/data\s*:\s*(text\/html|application|image\/svg)/i' => 'contains an active data: URL',
        '/\bsrcdoc\s*=/i' => 'contains srcdoc',
        '/\b(formaction|xlink:href)\s*=/i' => 'contains a forbidden attribute',
        '/http-equiv/i' => 'contains http-equiv',
    ];

    /** @var array<string, string> */
    private const STYLE_RULES = [
        '/@import/i' => 'uses @import (list fonts in template.json instead)',
        '/expression\s*\(/i' => 'uses expression()',
        '/\b(javascript|vbscript)\s*:/i' => 'contains a script URL',
        '/behavior\s*:|-moz-binding/i' => 'uses behavior/-moz-binding',
        '/<\s*\/?\s*(style|script)/i' => 'contains an HTML tag',
    ];

    public function assertSafeMarkup(string $markup): void
    {
        $this->check($markup, self::MARKUP_RULES, 'index.html');
    }

    /** @param  string  $file  the package path, for the error message */
    public function assertSafeStyles(string $styles, string $file = 'css'): void
    {
        $this->check($styles, self::STYLE_RULES, $file);
    }

    /** @param array<string, string> $rules */
    private function check(string $source, array $rules, string $file): void
    {
        // Also check the entity-decoded form so "javascript&#58;" is caught.
        $decoded = html_entity_decode($source, ENT_QUOTES | ENT_HTML5);

        foreach ($rules as $pattern => $reason) {
            foreach ([$source, $decoded] as $candidate) {
                if (preg_match($pattern, $candidate, $match)) {
                    throw new InvalidTemplateException("{$file} ".str_replace('$1', strtolower($match[1] ?? ''), $reason).'.');
                }
            }
        }
    }
}
