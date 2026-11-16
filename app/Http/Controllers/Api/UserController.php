<?php

namespace App\Http\Controllers;
use App\Services\UserService;

use Illuminate\Http\Request;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index()
    {
        // Return a list of users
        return response()->json($this->userService->getAllUsers());
    }

    public function show($id)
    {
        // Return a single user by ID
        return response()->json($this->userService->getUser($id));
    }

    public function store(Request $request)
    {
        // Create a new user
        return response()->json($this->userService->createUser($request->all()));
    }

    public function update(Request $request, $id)
    {
        // Update an existing user by ID
        return response()->json($this->userService->updateUser($id, $request->all()));
    }

    public function destroy($id)
    {
        // Delete a user by ID
        return response()->json($this->userService->deleteUser($id));
    }
    public function getUserByEmail($email)
    {
        return response()->json($this->userService->getUserByEmail($email));
    }

    public function getUserByPhone($phone)
    {
        return response()->json($this->userService->getUserByPhone($phone));
    }
}
