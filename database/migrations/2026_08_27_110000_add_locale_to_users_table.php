<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Disimpan di user, bukan di sesi: bahasa adalah preferensi orang,
            // bukan properti tab browser. Admin yang memilih Indonesia di
            // laptop tidak seharusnya disambut Inggris di komputer kantor.
            $table->string('locale', 5)->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
