<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Mencatat tiap perubahan model ke log aktivitas.
 *
 * Yang dicatat: siapa, kapan, model apa, dan atribut mana yang berubah dari
 * nilai apa ke nilai apa.
 *
 * Yang TIDAK pernah dicatat: `password`, rahasia TOTP, dan kode pemulihan —
 * lihat `$hidden` di tiap model. Log audit yang menyimpan hash kata sandi lama
 * mengubah dirinya sendiri jadi sasaran: ia menumpuk kredensial dalam bentuk
 * yang justru lebih mudah dibaca daripada tabel aslinya.
 *
 * `logOnlyDirty` supaya menyimpan tanpa mengubah apa pun tidak menghasilkan
 * baris kosong, dan `dontSubmitEmptyLogs` supaya perubahan yang seluruhnya
 * terdiri dari atribut tersembunyi tidak meninggalkan entri hampa.
 */
trait RecordsActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logExcept($this->hidden)
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName(static::activityLogName());
    }

    /** Nama modul yang muncul di penyaring log. */
    public static function activityLogName(): string
    {
        return str(class_basename(static::class))->kebab()->toString();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return $eventName;
    }
}
