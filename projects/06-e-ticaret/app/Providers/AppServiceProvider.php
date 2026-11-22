<?php

namespace App\Providers;

use App\Payments\FakeGateway;
use App\Payments\IyzicoGateway;
use App\Payments\PaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Uygulama PaymentGateway istediğinde config'de seçilen sağlayıcı verilir
        $this->app->bind(PaymentGateway::class, function ($app) {
            $provider = config('shop.payment_provider');

            // Sahte sağlayıcıyla canlı ortamda "ödeme" alınamasın
            if ($provider === 'fake' && $app->isProduction()) {
                throw new LogicException('Canlı ortamda sahte ödeme sağlayıcısı kullanılamaz.');
            }

            return match ($provider) {
                'fake' => new FakeGateway,
                'iyzico' => new IyzicoGateway,
                default => throw new InvalidArgumentException("Bilinmeyen ödeme sağlayıcısı: {$provider}"),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // "composer run dev" sunucu, kuyruk ve Vite'ı başlatır; zamanlayıcıyı da ekliyoruz.
        // Böylece süresi dolan siparişler geliştirme ortamında da her dakika iptal edilir.
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
