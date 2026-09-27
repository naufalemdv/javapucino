<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /** FR-01 */
    public function create()
    {
        if (Auth::check()) {
            return redirect()->to(self::homeFor(Auth::user()));
        }

        $demoUsers = User::where('is_active', true)->orderBy('role')->get();

        return view('auth.login', compact('demoUsers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ], [], ['username' => 'nama pengguna', 'password' => 'kata sandi']);

        $key = mb_strtolower($data['username']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'username' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        $user = User::where('username', $data['username'])->first();

        if (! $user || ! Auth::attempt(['username' => $data['username'], 'password' => $data['password']])) {
            RateLimiter::hit($key, 60);

            AuditLogger::log('login_failed', 'Login gagal untuk username: '.$data['username'], null, null, null, $user?->id);

            throw ValidationException::withMessages([
                'username' => 'Nama pengguna atau kata sandi salah.',
            ]);
        }

        // BR-14: pengguna nonaktif tidak dapat login
        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'username' => 'Akun Anda dinonaktifkan. Hubungi administrator.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        AuditLogger::log('login', 'Login berhasil sebagai '.$user->roleLabel());

        return redirect()->to(self::homeFor($user));
    }

    /** FR-03: logout tidak menutup shift. */
    public function destroy(Request $request)
    {
        AuditLogger::log('logout', 'Logout dari sistem');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public static function homeFor(User $user): string
    {
        return $user->isAdmin() ? route('admin.dashboard') : route('kasir.pos');
    }
}
