<?php

namespace App\Support;

/**
 * Helpers for handling essay answers produced by the WYSIWYG editor.
 *
 * Student essays are now authored in TinyMCE, which emits HTML.
 * We store the HTML as-is, but every consumer must explicitly choose
 * between rendering sanitized HTML or stripping it to plain text.
 */
final class EssayAnswerHtml
{
    /**
     * Tags we deliberately keep when sanitizing for display.
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'span', 'div',
        'strong', 'b', 'em', 'i', 'u', 's',
        'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'pre', 'code',
        'a',
    ];

    /**
     * Return an HTML fragment safe to render with {!! !!}.
     *
     * Strips disallowed tags and any inline event handlers / javascript: URIs.
     */
    public static function sanitize(?string $html): string
    {
        $html = (string) ($html ?? '');
        if ($html === '') {
            return '';
        }

        $allowed = '<'.implode('><', self::ALLOWED_TAGS).'>';
        $clean = strip_tags($html, $allowed);

        // Drop inline event handlers (onclick, onerror, …) in either quote style.
        $clean = (string) preg_replace('/\s+on[a-z]+\s*=\s*"[^"]*"/i', '', $clean);
        $clean = (string) preg_replace("/\\s+on[a-z]+\\s*=\\s*'[^']*'/i", '', $clean);

        // Neutralize javascript:/data: URIs on anchors.
        $clean = (string) preg_replace(
            '/href\s*=\s*("|\')\s*(?:javascript|data|vbscript):[^"\']*("|\')/i',
            'href="#"',
            $clean,
        );

        // Force-open anchor targets to a safe relationship.
        $clean = (string) preg_replace_callback(
            '/<a\b([^>]*)>/i',
            static function (array $m): string {
                $attrs = $m[1];
                if (! preg_match('/\brel\s*=/i', $attrs)) {
                    $attrs .= ' rel="noopener noreferrer"';
                }
                if (! preg_match('/\btarget\s*=/i', $attrs)) {
                    $attrs .= ' target="_blank"';
                }

                return '<a'.$attrs.'>';
            },
            $clean,
        );

        return $clean;
    }

    /**
     * Convert HTML (or plain text) to a plain-text representation.
     *
     * Used for AI grading prompts, CSV exports and any other context where
     * formatting markup would be noise.
     */
    public static function toPlainText(?string $html): string
    {
        $html = (string) ($html ?? '');
        if ($html === '') {
            return '';
        }

        // Replace block boundaries with newlines before stripping tags so that
        // paragraphs and list items survive as readable lines.
        $withBreaks = preg_replace(
            '/<\s*(?:br\s*\/?|\/p|\/li|\/h[1-6]|\/div|\/blockquote)\s*>/i',
            "\n",
            $html,
        ) ?? $html;

        $stripped = strip_tags($withBreaks);
        $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace: collapse runs but keep line breaks.
        $decoded = preg_replace("/[ \t]+/u", ' ', $decoded) ?? $decoded;
        $decoded = preg_replace("/\n{3,}/u", "\n\n", $decoded) ?? $decoded;

        return trim($decoded);
    }

    /**
     * Heuristic: does this answer payload value contain renderable HTML?
     */
    public static function looksLikeHtml(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return (bool) preg_match('/<\s*(p|br|strong|em|u|ul|ol|li|h[1-6]|span|div|a)\b/i', $value);
    }
}
