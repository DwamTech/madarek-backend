<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::orderBy('name', 'asc')->paginate(10);
        return response()->json($users);
    }

    public function addUser(Request $request ,StoreUserRequest $storeUserRequest)
    {
        $user = User::create($storeUserRequest->validated());
        return response()->json($user);
    }

    public function updateUser(Request $request, User $user)
    {
        $roles = [
            env('ADMIN', 'admin'),
            env('EDITOR', 'editor'),
            env('AUTHOR', 'author'),
            env('REVIEWER', 'reviewer'),
        ];

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'string'],
            'role' => ['sometimes', 'string', Rule::in($roles)],
        ]);

        $user->update($data);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->fresh(),
        ]);
    }

    public function deleteUser(Request $request, User $user)
    {
        $authUser = $request->user();

        if ($authUser && $authUser->id === $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}
