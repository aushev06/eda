<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// KDS station screens: staff-only ticket updates.
Broadcast::channel('pos.tickets', fn (User $user) => $user->can('pos'));
