<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings;
use App\Filament\Widgets\AnalyticsHealthWidget;
use App\Models\Setting;
use App\Models\User;
use App\Support\AnalyticsHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsHealthTest extends TestCase
{
    use RefreshDatabase;

    private function healthy(): void
    {
        Setting::set('seo_gtm_id', 'GTM-TEST123');
        Setting::set('seo_google_analytics_id', 'G-TEST123456');
    }

    public function test_all_ok_returns_no_problems(): void
    {
        $this->healthy();

        $this->assertSame([], AnalyticsHealth::check());
    }

    public function test_missing_gtm_is_reported(): void
    {
        $this->healthy();
        Setting::set('seo_gtm_id', '  ');

        $problems = AnalyticsHealth::check();

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('Tag Manager', $problems[0]);
    }

    public function test_missing_ga4_is_reported(): void
    {
        $this->healthy();
        Setting::set('seo_google_analytics_id', '');

        $problems = AnalyticsHealth::check();

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('Analytics 4', $problems[0]);
    }

    public function test_tolerates_missing_settings_table(): void
    {
        Cache::forget('settings.all');
        \Illuminate\Support\Facades\Schema::drop('settings');

        $this->assertCount(2, AnalyticsHealth::check());
    }

    private function sentMessages(): int
    {
        return app('mailer')->getSymfonyTransport()->messages()->count();
    }

    public function test_command_exit_zero_when_ok_and_sends_nothing(): void
    {
        $this->healthy();

        $this->artisan('analytics:health')->assertExitCode(0);

        $this->assertSame(0, $this->sentMessages());
    }

    public function test_command_exit_one_mails_booking_recipient_once_per_24h(): void
    {
        Setting::set('booking_notification_email', 'ops@example.com');

        $this->artisan('analytics:health')->assertExitCode(1);
        $this->assertSame(1, $this->sentMessages());
        $message = app('mailer')->getSymfonyTransport()->messages()->first();
        $this->assertStringContainsString('ops@example.com', $message->toString());

        // Second run same day: still fails, but no repeated mail.
        $this->artisan('analytics:health')->assertExitCode(1);
        $this->assertSame(1, $this->sentMessages());

        // After the TTL it is sent again.
        $this->travel(25)->hours();
        $this->artisan('analytics:health')->assertExitCode(1);
        $this->assertSame(2, $this->sentMessages());
    }

    public function test_command_is_scheduled_daily(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command, 'analytics:health'));

        $this->assertCount(1, $events);
        $this->assertSame('0 9 * * *', $events->first()->expression);
    }

    public function test_widget_renders_red_with_problems_and_green_when_ok(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(AnalyticsHealthWidget::class)
            ->assertSee('Tag Manager')
            ->assertSeeHtml('data-analytics-health="problem"')
            ->assertDontSee('Midiendo visitas');

        $this->healthy();

        Livewire::test(AnalyticsHealthWidget::class)
            ->assertSee('Midiendo visitas')
            ->assertSeeHtml('data-analytics-health="ok"');
    }

    public function test_settings_has_no_banner_fields_and_saves_with_legacy_zero(): void
    {
        $this->actingAs(User::factory()->create());
        Setting::set('cookie_banner_enabled', false, 'boolean');

        Livewire::test(Settings::class)
            ->assertOk()
            ->assertFormFieldDoesNotExist('cookie_banner_enabled')
            ->assertFormFieldDoesNotExist('cookie_text_es')
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/es')->assertOk()->assertDontSee('lvt-cookie-banner', false);
    }
}
