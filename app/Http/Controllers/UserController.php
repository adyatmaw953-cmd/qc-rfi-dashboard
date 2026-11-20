<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    private const ALLOWED_ROLES = ['admin', 'qc_qa', 'production', 'warehouse', 'ppic'];

    /**
     * Display a listing of user accounts.
     */
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $search = trim((string) $request->input('search', ''));
        $roleFilter = $request->input('role');
        $statusFilter = $request->input('status');

        $query = User::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($roleFilter && in_array($roleFilter, self::ALLOWED_ROLES, true)) {
            $query->where('role', $roleFilter);
        }

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $users = $query->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $statusCounts = [
            'all' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
        ];

        return view('modules.users.index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $roleFilter,
                'status' => $statusFilter,
            ],
            'statusCounts' => $statusCounts,
            'roles' => self::ALLOWED_ROLES,
        ]);
    }

    /**
     * Show the form for creating a new user account.
     */
    public function create(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('modules.users.create', [
            'roles' => self::ALLOWED_ROLES,
        ]);
    }

    /**
     * Store a newly created user account.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => $request->validated('role'),
            'password' => $request->validated('password'),
            'is_active' => true,
        ]);

        return redirect()->route('users.index')->with('success', 'Akun pengguna berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the user account.
     */
    public function edit(Request $request, User $user): View
    {
        $this->authorizeAdmin($request);

        return view('modules.users.edit', [
            'user' => $user,
            'roles' => self::ALLOWED_ROLES,
        ]);
    }

    /**
     * Update the specified user account.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();

        if ($currentUser && $user->id === $currentUser->id && $request->validated('role') !== 'admin') {
            throw ValidationException::withMessages([
                'role' => 'Anda tidak dapat menurunkan role akun Anda sendiri.',
            ]);
        }

        if ($user->role === 'admin' && $request->validated('role') !== 'admin') {
            $activeAdminCount = User::where('role', 'admin')->where('is_active', true)->count();
            if ($user->is_active && $activeAdminCount <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'Sistem tidak boleh kehilangan admin aktif terakhir.',
                ]);
            }
        }

        $data = [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => $request->validated('role'),
        ];

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Akun pengguna berhasil diperbarui.');
    }

    /**
     * Toggle the active status of the user account.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $currentUser = $request->user();

        if ($user->is_active) {
            if ($currentUser && $user->id === $currentUser->id) {
                return back()
                    ->withErrors(['status' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.'])
                    ->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
            }

            if ($user->role === 'admin') {
                $activeAdminCount = User::where('role', 'admin')->where('is_active', true)->count();
                if ($activeAdminCount <= 1) {
                    return back()
                        ->withErrors(['status' => 'Sistem tidak boleh kehilangan admin aktif terakhir.'])
                        ->with('error', 'Sistem tidak boleh kehilangan admin aktif terakhir.');
                }
            }
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $statusText = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Akun {$user->name} berhasil {$statusText}.");
    }

    private function authorizeAdmin(Request $request): void
    {
        if ($request->user()?->role !== 'admin') {
            abort(403);
        }
    }
}
