<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


Broadcast::channel('systemstats', function ($user) {
    return $user->isAdmin();
});

Broadcast::channel('topstats', function ($user) {
    return $user->isAdmin();
});
