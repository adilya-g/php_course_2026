<?php

namespace MyApp\repositories\interfaces;

use MyApp\Entities\User;

interface IUserRepository
{
    function getUserByEmail(string $email);
    function getUserById(int $id);
    function saveNewUser(User $user);
    function updateUser(User $user);
    function deleteUser(int $id);
}