<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu dokumen yang menempel di satu rak, pada satu peringkat.
 *
 * Kembarannya `FaqPlacement`, dan kemiripannya disengaja — lihat komentar di
 * migrasi `create_document_placements_table`.
 *
 * TIDAK memakai `RecordsActivity`: satu kali Simpan di layar "Documents per
 * Halaman" menulis ulang seluruh isi satu rak, dan mencatatnya baris per baris
 * akan menenggelamkan jejak audit dengan enam entri untuk satu tindakan.
 * `DocumentController::placements()` mencatatnya sebagai satu entri.
 */
#[Fillable(['document_id', 'section', 'position'])]
class DocumentPlacement extends Model
{
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
