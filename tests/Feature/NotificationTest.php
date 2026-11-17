<?php
/*
 * ============================================================
 * BUAT APA : Menguji NOTIFIKASI in-app (P13.3): siapa menerima apa, pelaku tidak dinotifikasi,
 *            tandai dibaca, hanya milik sendiri (404 untuk orang lain), API, saluran email
 *            hanya bila diaktifkan, dan lonceng tampil.
 * ============================================================
 */

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use App\Notifications\ShipmentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private Shipment $shipment;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = User::factory()->operator()->create();
        $this->shipment = Shipment::factory()->create(['status' => 'BOOKED']);
        $this->customer = $this->shipment->customer;
        $this->customer->notifications()->delete(); // bersihkan notifikasi dari pembuatan data awal
    }

    private function event(string $type, array $extra = [])
    {
        return $this->actingAs($this->operator)->post(
            route('shipments.tracking.store', $this->shipment),
            array_merge(['event_type' => $type, 'occurred_at' => now()->subHour()->format('Y-m-d\TH:i')], $extra)
        );
    }

    public function test_the_customer_is_notified_when_the_status_changes(): void
    {
        $this->event('PICKED_UP')->assertSessionHasNoErrors();

        $n = $this->customer->notifications()->first();
        $this->assertNotNull($n);
        $this->assertSame('status_changed', $n->data['kind']);
        $this->assertStringContainsString($this->shipment->shipment_number, $n->data['title']);
        $this->assertSame('/shipments/'.$this->shipment->id, $n->data['url']);
    }

    public function test_the_person_who_did_it_is_not_notified(): void
    {
        $this->event('PICKED_UP');

        $this->assertSame(0, $this->operator->notifications()->count());
    }

    public function test_a_failed_event_creates_no_notification(): void
    {
        $this->event('DELIVERED'); // BOOKED -> DELIVERED tidak diizinkan

        $this->assertSame(0, $this->customer->notifications()->count());
    }

    public function test_other_customers_never_receive_it(): void
    {
        $other = User::factory()->customer()->create();

        $this->event('PICKED_UP');

        $this->assertSame(0, $other->notifications()->count());
    }

    public function test_reporting_an_exception_notifies_other_staff_and_the_customer(): void
    {
        $this->shipment->forceFill(['status' => 'IN_TRANSIT'])->save();
        $admin = User::factory()->admin()->create();

        $this->actingAs($this->operator)->post(route('shipments.exceptions.store', $this->shipment), [
            'type' => 'PORT_CONGESTION', 'severity' => 'HIGH', 'description' => 'Queue.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $admin->notifications()->where('data->kind', 'exception_reported')->count());
        $this->assertSame(1, $this->customer->notifications()->where('data->kind', 'exception_reported')->count());
        $this->assertSame(0, $this->operator->notifications()->where('data->kind', 'exception_reported')->count());
    }

    public function test_resolving_an_exception_notifies_the_customer(): void
    {
        $this->shipment->forceFill(['status' => 'IN_TRANSIT'])->save();
        $exception = ShipmentException::factory()->create(['shipment_id' => $this->shipment->id, 'status' => 'OPEN']);
        $this->customer->notifications()->delete();

        $this->actingAs($this->operator)->patch(route('exceptions.update', $exception), ['status' => 'RESOLVED', 'note' => 'Cleared.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->customer->notifications()->where('data->kind', 'exception_resolved')->count());
    }

    public function test_changing_the_eta_notifies_the_customer(): void
    {
        $this->shipment->forceFill(['status' => 'IN_TRANSIT'])->save();
        $this->customer->notifications()->delete();

        $this->actingAs($this->operator)->patch(route('shipments.eta.update', $this->shipment), [
            'estimated_arrival_at' => $this->shipment->expected_arrival_at->copy()->addDays(2)->format('Y-m-d\TH:i'),
            'reason' => 'Weather',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->customer->notifications()->where('data->kind', 'eta_updated')->count());
    }

    public function test_plain_location_updates_do_not_notify(): void
    {
        $this->shipment->forceFill(['status' => 'IN_TRANSIT'])->save();

        $this->event('LOCATION_UPDATE', ['location_name' => 'Mid sea']);

        $this->assertSame(0, $this->customer->notifications()->count());
    }

    // ---------- Halaman web ----------

    public function test_the_list_shows_only_own_notifications_and_filters_unread(): void
    {
        $this->event('PICKED_UP');
        $this->customer->notifications()->first()->markAsRead();
        $this->event('IN_TRANSIT');

        $all = $this->actingAs($this->customer)->get(route('notifications.index'))->assertOk();
        $this->assertCount(2, $all->viewData('notifications'));

        $unread = $this->actingAs($this->customer)->get(route('notifications.index', ['filter' => 'unread']))->assertOk();
        $this->assertCount(1, $unread->viewData('notifications'));

        $this->actingAs(User::factory()->customer()->create())->get(route('notifications.index'))
            ->assertOk()->assertSee('No notifications yet');
    }

    public function test_reading_marks_it_read_and_redirects_to_the_shipment(): void
    {
        $this->event('PICKED_UP');
        $n = $this->customer->notifications()->first();

        $this->actingAs($this->customer)->post(route('notifications.read', $n->id))
            ->assertRedirect('/shipments/'.$this->shipment->id);

        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_a_foreign_notification_id_gives_404(): void
    {
        $this->event('PICKED_UP');
        $n = $this->customer->notifications()->first();

        $this->actingAs(User::factory()->customer()->create())->post(route('notifications.read', $n->id))->assertNotFound();
        $this->assertNull($n->fresh()->read_at);
    }

    public function test_an_external_url_in_a_notification_is_never_followed(): void
    {
        $this->customer->notify(new ShipmentNotification('status_changed', 'info', 'T', 'M', $this->shipment->id, $this->shipment->shipment_number));
        $n = $this->customer->notifications()->first();
        $n->forceFill(['data' => array_merge($n->data, ['url' => '//evil.example/phish'])])->save();

        $this->actingAs($this->customer)->post(route('notifications.read', $n->id))->assertRedirect(route('notifications.index'));
    }

    public function test_mark_all_read(): void
    {
        $this->event('PICKED_UP');
        $this->event('IN_TRANSIT');
        $this->assertSame(2, $this->customer->unreadNotifications()->count());

        $this->actingAs($this->customer)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $this->customer->unreadNotifications()->count());
    }

    public function test_guests_cannot_use_notifications(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
    }

    public function test_the_bell_shows_the_unread_count(): void
    {
        $this->event('PICKED_UP');
        $this->event('IN_TRANSIT');

        $this->actingAs($this->customer)->get(route('dashboard'))
            ->assertOk()->assertSee('bell-badge', false)->assertSee('View all notifications');
    }

    // ---------- API ----------

    public function test_the_api_lists_reads_and_reports_unread_count(): void
    {
        $this->event('PICKED_UP');
        $n = $this->customer->notifications()->first();

        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonStructure(['data' => [['id', 'kind', 'level', 'title', 'message', 'url', 'shipment_number', 'is_read', 'read_at', 'created_at']]]);

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/notifications/'.$n->id.'/read')
            ->assertOk()->assertJsonPath('data.is_read', true);

        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/notifications?filter=unread')->assertOk()->assertJsonPath('unread_count', 0);
    }

    public function test_the_api_hides_other_peoples_notifications_and_supports_read_all(): void
    {
        $this->event('PICKED_UP');
        $n = $this->customer->notifications()->first();

        $this->actingAs(User::factory()->customer()->create(), 'sanctum')->postJson('/api/v1/notifications/'.$n->id.'/read')->assertNotFound();

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);
        $this->getJson('/api/v1/notifications')->assertOk(); // masih terautentikasi di tes yang sama
    }

    // ---------- Saluran email ----------

    public function test_email_is_only_used_when_the_flag_is_on(): void
    {
        $n = new ShipmentNotification('status_changed', 'info', 'T', 'M', 1, 'CT-1');

        config(['cargotrace.notifications.mail' => false]);
        $this->assertSame(['database'], $n->via($this->customer));

        config(['cargotrace.notifications.mail' => true]);
        $this->assertSame(['database', 'mail'], $n->via($this->customer));
    }

    public function test_the_notification_is_sent_through_the_notification_system(): void
    {
        Notification::fake();

        $this->event('PICKED_UP');

        Notification::assertSentTo($this->customer, ShipmentNotification::class);
    }
}
