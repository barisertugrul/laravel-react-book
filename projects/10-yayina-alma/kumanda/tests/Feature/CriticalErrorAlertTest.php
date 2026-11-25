<?php

namespace Tests\Feature;

use App\Notifications\CriticalErrorOccurred;
use App\Support\CriticalErrorAlerter;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/**
 * Canlıda bir hata olduğunda yöneticiye e-posta gider; aynı hata e-posta seline dönüşmez.
 */
class CriticalErrorAlertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['monitoring.alert_email' => 'yonetici@kumanda.test']);
        Notification::fake();
    }

    public function test_nothing_is_sent_when_no_address_is_configured(): void
    {
        config(['monitoring.alert_email' => null]);

        report(new RuntimeException('Bağlantı koptu'));

        Notification::assertNothingSent();
    }

    public function test_the_same_error_sends_only_one_email_within_the_window(): void
    {
        for ($i = 0; $i < 3; $i++) {
            report($this->sameError());
        }

        Notification::assertSentOnDemandTimes(CriticalErrorOccurred::class, 1);
        Notification::assertSentOnDemand(
            CriticalErrorOccurred::class,
            fn (CriticalErrorOccurred $n, array $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'yonetici@kumanda.test'
                && $n->class === RuntimeException::class
                && $n->message === 'Bağlantı koptu',
        );
    }

    public function test_the_same_error_alerts_again_after_the_window(): void
    {
        report($this->sameError());
        $this->travel(11)->minutes();
        report($this->sameError());

        Notification::assertSentOnDemandTimes(CriticalErrorOccurred::class, 2);
    }

    public function test_a_different_error_sends_its_own_email(): void
    {
        report(new RuntimeException('Bir'));
        report(new LogicException('İki'));

        Notification::assertSentOnDemandTimes(CriticalErrorOccurred::class, 2);
    }

    public function test_hourly_cap_limits_a_flood_of_different_errors(): void
    {
        config(['monitoring.alert_hourly_cap' => 2]);

        report(new RuntimeException('Bir'));
        report(new LogicException('İki'));
        report(new \DomainException('Üç'));

        Notification::assertSentOnDemandTimes(CriticalErrorOccurred::class, 2);
    }

    public function test_not_found_and_validation_errors_do_not_alert(): void
    {
        $this->get('/yok-boyle-bir-sayfa')->assertNotFound();

        Notification::assertNothingSent();
    }

    public function test_an_unhandled_exception_in_a_request_alerts(): void
    {
        Route::get('/_patla', fn () => throw new RuntimeException('İstekte hata'));

        $this->get('/_patla')->assertServerError();

        Notification::assertSentOnDemand(
            CriticalErrorOccurred::class,
            fn (CriticalErrorOccurred $n) => $n->message === 'İstekte hata' && str_ends_with($n->context, '/_patla'),
        );
    }

    public function test_a_failing_mailer_does_not_throw(): void
    {
        Notification::swap(new class
        {
            public function route(): static
            {
                return $this;
            }

            public function notifyNow(): never
            {
                throw new RuntimeException('SMTP kapalı');
            }
        });

        app(CriticalErrorAlerter::class)->handle(new RuntimeException('Asıl hata'));

        $this->assertTrue(true); // istisna dışarı sızsaydı buraya gelinmezdi
    }

    public function test_the_email_contains_no_stack_trace(): void
    {
        $mail = CriticalErrorOccurred::fromThrowable($this->sameError())
            ->toMail(new AnonymousNotifiable)
            ->render();

        $this->assertStringContainsString('Bağlantı koptu', (string) $mail);
        $this->assertStringNotContainsString('#0 ', (string) $mail);
    }

    private function sameError(): RuntimeException
    {
        // Tek satırda üretilir: dosya + satır aynı → parmak izi aynı
        return new RuntimeException('Bağlantı koptu');
    }
}
