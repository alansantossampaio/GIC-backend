<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Core\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeMail;

class UserService
{
    protected $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Summary of createUser a new user
     *
     * @param array $data
     * @return User|\Illuminate\Database\Eloquent\Model
     */
    public function createUser(array $data)
    {
        $user = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'],
            'tipe_user_id' => $data['tipe_user_id'],
        ]);

        Mail::to($user->email)->send(new WelcomeMail($user));

        return $user;
    }

    /**
     * Summary of updateUser an existing user by ID
     *
     * @param int $id
     * @param array $data
     * @return User|\Illuminate\Database\Eloquent\Model
     */
    public function updateUser(int $id, array $data)
    {
        return $this->userRepository->update($data, $id);
    }

    /**
     * Summary of deleteUser a user by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteUser(int $id)
    {
        return $this->userRepository->delete($id);
    }

    /**
     * Summary of getUser a single user by ID
     *
     * @param int $id
     * @return User|\Illuminate\Database\Eloquent\Model
     */
    public function getUser(int $id)
    {
        return $this->userRepository->find($id);
    }

    /**
     * Summary of getAllUsers all users
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllUsers()
    {
        return $this->userRepository->all();
    }

    /**
     * Summary of getUserByEmail a single user by email
     *
     * @param string $email
     * @return User|\Illuminate\Database\Eloquent\Model
     */
    public function getUserByEmail(string $email)
    {
        return $this->userRepository->findByEmail($email);
    }

    /**
     * Summary of getUserByPhone a single user by phone
     *
     * @param string $phone
     * @return User|\Illuminate\Database\Eloquent\Model
     */
    public function getUserByPhone(string $phone)
    {
        return $this->userRepository->findByPhone($phone);
    }
}
