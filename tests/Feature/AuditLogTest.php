<?php
/*
 * ============================================================
 * BUAT APA : Menguji AUDIT LOG (P13.3): perubahan penting tercatat otomatis lewat observer,
 *            password tidak pernah dicatat, log bersifat tambah-saja, halaman dan API
 *            hanya untuk admin, serta filter bekerja.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Carrier;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_update_is_audited_with_the_actor(): void
    {
        $admin = User::factory()->admin()->create();
        // Nilai awal DIPASTIKAN berbeda dari nilai baru. Tanpa ini, factory yang memilih jenis kargo
        // acak kadang sudah 'Electronics', sehingga tidak ada perubahan dan tidak ada audit (tes sesekali gagal).
        $shipment = Shipment::factory()->create(['cargo_type' => 'Textiles']);

        $this->actingAs($admin);
        $shipment->forceFill(['cargo_type' => 'Electronics'])->save();

        $log = AuditLog::where('action', 'shipment.updated')->where('entity_id', $shipment->id)->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Electronics', $log->new_values['cargo_type']);
        $this->assertSame($shipment->shipment_number, $log->entity_label);
    }

    public function test_a_shipment_creation_is_recorded(): void
    {
        $shipment = Shipment::factory()->create();

        $this->assertDatabaseHas('audit_logs', ['action' => 'shipment.created', 'entity_id' => $shipment->id]);
    }

    public function test_a_status_change_stores_old_and_new_values(): void
    {
        $shipment = Shipment::factory()->create(['status' => 'BOOKED']);

        $shipment->forceFill(['status' => 'PICKED_UP'])->save();

        $log = AuditLog::where('action', 'shipment.updated')->where('entity_id', $shipment->id)->latest('id')->first();
        $this->assertSame(['status' => 'BOOKED'], $log->old_values);
        $this->assertSame(['status' => 'PICKED_UP'], $log->new_values);
    }

    public function test_saving_without_tracked_changes_writes_nothing(): void
    {
        $shipment = Shipment::factory()->create();
        $before = AuditLog::count();

        $shipment->touch();

        $this->assertSame($before, AuditLog::count());
    }

    public function test_deleting_and_restoring_a_shipment_are_audited(): void
    {
        $shipment = Shipment::factory()->create();

        $shipment->delete();
        $this->assertDatabaseHas('audit_logs', ['action' => 'shipment.deleted', 'entity_id' => $shipment->id]);

        $shipment->restore();
        $this->assertDatabaseHas('audit_logs', ['action' => 'shipment.restored', 'entity_id' => $shipment->id]);
    }

    public function test_passwords_are_never_written_to_the_audit_log(): void
    {
        $user = User::factory()->create(['password' => 'Secret-Pass-123']);
        $user->forceFill(['password' => 'Another-Pass-456', 'name' => 'New Name'])->save();

        $raw = AuditLog::all()->map(fn ($l) => json_encode([$l->old_values, $l->new_values]))->implode(' ');

        $this->assertStringNotContainsString('Secret-Pass-123', $raw);
        $this->assertStringNotContainsString('Another-Pass-456', $raw);
        $this->assertStringNotContainsString('password', $raw);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated', 'entity_id' => $user->id]);
    }

    public function test_master_data_changes_are_audited_through_the_web(): void
    {
        $admin = User::factory()->admin()->create();
        $carrier = Carrier::factory()->create();

        $this->actingAs($admin)->put(route('carriers.update', $carrier), [
            'name' => 'Renamed Carrier', 'code' => $carrier->code, 'contact_email' => null, 'is_active' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'carrier.updated', 'entity_id' => $carrier->id, 'user_id' => $admin->id]);
    }

    public function test_reopening_a_shipment_is_audited_with_its_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $shipment = Shipment::factory()->delivered()->create();

        $this->actingAs($admin)->post(route('shipments.reopen', $shipment), ['reason' => 'Recorded by mistake.']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'shipment.updated', 'entity_id' => $shipment->id, 'user_id' => $admin->id]);
        $event = AuditLog::where('action', 'tracking_event.created')->latest('id')->first();
        $this->assertStringContainsString('REOPENED', $event->entity_label);
        $this->assertStringContainsString('Recorded by mistake.', $event->new_values['description']);
    }

    public function test_audit_logs_are_append_only(): void
    {
        $log = AuditLog::first() ?? AuditLog::create(['action' => 'shipment.created', 'entity_type' => 'shipment', 'entity_id' => 1]);

        $this->expectException(LogicException::class);
        $log->update(['entity_label' => 'tampered']);
    }

    public function test_audit_logs_cannot_be_deleted(): void
    {
        $log = AuditLog::create(['action' => 'shipment.created', 'entity_type' => 'shipment', 'entity_id' => 1]);

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_the_audit_can_be_switched_off_in_config(): void
    {
        config(['cargotrace.audit.enabled' => false]);

        Shipment::factory()->create();

        $this->assertSame(0, AuditLog::where('entity_type', 'shipment')->count());
    }

    // ---------- Halaman web ----------

    public function test_only_admin_can_open_the_audit_page(): void
    {
        $this->get(route('audit-logs.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->operator()->create())->get(route('audit-logs.index'))->assertForbidden();
        $this->actingAs(User::factory()->customer()->create())->get(route('audit-logs.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('audit-logs.index'))->assertOk()->assertSee('Audit log');
    }

    public function test_the_page_lists_entries_and_filters_them(): void
    {
        $admin = User::factory()->admin()->create();
        $shipment = Shipment::factory()->create(['shipment_number' => 'CT-AUDIT-0001']);
        Carrier::factory()->create(['name' => 'Zeta Lines']);

        $this->actingAs($admin)->get(route('audit-logs.index', ['entity' => 'shipment']))
            ->assertOk()->assertSee('CT-AUDIT-0001')->assertDontSee('Zeta Lines');

        $this->actingAs($admin)->get(route('audit-logs.index', ['q' => 'Zeta']))
            ->assertOk()->assertSee('Zeta Lines')->assertDontSee('CT-AUDIT-0001');

        $this->actingAs($admin)->get(route('audit-logs.index', ['action_type' => 'deleted']))
            ->assertOk()->assertSee('No audit entries found');
    }

    public function test_bad_filters_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('audit-logs.index', ['entity' => 'hacker']))->assertSessionHasErrors('entity');
        $this->actingAs($admin)->get(route('audit-logs.index', ['date_from' => '2026-05-10', 'date_to' => '2026-05-01']))->assertSessionHasErrors('date_to');
    }

    // ---------- API ----------

    public function test_the_audit_api_is_admin_only(): void
    {
        $this->getJson('/api/v1/audit-logs')->assertUnauthorized();

        $this->actingAs(User::factory()->operator()->create(), 'sanctum')->getJson('/api/v1/audit-logs')->assertForbidden();

        Shipment::factory()->create();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/v1/audit-logs?entity=shipment')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'action', 'entity_type', 'entity_id', 'entity_label', 'old_values', 'new_values', 'ip_address', 'actor', 'created_at']], 'meta']);
    }

    public function test_the_audit_api_validates_filters(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/v1/audit-logs?action_type=explode')->assertStatus(422)->assertJsonValidationErrors('action_type');
    }
}
