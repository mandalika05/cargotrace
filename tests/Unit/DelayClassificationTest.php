<?php
/*
 * ============================================================
 * BUAT APA : Menguji aturan tingkat delay (slide 8 brief) TANPA database:
 *            0-6 jam LOW, >6-24 jam MEDIUM, >24 jam HIGH, dan batas-batasnya.
 * ============================================================
 */

namespace Tests\Unit;

use App\Services\DelayAssessment;
use App\Services\EtaService;
use PHPUnit\Framework\TestCase;

class DelayClassificationTest extends TestCase
{
    private const T = ['low_max_hours' => 6, 'medium_max_hours' => 24];

    public function test_not_late_means_no_severity(): void
    {
        $eta = new EtaService();

        $this->assertNull($eta->classify(0, self::T));
        $this->assertNull($eta->classify(-90, self::T));
    }

    public function test_low_medium_and_high_with_exact_boundaries(): void
    {
        $eta = new EtaService();

        $this->assertSame('LOW', $eta->classify(1, self::T));
        $this->assertSame('LOW', $eta->classify(6 * 60, self::T));        // tepat 6 jam masih LOW
        $this->assertSame('MEDIUM', $eta->classify(6 * 60 + 1, self::T));  // 6 jam 1 menit = MEDIUM
        $this->assertSame('MEDIUM', $eta->classify(24 * 60, self::T));     // tepat 24 jam masih MEDIUM
        $this->assertSame('HIGH', $eta->classify(24 * 60 + 1, self::T));   // lebih dari 24 jam = HIGH
    }

    public function test_thresholds_can_be_changed(): void
    {
        $eta = new EtaService();
        $custom = ['low_max_hours' => 2, 'medium_max_hours' => 10];

        $this->assertSame('MEDIUM', $eta->classify(3 * 60, $custom));
        $this->assertSame('HIGH', $eta->classify(11 * 60, $custom));
    }

    public function test_durations_are_readable(): void
    {
        $this->assertSame('45m', DelayAssessment::formatMinutes(45));
        $this->assertSame('5h 30m', DelayAssessment::formatMinutes(330));
        $this->assertSame('6h 00m', DelayAssessment::formatMinutes(360));
        $this->assertSame('1d 6h', DelayAssessment::formatMinutes(1800));
    }
}
