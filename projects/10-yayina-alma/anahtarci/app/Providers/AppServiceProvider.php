<?php

namespace App\Providers;

use App\Payments\FakeGateway;
use App\Payments\PaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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
            $provider = config('licensing.payment_provider');

            if ($provider === 'fake' && $app->isProduction()) {
                throw new LogicException('Canlı ortamda sahte ödeme sağlayıcısı kullanılamaz.');
            }

            return match ($provider) {
                'fake' => new FakeGateway,
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
        $this->configureRateLimiting();
    }

    /**
     * Lisans API'sinin istek sınırları.
     * Birden çok Limit verildiğinde HER BİRİ ayrı ayrı uygulanır; ilk aşılan 429 döndürür.
     */
    protected function configureRateLimiting(): void
    {
        $tooMany = fn (Request $request, array $headers) => response()->json([
            'error' => 'too_many_requests',
            'message' => 'Çok fazla deneme yaptınız. Lütfen biraz sonra tekrar deneyin.',
        ], 429, $headers);

        // Aktivasyon: anahtar tahmini ve kaba kuvvet denemelerine karşı sıkı sınır
        RateLimiter::for('license-activate', fn (Request $request) => [
            // Aynı anahtarla dakikada 10 deneme (anahtarın kendisi değil özeti saklanır)
            Limit::perMinute(10)->by('key:'.hash('sha256', (string) $request->input('key')))->response($tooMany),
            // Aynı IP'den, farklı anahtarlarla bile, dakikada 30 deneme
            Limit::perMinute(30)->by('ip:'.$request->ip())->response($tooMany),
        ]);

        // Doğrulama (check) ve kaldırma: her site, kendi token'ıyla dakikada 60 istek
        RateLimiter::for('license-api', fn (Request $request) => Limit::perMinute(60)
            ->by('token:'.($request->user()?->currentAccessToken()?->id ?? $request->ip()))
            ->response($tooMany));
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
