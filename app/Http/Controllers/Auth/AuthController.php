<?php
/*
 * ============================================================
 * BUAT APA : Login, daftar (customer), dan logout lewat web.
 * ALUR LOGIN:
 *   1) Validasi (LoginRequest)  2) Auth::attempt cek email+password (hash)
 *   3) session()->regenerate()  -> mencegah session fixation
 *   4) redirect()->intended()   -> kembali ke halaman yang tadi dituju
 * ============================================================
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->validated(), $request->boolean('remember'))) {
            // Pesan sengaja umum: tidak membocorkan apakah email terdaftar atau tidak
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        // role tidak ikut diisi -> bawaan database: customer
        $user = User::create($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();      // hapus data session
        $request->session()->regenerateToken(); // token CSRF baru

        return redirect()->route('login');
    }
}
