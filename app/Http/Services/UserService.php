<?php

namespace App\Http\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(private UserRepository $repo) {}

    public function store(array $payload): User
    {
        $data = [
            'name' => $payload['name'],
            'email' => $payload['email'],
            'role' => $payload['role'] ?? 'client',
            'password' => Hash::make($payload['password']),
        ];

        return $this->repo->create($data);
    }

    public function update(User $user, array $payload): User
    {
        $data = [
            'name' => $payload['name'],
            'email' => $payload['email'],
            'role' => $payload['role'] ?? $user->role,
        ];

        if (!empty($payload['password'])) {
            $data['password'] = Hash::make($payload['password']);
        }

        return $this->repo->update($user, $data);
    }
}