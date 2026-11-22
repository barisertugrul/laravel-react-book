<?php

namespace App\Policies;

use App\Models\Card;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Kartlarla ilgili yetki kuralları.
 * Laravel, Card modeli için bu sınıfı adından otomatik olarak bulur.
 */
class CardPolicy
{
    public function create(User $user): Response
    {
        return $user->hasReachedCardLimit()
            ? Response::deny(sprintf(
                '%s planda en fazla %d kart oluşturabilirsiniz. Daha fazlası için planınızı yükseltin.',
                $user->plan->label(),
                $user->plan->cardLimit(),
            ))
            : Response::allow();
    }

    public function update(User $user, Card $card): bool
    {
        return $user->id === $card->user_id;
    }

    public function delete(User $user, Card $card): bool
    {
        return $user->id === $card->user_id;
    }
}
