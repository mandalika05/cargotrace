<?php
/*
 * ============================================================
 * BUAT APA : Menguji peta status SECARA MURNI (tanpa database).
 *            Semua pasangan status diuji: hanya yang ada di alur yang boleh.
 * ============================================================
 */

namespace Tests\Unit;

use App\Models\Shipment;
use App\Services\ShipmentStateMachine;
use PHPUnit\Framework\TestCase;

class ShipmentStateMachineTest extends TestCase
{
    public function test_only_the_documented_transitions_are_allowed(): void
    {
        $allowed = [
            'CREATED' => ['BOOKED', 'CANCELLED'],
            'BOOKED' => ['PICKED_UP', 'CANCELLED'],
            'PICKED_UP' => ['IN_TRANSIT'],
            'IN_TRANSIT' => ['DELIVERED'],
            'DELIVERED' => [],
            'CANCELLED' => [],
        ];

        $machine = new ShipmentStateMachine();

        foreach (Shipment::STATUSES as $from) {
            foreach (Shipment::STATUSES as $to) {
                $this->assertSame(
                    in_array($to, $allowed[$from], true),
                    $machine->canTransition($from, $to),
                    "Transition {$from} -> {$to}"
                );
            }
        }
    }

    public function test_terminal_statuses_have_no_way_out(): void
    {
        $machine = new ShipmentStateMachine();

        $this->assertSame([], $machine->allowedNext('DELIVERED'));
        $this->assertSame([], $machine->allowedNext('CANCELLED'));
    }

    public function test_reopen_targets(): void
    {
        $machine = new ShipmentStateMachine();

        $this->assertSame('IN_TRANSIT', $machine->reopenTarget('DELIVERED'));
        $this->assertSame('CREATED', $machine->reopenTarget('CANCELLED'));
        $this->assertNull($machine->reopenTarget('IN_TRANSIT'));
    }
}
