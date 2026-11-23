<?php

namespace App\Tenancy;

use App\Models\Agency;

/**
 * İsteğin (ya da kuyruk işinin) hangi ajans adına çalıştığını tutar.
 *
 * Kapsayıcıya "scoped" olarak kaydedilir (AppServiceProvider): her HTTP isteğinde ve
 * her kuyruk işinde sıfırdan başlar. "singleton" olsaydı, uzun ömürlü kuyruk işçisi
 * bir önceki işin ajansını bir sonrakine taşıyabilirdi.
 */
final class CurrentAgency
{
    private ?Agency $agency = null;

    public function set(?Agency $agency): void
    {
        $this->agency = $agency;
    }

    public function get(): ?Agency
    {
        return $this->agency;
    }

    public function id(): ?int
    {
        return $this->agency?->id;
    }

    /**
     * Bir işi belirli bir ajansın bağlamında çalıştırır; bitince önceki bağlam geri gelir.
     * Kuyruk işleri ve komutlar (oturum yok, middleware yok) bunu kullanır.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(Agency $agency, callable $callback): mixed
    {
        $previous = $this->agency;
        $this->agency = $agency;

        try {
            return $callback();
        } finally {
            $this->agency = $previous;
        }
    }
}
