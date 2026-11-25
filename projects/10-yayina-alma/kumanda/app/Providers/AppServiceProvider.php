<?php

namespace App\Providers;

use App\Billing\BillingGateway;
use App\Billing\FakeBillingGateway;
use App\Enums\AgencyRole;
use App\Models\User;
use App\Tenancy\CurrentAgency;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // scoped: her istek ve her kuyruk işi için yeni, boş bir bağlam (bkz. CurrentAgency)
        $this->app->scoped(CurrentAgency::class);

        // Abonelik sağlayıcısı config/billing.php'den seçilir; uygulama yalnızca arayüzü bilir
        // Canlıda sahte sağlayıcı yasak: 4242 yazan herkes ücretli planı bedavaya alırdı (Bölüm 10)
        $this->app->bind(BillingGateway::class, fn () => match (config('billing.provider')) {
            'fake' => FakeBillingGateway::allowed()
                ? new FakeBillingGateway
                : throw new LogicException('Canlıda sahte ödeme sağlayıcısı kullanılamaz (BILLING_PROVIDER=fake). Gerçek sağlayıcıyı yapılandırın.'),
            null, '' => throw new LogicException('Ödeme sağlayıcısı tanımlı değil: .env dosyasına BILLING_PROVIDER yazın.'),
            default => throw new LogicException('Bilinmeyen ödeme sağlayıcısı: '.config('billing.provider')),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Ekibi, ajans ayarlarını yönetmek ve site silmek yalnızca ajans sahibine açık
        Gate::define('manage-agency', function (User $user): bool {
            $agency = app(CurrentAgency::class)->get();

            return $agency !== null && $user->roleIn($agency) === AgencyRole::Owner;
        });

        // Pulse paneli (/pulse) yalnızca platform yöneticilerine (Bölüm 10). Bu tanım olmadan
        // Pulse yalnızca APP_ENV=local'de açılır; canlıda herkese 403 verir.
        Gate::define('viewPulse', fn (User $user): bool => in_array(
            strtolower($user->email),
            config('pulse.admins'),
            true,
        ));

        // "composer run dev" zamanlayıcıyı da başlatsın: sağlık kontrolleri her 15 dakikada bir (Bölüm 6)
        if ($this->app->runningInConsole()) {
            DevCommands::artisan('schedule:work', 'scheduler');
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
