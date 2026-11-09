<?php
/*
 * ============================================================
 * BUAT APA : Menambah kolom 'role' ke tabel users bawaan Laravel.
 *            Nilai: admin | operator | customer. Bawaan: customer,
 *            jadi pendaftaran publik selalu menghasilkan customer.
 * INDEX    : role diberi index karena sering dipakai untuk filter.
 * ============================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->after('password')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
