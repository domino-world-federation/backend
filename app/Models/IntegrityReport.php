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

    /** Jenis insiden yang digambar `601:17709`, urut seperti di sana. */
    public const TYPES = [
        'Match manipulation',
        'Doping',
        'Betting or insider information',
        'Harassment or abuse',
        'Conflict of interest',
        'Something else',
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
