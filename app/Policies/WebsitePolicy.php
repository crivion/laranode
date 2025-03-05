<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website;
use Illuminate\Auth\Access\Response;

class WebsitePolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function delete(User $user, Website $website): Response
    {
        return ($user->isAdmin() || $user->id === $website->user_id) ? Response::allow() : Response::deny('You are not authorized to delete this website.');
    }
}
