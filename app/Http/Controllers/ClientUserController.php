<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Services\UserService;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientUserController extends Controller
{
    public function __construct(
        private UserRepository $repo,
        private UserService $service
    ) {}

    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $perPage = (int) $request->input('per_page', 10);

        $users = $this->repo->paginateClients($search, $perPage);

        return Inertia::render('ClientUsers/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'users' => [
                'data' => UserResource::collection($users->items()),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
        ]);
    }

    public function show(User $user)
    {
        // ✅ safety: allow only client users on this module
        abort_unless($user->role === 'client', 404);

        return Inertia::render('ClientUsers/Show', [
            'user' => new UserResource($user),
        ]);
    }

    public function store(UpsertUserRequest $request)
    {
        $data = $request->validated();

        // ✅ enforce client-only creation on this module
        $data['role'] = 'client';

        $this->service->store($data);

        return back()->with('success', 'Client user created.');
    }

    public function update(UpsertUserRequest $request, User $user)
    {
        abort_unless($user->role === 'client', 404);

        $data = $request->validated();

        // ✅ enforce client-only update on this module
        $data['role'] = 'client';

        $this->service->update($user, $data);

        return back()->with('success', 'Client user updated.');
    }

    public function destroy(User $user)
    {
        abort_unless($user->role === 'client', 404);

        $this->repo->delete($user);

        return back()->with('success', 'Client user deleted.');
    }
}