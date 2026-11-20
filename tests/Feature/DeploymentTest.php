<?php
/*
 * ============================================================
 * BUAT APA : Menguji bagian deployment (P15): perintah seed-if-empty (aman dijalankan tiap deploy),
 *            health check /up, dan keberadaan berkas deployment penting.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_if_empty_fills_an_empty_database_once(): void
    {
        $this->assertSame(0, User::count());

        $this->artisan('cargotrace:seed-if-empty')->assertSuccessful();

        $users = User::count();
        $shipments = Shipment::count();
        $this->assertGreaterThan(0, $users);
        $this->assertGreaterThan(0, $shipments);
        $this->assertDatabaseHas('users', ['email' => 'admin@cargotrace.test', 'role' => 'admin']);

        // Deploy berikutnya: tidak menggandakan data
        $this->artisan('cargotrace:seed-if-empty')->assertSuccessful();
        $this->assertSame($users, User::count());
        $this->assertSame($shipments, Shipment::count());
    }

    public function test_seed_if_empty_never_touches_a_database_that_already_has_users(): void
    {
        User::factory()->admin()->create(['email' => 'real@example.com']);

        $this->artisan('cargotrace:seed-if-empty')->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame(0, Shipment::count());
    }

    public function test_the_health_check_answers_without_login(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_deployment_files_exist(): void
    {
        foreach (['Dockerfile', 'render.yaml', 'docker/entrypoint.sh', '.dockerignore', '.env.production.example', '.github/workflows/tests.yml', 'docs/DEPLOYMENT.md'] as $file) {
            $this->assertFileExists(base_path($file), $file.' is missing');
        }
    }

    public function test_the_production_example_contains_no_real_secrets(): void
    {
        $env = file_get_contents(base_path('.env.production.example'));

        $this->assertMatchesRegularExpression('/^APP_KEY=\s*(#.*)?$/m', $env);
        $this->assertMatchesRegularExpression('/^DB_PASSWORD=\s*(#.*)?$/m', $env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);
    }
}
