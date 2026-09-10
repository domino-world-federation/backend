<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nama, surel, dan avatar milik sendiri.
 *
 * Sandi TIDAK di sini — ia punya endpoint sendiri karena menuntut sandi lama
 * ikut dibuktikan, dan menggabungkannya berarti "sandi lama salah" ikut
 * membatalkan penggantian nama yang benar di formulir yang sama.
 *
 * Peran, status aktif, sakelar 2FA, dan federasi juga tidak di sini, dan itu
 * bukan kelalaian — lihat komentar kelas di `ProfileController`.
 */
class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Yang disunting selalu akun yang sedang login; tidak ada parameter
        // route yang bisa menunjuk akun orang lain.
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $uploads = config('dwf.uploads');
        $spec = $uploads['image_specs']['avatar'];

        return [
            'name' => ['required', 'string', 'max:120'],

            // Surel adalah identitas login, jadi keunikannya bukan kerapian.
            // `ignore` dirinya sendiri: menyimpan tanpa mengubah surel akan
            // ditolak oleh aturannya sendiri tanpa itu.
            'email' => [
                'required', 'email', 'max:160',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],

            'avatar' => [
                'nullable', 'image',
                'mimes:'.implode(',', $uploads['image_mimes']),
                'max:'.$uploads['image_max_kb'],
                'dimensions:min_width='.$spec['min_width']
                    .',min_height='.$spec['min_height']
                    .',ratio='.$spec['ratio'],
            ],

            'remove_avatar' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $spec = config('dwf.uploads.image_specs.avatar');

        return [
            // Kalimat `dimensions` bawaan Laravel cuma bilang "dimensinya
            // salah". Yang perlu diketahui orangnya justru angkanya.
            'avatar.dimensions' => __('backoffice.profile.avatar_dimensions', [
                'width' => $spec['min_width'],
                'height' => $spec['min_height'],
            ]),
        ];
    }
}
