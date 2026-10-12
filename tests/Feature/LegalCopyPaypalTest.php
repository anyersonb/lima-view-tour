<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The only processor is PayPal (card or PayPal account) and the checkout has
 * exactly two options: pay the total now, or reserve and pay later. The legal
 * and checkout copy must say that, in the three languages.
 */
class LegalCopyPaypalTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function locales(): array
    {
        return ['es' => ['es'], 'en' => ['en'], 'pt' => ['pt']];
    }

    /** @dataProvider locales */
    public function test_no_copy_mentions_culqi(string $locale): void
    {
        $keys = [
            'legal.terms_s3_body', 'legal.privacy_s1_body', 'legal.privacy_s3_body',
            'checkout.hero_subtitle', 'checkout.secure_payment',
        ];

        foreach ($keys as $key) {
            $text = __($key, [], $locale);

            $this->assertNotSame($key, $text, "missing key {$key} ({$locale})");
            $this->assertStringNotContainsStringIgnoringCase('culqi', $text, "{$key} ({$locale})");
        }
    }

    /** @dataProvider locales */
    public function test_terms_describe_the_real_options_and_no_deposit(string $locale): void
    {
        $terms = __('legal.terms_s3_body', [], $locale);

        $this->assertStringContainsString('PayPal', $terms);
        $this->assertDoesNotMatchRegularExpression('/50\s?%/u', $terms, 'the 50 % deposit does not exist at checkout');
        $this->assertDoesNotMatchRegularExpression('/dep[oó]sit/iu', $terms);
        // Both real options are described: pay now / reserve and pay later.
        $this->assertMatchesRegularExpression(
            ['es' => '/pagar ahora.*pagar después/isu', 'en' => '/pay the full amount now.*pay later/isu', 'pt' => '/pagar agora.*pagar depois/isu'][$locale],
            $terms
        );
    }

    /** @dataProvider locales */
    public function test_checkout_copy_names_paypal(string $locale): void
    {
        $this->assertStringContainsString('PayPal', __('checkout.hero_subtitle', [], $locale));
        $this->assertStringContainsString('PayPal', __('checkout.secure_payment', [], $locale));
    }

    public function test_the_checkout_really_offers_those_two_options(): void
    {
        // Guards the copy against the code: if the radios change, the terms must too.
        $view = file_get_contents(resource_path('views/checkout.blade.php'));

        $this->assertStringContainsString('name="payment_timing_ui" value="now"', $view);
        $this->assertStringContainsString('name="payment_timing_ui" value="later"', $view);
        $this->assertStringNotContainsString('deposit', strtolower($view));
    }
}
