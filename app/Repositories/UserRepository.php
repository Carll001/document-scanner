<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository
{
    public function paginateClients(?string $search, ?string $role = null, int $perPage = 10, ?int $excludeUserId = null): LengthAwarePaginator
    {
        return User::query()
            ->whereIn('role', ['client', 'registrar'])

            // ✅ hide the currently logged-in account
            ->when($excludeUserId, fn ($q) => $q->where('id', '!=', $excludeUserId))

            // ✅ hide seeded super admin account by email
            ->where('email', '!=', 'superadmin@gmail.com')

            ->when($role && in_array($role, ['client', 'registrar'], true), function ($q) use ($role) {
                $q->where('role', $role);
            })

            ->when($search && trim($search) !== '', function ($q) use ($search) {
                $search = mb_strtolower(trim($search));
                $q->where(function ($qq) use ($search) {
                    $qq->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                       ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user->refresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
