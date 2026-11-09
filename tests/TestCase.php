<?php
/*
 * ============================================================
 * BUAT APA : Kelas dasar semua tes.
 * withoutVite() : saat tes, @vite() tidak mencari hasil build CSS/JS,
 *                 jadi tes halaman tetap jalan tanpa npm run build
 *                 (juga di GitHub Actions nanti).
 * ============================================================
 */

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
