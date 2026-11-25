<?php

use App\Models\Agency;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

/**
 * Ajansın canlı kanalı (toplu işlem ilerlemesi). "private" yalnızca GİRİŞ ister;
 * KİMİN dinleyebileceğini bu fonksiyon belirler: yalnızca o ajansın üyeleri.
 */
Broadcast::channel('agencies.{agency}', function (User $user, Agency $agency) {
    return $user->belongsToAgency($agency);
});
