<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $role = $request->query('role');
        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);

        $query = User::query();

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($role && in_array($role, ['user', 'admin'], true)) {
            $query->where('role', $role);
        }

        $users = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => collect($users->items())->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role instanceof UserRole ? $u->role->value : $u->role,
                'karma_points' => $u->karma_points ?? 0,
                'reputation_badge' => $u->reputation_badge,
                'avatar_url' => $u->avatar_url,
                'created_at' => $u->created_at?->toISOString(),
                'is_current_user' => $request->user()->id === $u->id,
                'is_head_admin' => strcasecmp(trim($u->email), 'admin@bangued.ph') === 0,
                'can_edit_role' => $request->user()->id !== $u->id && strcasecmp(trim($u->email), 'admin@bangued.ph') !== 0,
                'can_delete' => $request->user()->id !== $u->id && strcasecmp(trim($u->email), 'admin@bangued.ph') !== 0,
            ]),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function updateRole(User $user, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['user', 'admin'])],
        ]);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Administrators cannot edit their own role.'], 422);
        }

        if (strcasecmp(trim($user->email), 'admin@bangued.ph') === 0) {
            return response()->json(['message' => 'The head administrator role cannot be altered.'], 422);
        }

        $beforeRole = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
        $user->role = $validated['role'];
        $user->save();
        $afterRole = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        $this->audit->record('user', $user->id, 'role_change', $request->user()->id, [
            'before' => $beforeRole,
            'after' => $afterRole,
        ]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'role' => $user->role instanceof UserRole ? $user->role->value : $user->role,
                'message' => 'User role updated successfully.',
            ],
        ]);
    }

    public function destroy(User $user, Request $request): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account from administration.'], 422);
        }

        if (strcasecmp(trim($user->email), 'admin@bangued.ph') === 0) {
            return response()->json(['message' => 'The head administrator account cannot be deleted.'], 422);
        }

        $userId = $user->id;
        $userName = $user->name;
        $userEmail = $user->email;
        $userRole = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->delete();

        $this->audit->record('user', $userId, 'delete', $request->user()->id, [
            'name' => $userName,
            'email' => $userEmail,
            'role' => $userRole,
        ]);

        return response()->json([
            'data' => [
                'message' => 'User account deleted successfully.',
            ],
        ]);
    }
}
