<?php

namespace Tests\Unit\Filament;

use App\Filament\Resources\TourResource;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Exercises the shared HasLocalizedSeoFields::seoJsonLdRule() validation
 * closure used by TourResource, PageResource and BlogPostResource. A broken
 * JSON-LD in <head> is worse than none, so this must genuinely fail on
 * invalid JSON — not just report OK.
 */
class SeoJsonLdRuleTest extends TestCase
{
    /**
     * seoJsonLdRule() returns a zero-arg closure (see the docblock on the
     * method for why): Filament evaluates that outer closure and takes
     * whatever it returns — the actual Laravel validation closure — as the
     * rule. Unwrap it here the same way Filament's evaluator does.
     */
    private function rule(): \Closure
    {
        $method = new ReflectionMethod(TourResource::class, 'seoJsonLdRule');
        $method->setAccessible(true);

        $outer = $method->invoke(null);

        return $outer();
    }

    /** @test */
    public function it_fails_on_broken_json(): void
    {
        $rule = $this->rule();
        $failed = false;

        $rule('schema_jsonld_es', '{not valid json', function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Expected the rule to call $fail() on broken JSON.');
    }

    /** @test */
    public function it_passes_on_valid_json(): void
    {
        $rule = $this->rule();
        $failed = false;

        $rule('schema_jsonld_es', json_encode(['@context' => 'https://schema.org']), function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Valid JSON must not fail validation.');
    }

    /** @test */
    public function it_passes_on_empty_value_because_the_field_is_optional(): void
    {
        $rule = $this->rule();
        $failed = false;

        $rule('schema_jsonld_es', null, function () use (&$failed) {
            $failed = true;
        });
        $rule('schema_jsonld_es', '', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'An empty JSON-LD field is optional and must not fail.');
    }
}
