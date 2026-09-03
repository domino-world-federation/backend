<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empat kolom yang dituntut layar Roles & Permissions (`528:9745`).
 *
 * Tabelnya milik `spatie/laravel-permission`; menambah kolom ke sana aman —
 * paket itu membaca `name` dan `guard_name` dan tidak peduli tetangganya.
 *
 * - `type` — "System" atau "Custom". System = peran yang lahir dari
 *   `App\Support\Access::roles()`, jadi ia bukan pilihan pengguna melainkan
 *   fakta tentang asal-usulnya. Catatan desain (`528:9744`) menjadikannya
 *   aturan: "System roles can be inspected but not deleted."
 * - `scope` — "Global" atau "Federation". Peran berlingkup federasi menuntut
 *   field "Federation Scope" terisi saat admin dibuat (`529:9696`).
 * - `summary` — kolom "Permission Summary", kalimat pendek yang ditulis orang.
 *   BUKAN daftar izin yang dirangkai otomatis: desain menulis "Players, KYC,
 *   federation content" untuk peran yang punya belasan izin, jadi ini ringkasan
 *   niat, bukan cerminan data.
 * - `updated_by_id` — kolom "Last Updated" menyebut nama, dan `updated_at`
 *   sendirian tidak bisa menjawabnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('type', 16)->default('custom')->after('guard_name');
            $table->string('scope', 16)->default('global')->after('type');
            $table->string('summary')->nullable()->after('scope');
            $table->foreignId('updated_by_id')->nullable()->after('summary')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by_id');
            $table->dropColumn(['type', 'scope', 'summary']);
        });
    }
};
