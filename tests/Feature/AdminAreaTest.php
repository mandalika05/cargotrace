<?php
/*
 * ============================================================
 * BUAT APA : Menguji area Admin: carrier, lokasi, user.
 * ISI TES  : non-admin ditolak (403), admin bisa mengelola, kode carrier unik,
 *            carrier yang dipakai tidak bisa dihapus, koordinat harus berpasangan,
 *            password user di-hash, admin tidak bisa mengubah perannya sendiri.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_are_forbidden_from_admin_pages(): void
    {
        foreach (['operator', 'customer'] as $role) {
            $user = User::factory()->{$role}()->create();
            foreach (['/carriers', '/locations', '/users'] as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }
    }

    public function test_admin_can_open_admin_pages(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['/carriers', '/locations', '/users', '/carriers/create', '/locations/create', '/users/create'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_can_create_a_carrier_and_code_is_uppercased(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('carriers.store'), ['name' => 'Test Lines', 'code' => 'tst', 'contact_email' => '', 'is_active' => '1'])
            ->assertRedirect(route('carriers.index'));

        $this->assertDatabaseHas('carriers', ['name' => 'Test Lines', 'code' => 'TST', 'is_active' => 1]);
    }

    public function test_carrier_code_must_be_unique(): void
    {
        Carrier::factory()->create(['code' => 'DUP']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('carriers.store'), ['name' => 'Other', 'code' => 'dup', 'is_active' => '1'])
            ->assertSessionHasErrors('code');
    }

    public function test_carrier_used_by_shipments_cannot_be_deleted(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('carriers.destroy', $shipment->carrier))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('carriers', ['id' => $shipment->carrier_id]);
    }

    public function test_unused_carrier_can_be_deleted(): void
    {
        $carrier = Carrier::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->delete(route('carriers.destroy', $carrier));

        $this->assertDatabaseMissing('carriers', ['id' => $carrier->id]);
    }

    public function test_location_coordinates_must_come_as_a_pair(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('locations.store'), ['name' => 'Test Port', 'city' => 'Testville', 'country' => 'Testland', 'latitude' => '10.5'])
            ->assertSessionHasErrors('longitude');
    }

    public function test_location_used_by_shipments_cannot_be_deleted(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('locations.destroy', Location::find($shipment->origin_id)))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('locations', ['id' => $shipment->origin_id]);
    }

    public function test_admin_can_create_a_user_with_a_role_and_password_is_hashed(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('users.store'), [
                'name' => 'New Operator', 'email' => 'newop@example.test', 'role' => 'operator',
                'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
            ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'newop@example.test')->firstOrFail();
        $this->assertSame('operator', $user->role);
        $this->assertNotSame('secret-pass-1', $user->password);
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));
    }

    public function test_admin_cannot_change_their_own_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('users.edit', $admin))
            ->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role' => 'customer'])
            ->assertSessionHasErrors('role');

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_admin_can_change_another_users_role(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->customer()->create();

        $this->actingAs($admin)
            ->put(route('users.update', $other), ['name' => $other->name, 'email' => $other->email, 'role' => 'operator'])
            ->assertRedirect(route('users.index'));

        $this->assertSame('operator', $other->fresh()->role);
    }
}
