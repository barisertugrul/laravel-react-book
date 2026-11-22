<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Yalnızca yöneticilerin erişebildiği ekip sayfası (route'ta role:admin). */
class TeamController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('team', [
            'users' => User::withCount(['assignedTickets as open_tickets_count' => fn ($q) => $q->whereNotIn('status', ['delivered', 'cancelled'])])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role']),
            'roles' => array_map(fn (Role $r) => ['value' => $r->value, 'label' => $r->label()], Role::cases()),
        ]);
    }

    /**
     * Yeni ekip üyesi. Kayıt sayfası kapalı olduğu için kullanıcıları yönetici ekler.
     * Şifreyi yönetici belirlemez: kullanıcıya "şifreni belirle" bağlantısı gider.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users')],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $user = User::forceCreate([
            ...$validated,
            // Kimsenin bilmediği rastgele bir şifre; kullanıcı kendi şifresini bağlantıyla belirler
            'password' => Str::password(32),
            // E-postayı yönetici girdiği için doğrulanmış sayıyoruz
            'email_verified_at' => now(),
        ]);

        // Laravel'in "şifremi unuttum" altyapısını davet için kullanıyoruz
        Password::sendResetLink(['email' => $user->email]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} eklendi; şifre belirleme bağlantısı gönderildi."]);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['role' => ['required', Rule::enum(Role::class)]]);

        // Kendi yetkisini düşüren yönetici, paneli yönetecek kimse bırakmayabilir
        if ($user->is($request->user()) && $validated['role'] !== Role::Admin->value) {
            return back()->withErrors(['role' => 'Kendi yönetici yetkinizi kaldıramazsınız.']);
        }

        $user->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name}: {$user->role->label()}"]);

        return back();
    }
}
