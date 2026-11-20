<?php
/*
 * ============================================================
 * BUAT APA : Menguji poles tampilan P14.5: bar atas (judul, profil), sapaan dashboard sesuai jam
 *            (disiapkan untuk JavaScript) dan kotak akun demo di halaman login.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_top_bar_shows_the_page_title_and_the_profile(): void
    {
        $user = User::factory()->operator()->create(['name' => 'Rani Operator']);

        $page = $this->actingAs($user)->get(route('shipments.index'))->assertOk();

        $page->assertSee('class="topbar-title"', false)
            ->assertSee('topbar-profile', false)
            ->assertSee('Rani Operator')
            ->assertSee('Search shipment number...', false);
    }

    public function test_the_page_title_is_still_in_the_page_for_screen_readers(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('exceptions.index'))
            ->assertOk()->assertSee('<h1 class="page-title">Exceptions</h1>', false);
    }

    public function test_the_dashboard_has_a_greeting_slot_that_javascript_fills(): void
    {
        $this->actingAs(User::factory()->admin()->create(['name' => 'Dina Admin']))->get(route('dashboard'))
            ->assertOk()->assertSee('data-greeting', false)->assertSee('Dina Admin');
    }

    public function test_the_greeting_script_is_part_of_the_app_bundle_source(): void
    {
        $js = file_get_contents(resource_path('js/greeting.js'));

        foreach (['Good morning', 'Good afternoon', 'Good evening', 'Good night'] as $text) {
            $this->assertStringContainsString($text, $js);
        }
        $this->assertStringContainsString("import './greeting.js'", file_get_contents(resource_path('js/app.js')));
    }

    public function test_demo_accounts_box_follows_the_setting(): void
    {
        config(['cargotrace.demo.show_accounts' => true]);
        $this->get('/login')->assertOk()->assertSee('Demo accounts')->assertSee('admin@cargotrace.test');

        config(['cargotrace.demo.show_accounts' => false]);
        $this->get('/login')->assertOk()->assertDontSee('Demo accounts');
    }

    public function test_demo_accounts_box_is_automatic_when_the_setting_is_empty(): void
    {
        config(['cargotrace.demo.show_accounts' => null]);
        $this->get('/login')->assertSee('Demo accounts'); // lingkungan test bukan production
    }

    public function test_the_login_heading_has_a_slot_for_first_time_or_returning_visitors(): void
    {
        $this->get('/login')->assertOk()->assertSee('data-welcome', false)->assertSee('Welcome to CargoTrace');

        $js = file_get_contents(resource_path('js/greeting.js'));
        $this->assertStringContainsString('Welcome back', $js);
        $this->assertStringContainsString('cargotrace_seen', $js);
        $this->assertStringContainsString('localStorage', $js);
    }
}
