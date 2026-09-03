<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu orang yang minta dikabari saat pendaftaran sebuah turnamen dibuka.
 *
 * TIDAK memakai `RecordsActivity`: barisnya dibuat pengunjung situs publik,
 * bukan admin, jadi jejak auditnya akan penuh entri tanpa pelaku — dan yang
 * hendak diaudit dari modul ini bukan "siapa mendaftar" melainkan "siapa
 * mengunduh daftarnya", yang dicatat controllernya sendiri.
 */
#[Fillable(['tournament_id', 'email'])]
class TournamentNotification extends Model
{
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
