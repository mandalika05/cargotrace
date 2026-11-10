<?php
/*
 * ============================================================
 * BUAT APA : Admin mengelola akun (daftar, buat staf, ubah peran).
 * KEAMANAN : 'role' diisi eksplisit di sini (tidak masuk $fillable).
 *            Admin tidak boleh mengubah perannya sendiri (mencegah
 *            tidak sengaja menghapus satu-satunya admin).
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.addcslashes($request->string('q')->toString(), '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when(in_array($request->input('role'), [User::ROLE_ADMIN, User::ROLE_OPERATOR, User::ROLE_CUSTOMER], true),
                fn ($query) => $query->where('role', $request->input('role')))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'q' => $request->string('q')->toString(),
            'role' => $request->input('role'),
        ]);
    }

    public function create(): View
    {
        return view('users.form', ['account' => new User(['role' => User::ROLE_OPERATOR])]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->role = $data['role']; // diisi eksplisit, bukan mass-assignment
        $user->save();

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        return view('users.form', ['account' => $user]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if ($user->is($request->user()) && $data['role'] !== $user->role) {
            return back()->withErrors(['role' => 'You cannot change your own role.'])->withInput();
        }

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        if (! empty($data['password'])) {
            $user->password = $data['password']; // otomatis di-hash oleh cast
        }
        $user->role = $data['role'];
        $user->save();

        return redirect()->route('users.index')->with('success', 'User updated.');
    }
}
