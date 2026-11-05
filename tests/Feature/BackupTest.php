<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $this->dir = storage_path('framework/testing/backups');
        File::deleteDirectory($this->dir);
        config(['cleartrack.backup_path' => $this->dir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_admin_backs_up_lists_and_downloads(): void
    {
        Process::fake(['*' => Process::result(output: "-- MySQL dump\nCREATE TABLE users ...;\n")]);
        File::ensureDirectoryExists($this->dir);
        File::put("{$this->dir}/cleartrack-2026-11-05.sql.gz", gzencode('nightly'));

        $this->actingAs(User::role('admin')->first())->post(route('admin.backups.store'))
            ->assertSessionHas('status', fn ($s) => str_starts_with($s, 'Backup saved: manual-'));

        Process::assertRan(fn ($process) => in_array('--no-tablespaces', $process->command, true));
        $manual = basename(File::glob("{$this->dir}/manual-*.sql.gz")[0]);
        $this->assertSame("-- MySQL dump\nCREATE TABLE users ...;\n", gzdecode(File::get("{$this->dir}/{$manual}")));

        $this->get(route('admin.backups'))->assertOk()->assertSee($manual)->assertSee('cleartrack-2026-11-05.sql.gz')->assertSee('Nightly');
        $this->get(route('admin.backups.download', $manual))->assertOk()->assertDownload($manual);
        $this->get(route('admin.backups.download', '..%2F.env'))->assertNotFound();
        $this->get(route('admin.backups.download', 'missing.sql.gz'))->assertNotFound();
    }

    public function test_a_failed_backup_says_why_and_saves_nothing(): void
    {
        Process::fake(['*' => Process::result(output: '', errorOutput: "mysqldump: Got error: 1045: Access denied\n", exitCode: 2)]);

        $this->actingAs(User::role('admin')->first())->post(route('admin.backups.store'))
            ->assertSessionHas('error', 'The backup failed: mysqldump: Got error: 1045: Access denied');
        $this->assertSame([], File::glob("{$this->dir}/*.sql.gz"));
    }

    public function test_only_admins_can_use_backups(): void
    {
        Process::fake();
        $officer = User::role('officer')->first();

        $this->actingAs($officer)->get(route('admin.backups'))->assertForbidden();
        $this->actingAs($officer)->post(route('admin.backups.store'))->assertForbidden();
        Process::assertNothingRan();
    }
}
