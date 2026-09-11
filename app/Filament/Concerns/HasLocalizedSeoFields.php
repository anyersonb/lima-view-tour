<?php

namespace App\Filament\Concerns;

use Closure;
use Illuminate\Support\Str;

/**
 * Shared helpers for the "SEO — [idioma]" blocks added to TourResource,
 * PageResource and BlogPostResource (ESPASEO proposal, 2026-08-17).
 *
 * Unified criterion across the three resources (decided 2026-08-17 to avoid
 * truncating meta titles already loaded on the blog, which used a 70-char
 * hard cap): meta title hard cap 70 / recommended 50-60, meta description
 * hard cap 160 / recommended 150-160. The live character counter shows the
 * count against the hard cap and flags whether it's inside the recommended
 * range.
 */
trait HasLocalizedSeoFields
{
    protected static function seoSanitizeSlug(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Str::limit(Str::slug($value), 60, '');
    }

    /**
     * Live helper text: "N/max caracteres · <estado respecto al rango ideal>".
     */
    protected static function seoCharHelper(mixed $value, int $min, int $max, string $unit = 'caracteres'): string
    {
        $len = mb_strlen((string) $value);

        if ($len === 0) {
            return "0/{$max} {$unit} · recomendado {$min}-{$max}.";
        }

        if ($len > $max) {
            return "⚠ {$len}/{$max} {$unit} · se pasa del máximo recomendado ({$min}-{$max}).";
        }

        if ($len >= $min) {
            return "✔ {$len}/{$max} {$unit} · dentro del rango ideal ({$min}-{$max}).";
        }

        return "{$len}/{$max} {$unit} · corto, lo ideal es {$min}-{$max}.";
    }

    /**
     * Validation rule for the optional JSON-LD textarea: must be parseable
     * JSON when not empty. A broken JSON-LD in <head> is worse than none.
     *
     * IMPORTANT: this must return a closure that, when called with ZERO
     * arguments, returns the real Laravel validation closure — it cannot
     * return the validation closure directly.
     *
     * Every closure passed to Filament's ->rules() is run through
     * `Filament\Support\Concerns\EvaluatesClosures::evaluate()`, which
     * inspects the closure's parameters via reflection and tries to
     * dependency-inject each one (by name, then by type-hint). Laravel's
     * validation closure signature is `(string $attribute, $value, Closure
     * $fail)`: `$attribute` is an untyped, un-injectable string with no
     * default, so Filament's evaluator throws BindingResolutionException
     * before Laravel's validator ever runs — 500 on every save, regardless
     * of what the field contains.
     *
     * The fix is to hand Filament a zero-parameter closure instead. Filament
     * evaluates it with no dependencies to resolve and takes whatever it
     * returns as the "real" rule. `evaluate()` is NOT recursive — it only
     * unwraps one level — so the returned closure is passed through as-is
     * into the rules array. Laravel's Validator then invokes that closure
     * itself via a plain native call (not through Filament's reflection-based
     * evaluator), so `$attribute`/`$value`/`$fail` arrive exactly as Laravel
     * expects. This is the standard idiom for shipping closure-based custom
     * rules through Filament's ->rules()/->rule() helpers.
     */
    protected static function seoJsonLdRule(): Closure
    {
        return fn (): Closure => function (string $attribute, $value, Closure $fail): void {
            if ($value === null || trim((string) $value) === '') {
                return;
            }

            $decoded = json_decode((string) $value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $fail('El JSON-LD no es válido (' . json_last_error_msg() . '). Corrígelo antes de guardar: un JSON roto en el <head> es peor que no tener schema.');

                return;
            }

            // Defensa en profundidad + ayuda de usabilidad. El control real
            // contra XSS vive en el render (components/schema-raw.blade.php,
            // JSON_HEX_TAG): esto NO lo reemplaza, porque los settings/columnas
            // también se escriben por SQL directo (deploy-*.sql), sin pasar
            // por este formulario. Esto solo evita el error honesto más común
            // al copiar de un generador de schema: pegar las etiquetas
            // <script> que lo envuelven (o un comentario HTML) junto con el
            // JSON.
            //
            // Se revisa sobre los valores YA DECODIFICADOS (no el string
            // crudo): un "/" escapado como "\/" dentro del JSON (habitual en
            // json_encode() sin JSON_UNESCAPED_SLASHES) haría invisible un
            // "</script" al buscarlo en el string crudo. Decodificar primero
            // normaliza ambas formas a la misma cadena.
            $hasForbiddenMarker = false;
            if (is_array($decoded)) {
                array_walk_recursive($decoded, function ($leaf) use (&$hasForbiddenMarker): void {
                    if (is_string($leaf) && Str::contains(Str::lower($leaf), ['</script', '<!--'])) {
                        $hasForbiddenMarker = true;
                    }
                });
            } elseif (is_string($decoded) && Str::contains(Str::lower($decoded), ['</script', '<!--'])) {
                $hasForbiddenMarker = true;
            }

            if ($hasForbiddenMarker) {
                $fail('Pega solo el objeto JSON (el que empieza en { y termina en }), sin las etiquetas <script> que lo envuelven ni comentarios HTML. Quita cualquier </script> o <!-- del texto y vuelve a guardar.');
            }
        };
    }

    protected static function seoUrlPreviewHost(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'limaviewtours.com';
    }
}
