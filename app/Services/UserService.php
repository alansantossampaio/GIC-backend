<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeMail;

class UserService
{
    protected $userRepository;

    public function __construct(UserInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Cria um usuário a partir dos dados fornecidos
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
     * Atualiza um usuário existente pelo ID
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
     * Remove um usuário pelo ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteUser(int $id)
    {
        return $this->userRepository->delete($id);
    }

    /**
     * Retorna um usuário específico pelo ID
     *
     * @param int $id
     * @return User|\Illuminate\Database\Eloquent\Model
     */
    public function getUser(int $id)
    {
        return $this->userRepository->find($id);
    }

    /**
     * Retorna todos os usuários
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllUsers()
    {
        return $this->userRepository->all();
    }

    /**
     * Retorna um usuário específico pelo email
     *
     * @param string $email
     * @return User|\Illuminate\Database\Eloquent\Model
     */
    public function getUserByEmail(string $email)
    {
        return $this->userRepository->findByEmail($email);
    }

    /**
     * Retorna um usuário específico pelo telefone
     *
     * @param string $phone
     * @return User|\Illuminate\Database\Eloquent\Model
     */
    public function getUserByPhone(string $phone)
    {
        return $this->userRepository->findByPhone($phone);
    }
}
