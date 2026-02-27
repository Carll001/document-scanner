<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Services\UserService;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClientUserController extends Controller
{
    public function __construct(
        private UserRepository $repo,
        private UserService $service
    ) {}

    public function index(Request $request)
    {
        $search  = $request->string('search')->toString();
        $role    = $request->string('role')->toString();
        $role    = in_array($role, ['client', 'registrar'], true) ? $role : null;
        $perPage = (int) $request->input('per_page', 10);

        $users = User::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role, fn($q) => $q->where('role', $role))
            ->where('id', '!=', Auth::id())   // ✅ hide current user
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn($user) => (new UserResource($user))->resolve());

        return Inertia::render('ClientUsers/Index', [
            'filters' => [
                'search' => $search,
                'role' => $role,
                'per_page' => $perPage,
            ],
            'users' => $users,
        ]);
    }

    public function show(User $user)
    {


        // ✅ allow only client/registrar
        abort_unless(in_array($user->role, ['client', 'registrar']), 404);

        // ✅ SAFETY: prevent viewing own account via URL
        abort_if(Auth::id() === $user->id, 403);

        $files = $user->files()->latest()->paginate(10);

        return Inertia::render('ClientUsers/Show', [
            'user' => new UserResource($user),
            'generatedFiles' => $files,
        ]);
    }

    public function store(UpsertUserRequest $request)
    {
        $data = $request->validated();

        // ✅ keep role from request; default to client if missing
        $data['role'] = $data['role'] ?? 'client';

        $this->service->store($data);

        return back()->with('success', 'User created.');
    }

    public function update(UpsertUserRequest $request, User $user)
    {
        abort_unless(in_array($user->role, ['client', 'registrar']), 404);

        // ✅ SAFETY: prevent updating own account via URL
        abort_if(Auth::id() === $user->id, 403);

        $data = $request->validated();

        // ✅ keep role from request; fallback to current role if missing
        $data['role'] = $data['role'] ?? $user->role;

        $this->service->update($user, $data);

        return back()->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        abort_unless(in_array($user->role, ['client', 'registrar']), 404);

        // ✅ SAFETY: prevent deleting own account via URL
        abort_if(Auth::id() === $user->id, 403);

        $this->repo->delete($user);

        return back()->with('success', 'User deleted.');
    }
}
