<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Laporan dugaan pelanggaran integritas, dikirim ANONIM dari `/integrity`.
 *
 * Tidak ada kolom identitas, dan itu disengaja — alasannya di migrasinya.
 * Konsekuensi yang mengikat layar CMS-nya: tidak ada yang bisa dibalas, jadi
 * "sudah dibaca" adalah satu-satunya keadaan yang berarti.
 */
#[Fillable(['type', 'description', 'read_at'])]
class IntegrityReport extends Model
{
    use HasFactory, RecordsActivity;

    /**
     * Jenis laporan yang diterima formulir `/integrity`, urut seperti di sana.
     *
     * Kontrak dengan `landing-page-nuxt` (`INTEGRITY_COPY.report.types`):
     * nilai di luar daftar ini ditolak 422. Diganti revisi tim DWF 2026-09-28;
     * laporan lama tetap membawa jenis lamanya, dan layar CMS menawarkannya di
     * filter selama masih ada barisnya.
     */
    public const TYPES = [
        'Cheating or match manipulation',
        'Corruption or betting',
        'Abuse, harassment or discrimination',
        'Anti-doping concern',
        'Other',
    ];

    /** Panjang minimum yang sama dengan yang diperiksa formulirnya sendiri. */
    public const MIN_DESCRIPTION = 20;

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }
}
