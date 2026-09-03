<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\UserRequest;
use App\Mail\AdminInvitationMail;
use App\Models\AdminInvitation;
use App\Models\MemberFederation;
use App\Models\Role;
use App\Models\User;
use App\Support\Access;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin Users — Figma `528:8821` (daftar) dan `529:9210` (Add Admin).
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('Users/Index', [
            'users' => $this->filtered($filters)
                ->paginate(config('dwf.per_page'))
                ->withQueryString()
                ->through(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'roles' => $u->roles->pluck('name')->all(),
                    'federation' => $u->federation?->name,
                    'mfaStatus' => $u->mfa_status,
                    'twoFactorEnabled' => $u->two_factor_enabled,
                    'isActive' => $u->is_active,
                    'isSelf' => $u->is($request->user()),
                    'createdAt' => $u->created_at?->toIso8601String(),
                    'lastLoginAt' => $u->last_login_at?->toIso8601String(),

                    // Keadaan undangan hanya menarik selama akunnya belum
                    // pernah dipakai. Sesudah itu ia sejarah, dan tempatnya
                    // jejak audit — bukan kolom yang dibaca tiap hari.
                    'invitationState' => $u->isPendingInvitation()
                        ? ($u->invitations->first()?->state ?? 'none')
                        : null,
                ]),
            'roles' => $this->roleOptions(),
            'filters' => $filters,
        ]);
    }

    /** @return array{q: string, role: string, status: string, mfa: string} */
    private function filters(Request $request): array
    {
        return [
            'q' => $request->string('q')->toString(),
            'role' => $request->string('role')->toString(),
            'status' => $request->string('status')->toString(),
            'mfa' => $request->string('mfa')->toString(),
        ];
    }

    /**
     * Query daftar — dipakai layar daftar DAN ekspor, supaya keduanya sepakat.
     *
     * @param  array{q: string, role: string, status: string, mfa: string}  $filters
     */
    private function filtered(array $filters): Builder
    {
        return User::query()
            ->with([
                'roles:id,name',
                'federation:id,name',
                // Hanya undangan terbaru per pengguna yang pernah dibaca layar
                // ini. Tanpa batas, akun yang undangannya berkali-kali dikirim
                // ulang menarik seluruh riwayatnya di tiap baris.
                'invitations' => fn ($q) => $q->latest()->limit(1),
            ])
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', "%{$filters['q']}%")
                ->orWhere('email', 'ilike', "%{$filters['q']}%")))
            ->when($filters['role'] !== '', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $filters['role'])))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))

            // "Enrolled" berarti rahasia TOTP ada DAN sudah dibuktikan dengan
            // satu kode yang benar — cerminan `hasConfirmedTwoFactor()`.
            ->when($filters['mfa'] === 'enrolled', fn ($q) => $q->whereNotNull('two_factor_confirmed_at'))
            ->when($filters['mfa'] === 'setup_required', fn ($q) => $q->whereNull('two_factor_confirmed_at'))
            ->orderBy('name');
    }

    /**
     * Ekspor daftar admin, mengikuti filter yang sedang aktif.
     *
     * Yang TIDAK ikut: hash sandi, rahasia TOTP, kode pemulihan, token "ingat
     * saya", dan token undangan. Kelimanya ada di baris yang sama atau satu
     * relasi darinya, dan berkas CSV berpindah lewat surel dan folder bersama —
     * tempat yang tidak punya satu pun perlindungan yang dipunyai tabel aslinya.
     */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($this->filters($request))->lazy();

        return Csv::stream('admin-users', [
            'ID', 'Name', 'Email', 'Roles', 'Federation', 'MFA Status',
            'Two Factor Required', 'Status', 'Created At', 'Last Login',
        ], $rows->map(fn (User $u) => [
            $u->id,
            $u->name,
            $u->email,
            $u->roles->pluck('name')->implode(', '),
            $u->federation?->name,
            $u->mfa_status,
            $u->two_factor_enabled ? 'yes' : 'no',
            $u->is_active ? 'active' : 'inactive',
            $u->created_at?->toDateTimeString(),
            $u->last_login_at?->toDateTimeString(),
        ]));
    }

    public function create(): Response
    {
        return Inertia::render('Users/Form', [
            'user' => null,
            'roles' => $this->roleOptions(),
            'federations' => $this->federationOptions(),
        ]);
    }

    /**
     * Membuat akun admin dan mengirim undangannya.
     *
     * Akun dibuat TANPA sandi dan langsung muncul di daftar dengan Last Login
     * kosong ("First login pending", `528:8909`). Ia belum bisa login: sandi
     * `null` tidak pernah cocok, dan `LoginRequest` menolaknya lebih dulu.
     */
    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        /*
         * Surel dikirim DI LUAR transaksi, sesudah commit.
         *
         * Di dalamnya, kegagalan SMTP akan me-rollback akunnya — dan super
         * admin melihat "gagal" untuk akun yang, di sebagian jalur kegagalan,
         * sudah telanjur ada. Di luar, akun dan undangannya tersimpan utuh dan
         * yang gagal hanya pengirimannya, yang memang bisa diulang lewat
         * tombol Resend.
         */
        [$user, $invitation, $token] = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => null,
                'two_factor_enabled' => $data['two_factor_enabled'],
                'is_active' => $data['is_active'],
                'member_federation_id' => $data['member_federation_id'] ?? null,
            ]);

            $user->syncRoles($data['roles']);

            [$invitation, $token] = AdminInvitation::issue($user, $request->user()->id);

            return [$user, $invitation, $token];
        });

        $this->deliver($invitation, $token, $user);

        return to_route('users.index')->with('success', __('backoffice.invitation.sent', [
            'email' => $user->email,
        ]));
    }

    public function edit(Request $request, User $user): Response
    {
        $user->load(['roles:id,name', 'federation:id,name']);

        return Inertia::render('Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
                'memberFederationId' => $user->member_federation_id,
                'twoFactorEnabled' => $user->two_factor_enabled,
                'twoFactorEnrolled' => $user->hasConfirmedTwoFactor(),
                'isActive' => $user->is_active,
                'isSelf' => $user->is($request->user()),
                'createdAt' => $user->created_at?->toIso8601String(),
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
                'pendingInvitation' => $user->isPendingInvitation(),
            ],
            'roles' => $this->roleOptions(),
            'federations' => $this->federationOptions(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'two_factor_enabled' => $data['two_factor_enabled'],
            'is_active' => $data['is_active'],
            'member_federation_id' => $data['member_federation_id'] ?? null,
        ]);

        // Kata sandi hanya disentuh kalau memang diisi. `filled()`, bukan
        // `isset()`: field yang dikirim kosong dari form adalah string kosong,
        // dan menyimpannya akan mengganti sandi orang dengan hash string kosong.
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        $user->save();
        $user->syncRoles($data['roles']);

        return to_route('users.index')->with('success', __('backoffice.users.updated'));
    }

    /**
     * Sakelar status di daftar — tidak menuntut formulir yang lengkap.
     *
     * Janji ketiga `StandardListTest`.
     */
    public function status(Request $request, User $user): RedirectResponse
    {
        $active = $request->boolean('is_active');

        if ($user->is($request->user()) && ! $active) {
            throw ValidationException::withMessages([
                'is_active' => __('backoffice.users.cannot_deactivate_self'),
            ]);
        }

        // Super admin terakhir tidak boleh dimatikan, alasannya sama persis
        // dengan tidak boleh dihapus: sesudahnya tidak ada seorang pun yang
        // bisa menyalakannya kembali.
        if (! $active && $user->isSuperAdmin() && $this->activeSuperAdminsBesides($user) === 0) {
            throw ValidationException::withMessages([
                'is_active' => __('backoffice.users.last_super_admin'),
            ]);
        }

        $user->update(['is_active' => $active]);

        return back()->with('success', __('backoffice.users.updated'));
    }

    /** Menerbitkan ulang undangan — "can be resent … from Admin Users". */
    public function resendInvitation(Request $request, User $user): RedirectResponse
    {
        if (! $user->isPendingInvitation()) {
            throw ValidationException::withMessages([
                'invitation' => __('backoffice.invitation.already_accepted'),
            ]);
        }

        [$invitation, $token] = AdminInvitation::issue($user, $request->user()->id);

        $this->deliver($invitation, $token, $user);

        return back()->with('success', __('backoffice.invitation.sent', ['email' => $user->email]));
    }

    /**
     * Mencabut undangan yang masih berlaku.
     *
     * Akunnya TIDAK ikut dihapus. Undangan yang dicabut menyisakan akun tanpa
     * sandi yang tidak bisa login dan tidak bisa diterima — keadaan yang benar
     * kalau seseorang salah mengundang orang, dan bisa dibalik dengan Resend
     * tanpa harus membuat akunnya lagi dari nol.
     */
    public function revokeInvitation(Request $request, User $user): RedirectResponse
    {
        $revoked = AdminInvitation::query()
            ->where('user_id', $user->id)
            ->pending()
            ->update(['revoked_at' => now()]);

        if ($revoked === 0) {
            throw ValidationException::withMessages([
                'invitation' => __('backoffice.invitation.nothing_to_revoke'),
            ]);
        }

        activity('admin-invitation')
            ->performedOn($user)
            ->event('invitation_revoked')
            ->log('invitation_revoked');

        return back()->with('success', __('backoffice.invitation.revoked'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => __('backoffice.users.cannot_delete_self'),
            ]);
        }

        if ($user->isSuperAdmin()
            && Role::findByName(Access::SUPER_ADMIN, 'web')->users()->count() <= 1) {
            throw ValidationException::withMessages([
                'user' => __('backoffice.users.last_super_admin'),
            ]);
        }

        $user->delete();

        return back()->with('success', __('backoffice.users.deleted'));
    }

    /**
     * Mengirim surel undangan, dan mencatatnya di jejak audit.
     *
     * Kegagalan pengiriman TIDAK dilempar ke atas: akun dan undangannya sudah
     * tersimpan, dan 500 di layar akan membuat orang membuat akunnya lagi.
     * Yang muncul adalah pesan bahwa tautannya perlu dikirim ulang — tindakan
     * yang tombolnya sudah ada.
     */
    private function deliver(AdminInvitation $invitation, string $token, User $user): void
    {
        activity('admin-invitation')
            ->performedOn($user)
            ->event('invitation_sent')
            ->log('invitation_sent');

        try {
            Mail::to($user->email)->send(new AdminInvitationMail($invitation, $token));
        } catch (\Throwable $e) {
            report($e);

            session()->flash('warning', __('backoffice.invitation.delivery_failed'));
        }
    }

    private function activeSuperAdminsBesides(User $user): int
    {
        return Role::findByName(Access::SUPER_ADMIN, 'web')
            ->users()
            ->where('users.id', '!=', $user->id)
            ->where('users.is_active', true)
            ->count();
    }

    /**
     * Peran beserta lingkupnya — form memakai `scope` untuk memutuskan apakah
     * field "Federation Scope" digambar.
     *
     * @return array<int, array{value: string, label: string, scope: string}>
     */
    private function roleOptions(): array
    {
        return Role::query()->orderBy('name')->get(['name', 'scope'])
            ->map(fn (Role $role) => [
                'value' => $role->name,
                'label' => $role->name,
                'scope' => $role->scope ?? 'global',
            ])
            ->all();
    }

    /** @return array<int, array{value: int, label: string}> */
    private function federationOptions(): array
    {
        return MemberFederation::query()->active()->orderBy('name')->get()
            ->map(fn (MemberFederation $f) => ['value' => $f->id, 'label' => $f->label])
            ->all();
    }
}
