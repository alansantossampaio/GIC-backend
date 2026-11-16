<?php

namespace App\Repositories\Core;

use App\Models\User;
use App\Repositories\Contracts\UserInterface;

class UserRepository implements UserInterface
{
    public function create(array $data)
    {
        return User::create($data);
    }

    public function findById($id)
    {
        return User::find($id);
    }

    public function update(array $request, int $id): ?User
    {
        $user = User::find($id);
        if ($user) {
            $user->update($request);
        }

        return $user;
    }

    public function delete($id)
    {
        $user = User::find($id);
        if ($user) {
            $user->delete();
        }

        return $user;
    }

    public function findByEmail($email)
    {
        return User::where('email', $email)->first();
    }

    public function findByPhone($phone)
    {
        return User::where('phone', $phone)->first();
    }

    public function findByTipeUserId($tipeUserId)
    {
        return User::where('tipe_user_id', $tipeUserId)->get();
    }

    public function findByName($name)
    {
        return User::where('name', $name)->get();
    }

    public function find($id)
    {
        return User::find($id);
    }

    public function all()
    {
        return User::all();
    }
}
