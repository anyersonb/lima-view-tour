<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Maintenance;
use App\Models\User;
use App\Support\MaintenanceOutput;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

/** Maintenance is limited to MAINTENANCE_ADMIN_EMAILS and never leaks DB details. */
class MaintenanceAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::factory()->create(['email' => $email]);
    }

    public function test_user_outside_the_list_gets_403_and_no_navigation_item(): void
    {
        config(['admin.maintenance_emails' => ['boss@webtilia.com']]);
        $this->actingAs($this->user('editor@webtilia.com'));

        $this->get('/admin/maintenance')->assertForbidden();
        $this->assertFalse(Maintenance::canAccess());
        $this->assertFalse(Maintenance::shouldRegisterNavigation());
    }

    public function test_user_in_the_list_can_open_it_case_insensitively(): void
    {
        config(['admin.maintenance_emails' => ['boss@webtilia.com']]);
        $this->actingAs($this->user('BOSS@webtilia.com'));

        $this->get('/admin/maintenance')->assertOk();
        $this->assertTrue(Maintenance::shouldRegisterNavigation());
    }

    public function test_guest_has_no_access(): void
    {
        $this->assertFalse(Maintenance::canAccess());
    }

    public function test_actions_are_blocked_for_a_user_outside_the_list(): void
    {
        config(['admin.maintenance_emails' => ['boss@webtilia.com']]);
        $this->actingAs($this->user('editor@webtilia.com'));

        Livewire::test(Maintenance::class)->assertForbidden();
    }

    /** @dataProvider envValues */
    public function test_config_parses_the_env_list_and_never_ends_up_empty(?string $env, array $expected): void
    {
        putenv($env === null ? 'MAINTENANCE_ADMIN_EMAILS' : 'MAINTENANCE_ADMIN_EMAILS='.$env);
        $_ENV['MAINTENANCE_ADMIN_EMAILS'] = $env;
        $_SERVER['MAINTENANCE_ADMIN_EMAILS'] = $env;
        if ($env === null) {
            unset($_ENV['MAINTENANCE_ADMIN_EMAILS'], $_SERVER['MAINTENANCE_ADMIN_EMAILS']);
        }

        try {
            $config = require base_path('config/admin.php');
        } finally {
            putenv('MAINTENANCE_ADMIN_EMAILS');
            unset($_ENV['MAINTENANCE_ADMIN_EMAILS'], $_SERVER['MAINTENANCE_ADMIN_EMAILS']);
        }

        $this->assertSame($expected, $config['maintenance_emails']);
    }

    /** @return array<string, array{0: ?string, 1: array<int, string>}> */
    public static function envValues(): array
    {
        return [
            'not defined => default (nobody locked out)' => [null, ['ablanco@webtilia.com']],
            'empty => default' => ['', ['ablanco@webtilia.com']],
            'only commas/spaces => default' => [' , ,', ['ablanco@webtilia.com']],
            'list, trimmed and lowercased' => [' A@x.com, B@Y.com ,a@x.com', ['a@x.com', 'b@y.com']],
        ];
    }

    public function test_failure_notification_hides_db_details_and_log_keeps_them(): void
    {
        config(['admin.maintenance_emails' => ['qa@webtilia.com']]);
        $this->actingAs($this->user('qa@webtilia.com'));
        Log::spy();

        $raw = "SQLSTATE[HY000] [1045] Access denied for user 'lima_prod'@'10.0.0.5' (using password: YES) (Connection: mysql, SQL: select 1)\nsecond line";
        Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true])->andReturn(1);
        Artisan::shouldReceive('output')->andReturn($raw);

        $component = Livewire::test(Maintenance::class)->callAction('run_migrations');

        $body = collect(session('filament.notifications', []))->first()['body'] ?? null;
        $this->assertNotNull($body);
        foreach (['lima_prod', '10.0.0.5', 'password: YES', 'select 1', 'second line'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
        $this->assertStringContainsString('storage/logs', $body);

        Log::shouldHaveReceived('error')
            ->withArgs(fn ($m, $ctx) => str_contains($ctx['output'], "'lima_prod'@'10.0.0.5'") && str_contains($ctx['output'], 'second line'))
            ->once();
        $this->assertNotNull($component);
    }

    /** @dataProvider rawOutputs */
    public function test_summary_masks_credentials_and_truncates(string $raw, string $mustNotContain): void
    {
        $summary = MaintenanceOutput::summary($raw);

        $this->assertNotSame('', $summary);
        $this->assertStringNotContainsString($mustNotContain, $summary);
        $this->assertLessThanOrEqual(MaintenanceOutput::MAX_LENGTH, mb_strlen($summary));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function rawOutputs(): array
    {
        return [
            'mysql access denied' => ["Access denied for user 'root'@'localhost'", 'root'],
            'dsn with password' => ['Could not connect to mysql://dbuser:S3cr3tPass@db.internal:3306/lima', 'S3cr3tPass'],
            'key=value password' => ['SQLSTATE[HY000]: host=db.internal;dbname=lima;password=hunter2', 'hunter2'],
            'ip address' => ['Connection refused to 192.168.10.20', '192.168.10.20'],
            'unknown database' => ["Unknown database 'lima_secret_db'", 'lima_secret_db'],
            'very long line' => [str_repeat('a', 500), str_repeat('a', 200)],
            'leading blank lines' => ["\n\n  Access denied for user 'u'@'h'\nnext", 'next'],
        ];
    }

    public function test_summary_of_empty_output_is_empty(): void
    {
        $this->assertSame('', MaintenanceOutput::summary("  \n "));
    }
}
