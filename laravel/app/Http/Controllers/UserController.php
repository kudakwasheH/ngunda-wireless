<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::with('role')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['is_active' => true]), 'roles' => $this->staffRoles()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('is_staff', true)],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null, 'password' => $data['password']]);
        $user->role_id = $data['role_id'];
        $user->is_active = true;
        $user->save();

        Audit::log('user.created', $user, null, ['email' => $user->email, 'role_id' => $user->role_id]);

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function edit(User $user)
    {
        return view('users.form', ['user' => $user, 'roles' => $this->staffRoles()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('is_staff', true)],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $newActive = (bool) ($data['is_active'] ?? false);
        $newRole = Role::findOrFail($data['role_id']);

        // Never allow the system to be left without an active super administrator.
        $losingSuper = $user->isSuperAdmin() && $user->is_active
            && (! $newActive || $newRole->slug !== Role::SUPER_ADMIN);
        if ($losingSuper && $this->activeSuperAdminCount() <= 1) {
            return back()->withErrors(['role_id' => 'At least one active Super Administrator must remain.'])->withInput();
        }
        if ($user->id === $request->user()->id && ! $newActive) {
            return back()->withErrors(['is_active' => 'You cannot deactivate your own account.'])->withInput();
        }

        $old = ['email' => $user->email, 'role_id' => $user->role_id, 'is_active' => $user->is_active];

        DB::transaction(function () use ($user, $data, $newActive) {
            $user->fill(array_filter(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
                'password' => $data['password'] ?? null], fn ($v) => $v !== null));
            $user->role_id = $data['role_id'];
            $user->is_active = $newActive;
            $user->save();
        });

        Audit::log('user.updated', $user, $old, ['email' => $user->email, 'role_id' => $user->role_id,
            'is_active' => $user->is_active, 'password_changed' => ! empty($data['password'])]);

        return redirect()->route('users.index')->with('status', 'User updated.');
    }

    private function staffRoles()
    {
        return Role::where('is_staff', true)->orderBy('id')->get();
    }

    private function activeSuperAdminCount(): int
    {
        return User::where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SUPER_ADMIN))->count();
    }
}
