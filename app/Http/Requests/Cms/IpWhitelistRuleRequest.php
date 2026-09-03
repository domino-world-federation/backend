<?php

namespace App\Http\Requests\Cms;

use App\Models\IpWhitelistRule;
use App\Support\Security\IpWhitelist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Aturan validasi untuk satu baris daftar IP.
 *
 * Empat butir "Validation & Security" di desain (`527:8163`) diterjemahkan di
 * sini; yang kelima ("Created By … recorded automatically") ditangani trait
 * `TracksEditor` dan controller.
 */
class IpWhitelistRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],

            // Format diperiksa di `withValidator`: aturan bawaan `ip` menolak
            // notasi CIDR, dan desain justru meminta keduanya.
            'ip_range' => ['required', 'string', 'max:64'],

            'scope' => ['required', Rule::in(IpWhitelistRule::SCOPES)],

            // `required_if` bukan `nullable`: lingkup peran tanpa peran adalah
            // aturan yang tidak menyasar siapa pun — ia lolos simpan lalu diam
            // saja selamanya.
            'role_id' => [
                Rule::requiredIf(fn () => $this->input('scope') === IpWhitelistRule::SCOPE_ROLE),
                'nullable',
                Rule::exists('roles', 'id'),
            ],
            'user_id' => [
                Rule::requiredIf(fn () => $this->input('scope') === IpWhitelistRule::SCOPE_USER),
                'nullable',
                Rule::exists('users', 'id'),
            ],

            'validity' => ['required', Rule::in(IpWhitelistRule::VALIDITIES)],

            // "Temporary rules require a future expiration date." Tanggal yang
            // sudah lewat menghasilkan aturan yang lahir mati — tersimpan,
            // terlihat aktif di kolom Status, dan tidak pernah berlaku.
            'expires_at' => [
                Rule::requiredIf(fn () => $this->input('validity') === IpWhitelistRule::VALIDITY_TEMPORARY),
                'nullable',
                'date',
                'after:now',
            ],

            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $pattern = trim((string) $this->input('ip_range'));

            if ($pattern === '') {
                return;
            }

            if (! $this->isValidPattern($pattern)) {
                $validator->errors()->add('ip_range', __('backoffice.ip_whitelist.invalid_ip'));

                return;
            }

            $this->rejectOverlap($validator, $pattern);
        });
    }

    /**
     * IPv4, IPv6, atau CIDR salah satunya.
     *
     * Ditulis tangan karena `filter_var` tidak mengenal prefix dan aturan `ip`
     * Laravel memakainya. Panjang prefix diperiksa terhadap KELUARGA alamatnya:
     * `/64` sah untuk IPv6 dan omong kosong untuk IPv4, dan tanpa pemeriksaan
     * ini yang kedua tersimpan diam-diam lalu tidak pernah cocok dengan apa pun.
     */
    private function isValidPattern(string $pattern): bool
    {
        $parts = explode('/', $pattern, 2);
        $address = trim($parts[0]);

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (count($parts) === 1) {
            return true;
        }

        $prefix = trim($parts[1]);

        if ($prefix === '' || ! ctype_digit($prefix)) {
            return false;
        }

        $max = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? 32 : 128;

        return (int) $prefix >= 0 && (int) $prefix <= $max;
    }

    /**
     * "Duplicate or overlapping rules within the same access scope are blocked."
     *
     * DALAM LINGKUP YANG SAMA — dua aturan yang beririsan tapi menyasar orang
     * berbeda bukan duplikat, melainkan dua izin yang kebetulan berbagi kantor.
     * Menolaknya akan melarang hal yang justru wajar: satu blok kantor untuk
     * semua admin, plus satu alamat di dalamnya untuk seorang kontraktor dengan
     * masa berlaku sendiri.
     */
    private function rejectOverlap(Validator $validator, string $pattern): void
    {
        $scope = (string) $this->input('scope');
        $current = $this->route('ip_whitelist');

        $clash = IpWhitelistRule::query()
            ->where('scope', $scope)
            ->when($scope === IpWhitelistRule::SCOPE_ROLE, fn ($q) => $q->where('role_id', $this->input('role_id')))
            ->when($scope === IpWhitelistRule::SCOPE_USER, fn ($q) => $q->where('user_id', $this->input('user_id')))
            ->when($current !== null, fn ($q) => $q->whereKeyNot($current->id))
            ->get()
            ->first(fn (IpWhitelistRule $rule) => IpWhitelist::overlaps($rule->ip_range, $pattern));

        if ($clash !== null) {
            $validator->errors()->add('ip_range', __('backoffice.ip_whitelist.overlaps', [
                'name' => $clash->name,
                'range' => $clash->ip_range,
            ]));
        }
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('backoffice.ip_whitelist.name'),
            'ip_range' => __('backoffice.ip_whitelist.ip'),
            'scope' => __('backoffice.ip_whitelist.scope'),
            'role_id' => __('backoffice.ip_whitelist.allowed_role'),
            'user_id' => __('backoffice.ip_whitelist.allowed_admin'),
            'validity' => __('backoffice.ip_whitelist.validity'),
            'expires_at' => __('backoffice.ip_whitelist.expires_at'),
            'notes' => __('backoffice.ip_whitelist.notes'),
        ];
    }
}
