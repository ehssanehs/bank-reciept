<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->with('roles')->orderBy('created_at', 'desc')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User(), 'roles' => Role::all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'locale' => ['required', Rule::in(['en', 'fa'])],
            'role' => ['required', 'string', 'exists:roles,slug'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'locale' => $data['locale'],
            'is_active' => true,
        ]);

        $user->assignRole($data['role']);

        return redirect()->route('admin.users')->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user, 'roles' => Role::all()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'locale' => ['required', Rule::in(['en', 'fa'])],
            'is_active' => ['sometimes', 'boolean'],
            'role' => ['required', 'string', 'exists:roles,slug'],
        ]);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'locale' => $data['locale'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
        if (!empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);
        $user->roles()->detach();
        $user->assignRole($data['role']);

        return redirect()->route('admin.users')->with('status', 'User updated.');
    }
}
