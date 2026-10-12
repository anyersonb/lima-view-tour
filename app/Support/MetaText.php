<?php

namespace App\Support;

/**
 * Plain-text normalisation for <title> / meta description.
 *
 * Child views feed the layout with `@section('title', $expr)`, and Blade
 * escapes that inline form (e()); layouts/app.blade.php then escapes the
 * yielded string again with {{ }}. A "&" typed in the CMS ended up as
 * "&amp;amp;" (visible "&amp;" in the SERP / share card). A value that was
 * saved already carrying an entity ("&amp;") is escaped a third time.
 *
 * Decoding until stable brings every case back to the plain text, which the
 * layout then escapes exactly once. Safe against injection: the output of
 * this method is NEVER printed raw, only through {{ }}.
 */
final class MetaText
{
    public static function plain(?string $value): string
    {
        $text = (string) $value;

        // Max 3 rounds: raw -> e() -> stored entity -> e() again. Bounded so a
        // pathological string can never spin.
        for ($i = 0; $i < 3; $i++) {
            $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $text) {
                break;
            }
            $text = $decoded;
        }

        return trim($text);
    }
}
