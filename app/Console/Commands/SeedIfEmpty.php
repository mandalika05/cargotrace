<?php
/*
 * ============================================================
 * BUAT APA : Mengisi data demo HANYA bila database masih kosong (belum ada user). P15.
 *            Dipakai saat deploy pertama di hosting; deploy berikutnya tidak menggandakan data.
 * JALANKAN : php artisan cargotrace:seed-if-empty
 * ============================================================
 */

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SeedIfEmpty extends Command
{
    protected $signature = 'cargotrace:seed-if-empty';

    protected $description = 'Seed the demo data only when the database has no users yet';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->info('Database already has users - demo seed skipped.');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--force' => true]);
        $this->info('Demo data seeded.');

        return self::SUCCESS;
    }
}
