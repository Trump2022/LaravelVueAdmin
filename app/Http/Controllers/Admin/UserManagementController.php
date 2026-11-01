<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class UserManagementController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::query()
            ->select(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at']);

        $search = trim($validated['search'] ?? '');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();

        return response()->json($users);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'email.unique' => '该邮箱已被使用。',
        ]);

        $user = User::create($validated);
        $user->refresh();

        return response()->json([
            'data' => $this->userData($user),
        ], 201);
    }

    /**
     * @param User $user
     * @return JsonResponse
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => $this->userData($user),
        ]);
    }

    /**
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->getKey()),
            ],
        ], [
            'email.unique' => '该邮箱已被使用。',
        ]);

        $user->update($validated);
        $user->refresh();

        return response()->json([
            'data' => $this->userData($user),
        ]);
    }

    /**
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function updatePassword(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->password = $validated['password'];
        $user->save();

        return response()->json([
            'data' => $this->userData($user),
        ]);
    }

    /**
     * @param User $user
     * @return JsonResponse
     * @throws Throwable
     */
    public function destroy(User $user): JsonResponse
    {
        DB::transaction(function () use ($user): void {
            $user->delete();

            $emailBase = preg_replace('/_del_\d+$/', '', $user->email) ?? $user->email;
            $suffix = '_del_'.$user->getKey();

            $user->email = Str::substr(
                    $emailBase,
                    0,
                    255 - Str::length($suffix),
                ).$suffix;

            $user->save();
        });

        return response()->json([
            'message' => '用户已删除。',
        ]);
    }

    /**
     * @param User $user
     * @return array
     */
    private function userData(User $user): array
    {
        return $user->only([
            'id',
            'name',
            'email',
            'email_verified_at',
            'created_at',
            'updated_at',
        ]);
    }
}
