<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('press_releases', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();

            // "PDF only. Recommended size up to 5 MB, maximum 10 MB."
            // (`366:5165`). Ukuran disimpan dalam byte; pemformatannya urusan
            // tampilan, bukan database.
            $table->string('file_path');
            $table->unsignedBigInteger('file_size');

            // Kategori diambil dari daftar tetap di `config/dwf.php`, bukan
            // tabel sendiri: wireframe tidak punya layar CRUD untuk kategori
            // dokumen, dan membuatkannya berarti mengarang menu yang tidak
            // diminta siapa pun.
            $table->string('category', 64)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'created_at']);
        });

        // Layar Add Gallery (`382:5349`) memilih tipe Event/Tournament lalu
        // "New" atau "Existing" — jadi event adalah entitasnya sendiri, bukan
        // kolom teks yang diketik ulang di tiap aset.
        Schema::create('gallery_events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 16)->default('event'); // event | tournament
            $table->date('held_on')->nullable();
            $table->timestamps();
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_event_id')->constrained()->cascadeOnDelete();

            $table->string('kind', 16)->default('image'); // image | video
            $table->string('path');
            $table->string('slug')->unique();
            $table->string('alt')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('gallery_events');
        Schema::dropIfExists('press_releases');
    }
};
