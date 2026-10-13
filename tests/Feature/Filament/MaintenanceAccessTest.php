<?php

namespace Tests\Feature\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Pages\Maintenance;
use App\Models\User;
use App\Support\MaintenanceAccess;
use App\Support\MaintenanceOutput;
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

    public function test_action_is_rejected_when_access_is_revoked_after_the_page_was_mounted(): void
    {
        config(['admin.maintenance_emails' => ['boss@webtilia.com']]);
        $this->actingAs($this->user('boss@webtilia.com'));

        // Page mounted and confirmation modal open while still allowed...
        $component = Livewire::test(Maintenance::class)->mountAction('run_migrations');

        // ...then the user leaves the list while the tab stays open.
        config(['admin.maintenance_emails' => ['someone-else@webtilia.com']]);
        Artisan::shouldReceive('call')->never();

        $component->assertActionHidden('run_migrations');
        $component->call('callMountedAction');

        $this->assertEmpty(session('filament.notifications', []));
    }

    public function test_action_runs_for_an_allowed_user_with_the_page_mounted(): void
    {
        config(['admin.maintenance_emails' => ['boss@webtilia.com']]);
        $this->actingAs($this->user('boss@webtilia.com'));

        Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true])->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('Nothing to migrate.');

        Livewire::test(Maintenance::class)->callAction('run_migrations');

        $this->assertNotEmpty(session('filament.notifications', []));
    }

    public function test_user_ids_anchor_access_when_configured(): void
    {
        $boss = $this->user('boss@webtilia.com');
        $other = $this->user('other@webtilia.com');
        config(['admin.maintenance_emails' => ['other@webtilia.com'], 'admin.maintenance_user_ids' => [(string) $boss->id]]);

        $this->assertTrue(MaintenanceAccess::allows($boss));
        $this->assertFalse(MaintenanceAccess::allows($other), 'ID list wins over the e-mail list');

        config(['admin.maintenance_user_ids' => []]);
        $this->assertTrue(MaintenanceAccess::allows($other));
        $this->assertFalse(MaintenanceAccess::allows($boss));
    }

    public function test_config_parses_admin_ids_ignoring_garbage(): void
    {
        putenv('MAINTENANCE_ADMIN_IDS= 3, x,7 ,,3');
        try {
            $config = require base_path('config/admin.php');
        } finally {
            putenv('MAINTENANCE_ADMIN_IDS');
        }

        $this->assertSame(['3', '7'], $config['maintenance_user_ids']);
    }

    public function test_profile_page_cannot_change_the_email(): void
    {
        $user = $this->user('editor@webtilia.com');
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->assertFormFieldIsDisabled('email')
            ->fillForm(['name' => 'New Name', 'email' => 'ablanco@webtilia.com'])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('editor@webtilia.com', $user->email);
    }

    /** @dataProvider leakyOutputs */
    public function test_mask_closes_residual_leaks(string $raw, string $mustNotContain): void
    {
        $masked = MaintenanceOutput::summary($raw);

        $this->assertNotSame('', $masked);
        $this->assertStringNotContainsString($mustNotContain, $masked);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function leakyOutputs(): array
    {
        return [
            'DB_PASSWORD env line' => ['DB_PASSWORD=secret123', 'secret123'],
            'DB_USERNAME env line' => ['DB_USERNAME=lima_prod_user', 'lima_prod_user'],
            'quoted env value' => ['DB_PASSWORD="my secret pass" next', 'secret'],
            'any env key' => ['MAIL_FROM_ADDRESS=hola_x9 ', 'hola_x9'],
            'APP_KEY' => ['APP_KEY=base64:AbCdEf123', 'AbCdEf123'],
            'sql after count(*)' => ['SQLSTATE[42S02]: Base table or view not found (Connection: mysql, SQL: select count(*) as aggregate from `users` where `id` = 1)', 'aggregate'],
            'sql after count(*) table' => ['Error (Connection: mysql, SQL: select count(*) as aggregate from `secret_table`)', 'secret_table'],
            'bare select' => ['Failed: select * from `bookings` where email = x', 'bookings'],
            'home path' => ['include(/home/cpuser123/public_html/vendor/x.php): failed', 'cpuser123'],
            'windows path' => ['failed in C:\\Users\\Anyerson\\www\\x.php', 'Anyerson'],
            'double quoted user@host' => ['Access denied for user "dbuser9"@"10.1.1.1"', 'dbuser9'],
        ];
    }

    public function test_mask_covers_sql_on_an_intermediate_line_of_multiline_output(): void
    {
        $raw = "SQLSTATE[42S02]: Base table not found (Connection: mysql, SQL: select count(*) as aggregate from secret_table)\n"
            ."Other line SQL: delete from users where id = 9\n"
            .'last line';

        $masked = MaintenanceOutput::mask($raw);

        $this->assertStringNotContainsString('secret_table', $masked);
        $this->assertStringNotContainsString('users', $masked);
        $this->assertStringContainsString('last line', $masked);
        $this->assertCount(3, explode("\n", $masked), 'lines are kept');
    }

    public function test_success_notification_is_persistent_so_the_result_can_be_read(): void
    {
        config(['admin.maintenance_emails' => ['boss@webtilia.com']]);
        $this->actingAs($this->user('boss@webtilia.com'));

        Artisan::shouldReceive('call')->once()->with('images:variants', ['--max-seconds' => 60])->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('Imágenes: 3. Variantes creadas: 5. Pendientes: 2.');

        Livewire::test(Maintenance::class)->callAction('optimized_images');

        $n = collect(session('filament.notifications', []))->first();
        $this->assertNotNull($n);
        $this->assertStringContainsString('Pendientes: 2', $n['body']);
        $this->assertSame('persistent', $n['duration']);
    }

    public function test_summary_of_empty_output_is_empty(): void
    {
        $this->assertSame('', MaintenanceOutput::summary("  \n "));
    }
}
