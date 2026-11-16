<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserInterface
{
    public function all();

    public function find($id);

    public function create(array $data);

    public function findById($id);

    public function update(array $request, int $id): ?User;

    public function delete($id);

    public function findByEmail($email);

    public function findByPhone($phone);

    public function findByTipeUserId($tipeUserId);

    public function findByName($name);
}
