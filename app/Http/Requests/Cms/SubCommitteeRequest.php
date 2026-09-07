<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubCommitteeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $id = $this->route('committee')?->id;

        return [
            'name' => [
                'required', 'string', 'max:160',
                // Dua sub-komite bernama sama adalah dua kartu identik di situs
                // publik, dan tidak ada yang bisa membedakannya dari sana.
                Rule::unique('sub_committees', 'name')->ignore($id),
            ],
            // Boleh kosong: halaman tujuannya belum tentu ada, dan panah yang
            // berujung 404 lebih buruk daripada kartu tanpa panah.
            'href' => ['nullable', 'string', 'max:200'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('backoffice.people.sub_name'),
            'href' => __('backoffice.people.sub_href'),
            'is_active' => __('backoffice.common.status'),
        ];
    }
}
