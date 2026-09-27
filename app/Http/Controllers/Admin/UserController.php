<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /** FR-21 */
    public function index()
    {
        return view('admin.users.index', ['users' => User::orderBy('role')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['role' => 'kasir', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'username'  => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')],
            'email'     => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')],
            'role'      => ['required', 'in:admin,kasir'],
            'is_active' => ['required', 'boolean'],
            'password'  => ['required', 'string', 'min:8', 'confirmed'],
        ], [], ['name' => 'nama lengkap', 'username' => 'nama pengguna', 'password' => 'kata sandi']);

        $user = User::create($data);

        AuditLogger::log('create', 'Pengguna "'.$user->name.'" ('.$user->role.') ditambahkan', $user);

        return redirect()->route('admin.users.index')->with('success', 'Pengguna tersimpan.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'username'  => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email'     => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'role'      => ['required', 'in:admin,kasir'],
            'is_active' => ['required', 'boolean'],
            'password'  => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [], ['name' => 'nama lengkap', 'username' => 'nama pengguna', 'password' => 'kata sandi']);

        // BR-14: admin tidak dapat menonaktifkan akunnya sendiri
        if ($user->id === auth()->id() && ! $request->boolean('is_active')) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri (BR-14).')->withInput();
        }

        $old = $user->only(['name', 'username', 'role', 'is_active']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        AuditLogger::log('update', 'Pengguna "'.$user->name.'" diperbarui', $user, $old, $user->only(['name', 'username', 'role', 'is_active']));

        return redirect()->route('admin.users.index')->with('success', 'Perubahan pengguna tersimpan.');
    }

    /** Aktif / nonaktif cepat. */
    public function toggle(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri (BR-14).');
        }

        $user->update(['is_active' => ! $user->is_active]);

        AuditLogger::log('update', 'Pengguna "'.$user->name.'" '.($user->is_active ? 'diaktifkan' : 'dinonaktifkan'), $user);

        return back()->with('success', 'Status pengguna diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $user->delete();

        AuditLogger::log('delete', 'Pengguna "'.$user->name.'" dihapus (soft delete)', $user);

        return back()->with('success', 'Pengguna dihapus.');
    }
}
