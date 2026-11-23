<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\AgencyRole;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Kayıt açık: ajanslar ürünün müşterisidir. Kayıt olan kişi kendi ajansının sahibi olur.
 * (Ekibe katılacak kişiler davet bağlantısıyla gelir; bkz. InvitationController.)
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'agency' => ['required', 'string', 'max:80'],
            'password' => $this->passwordRules(),
        ])->validate();

        // Kullanıcı, ajans ve üyelik birlikte oluşur ya da hiçbiri oluşmaz
        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $agency = Agency::create(['name' => $input['agency']]);
            $agency->addMember($user, AgencyRole::Owner);
            $user->switchAgency($agency);

            return $user;
        });
    }
}
