<?php

namespace MyApp\repositories\interfaces;

use MyApp\Entities\User;

interface IUserRepository
{
    public function getUserByEmail(string $email);
    public function getUserById(int $id);
    public function saveNewUser(User $user);
    public function updateUser(User $user);
    public function deleteUser(int $id);
}
