<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Database;
use Illuminate\Auth\Access\Response;

class DatabasePolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function view(User $user, Database $database): Response
    {
        return ($user->isAdmin() || $user->id === $database->user_id) 
            ? Response::allow() 
            : Response::deny('You are not authorized to view this database.');
    }

    public function update(User $user, Database $database): Response
    {
        return ($user->isAdmin() || $user->id === $database->user_id) 
            ? Response::allow() 
            : Response::deny('You are not authorized to update this database.');
    }

    public function delete(User $user, Database $database): Response
    {
        return ($user->isAdmin() || $user->id === $database->user_id) 
            ? Response::allow() 
            : Response::deny('You are not authorized to delete this database.');
    }
}
