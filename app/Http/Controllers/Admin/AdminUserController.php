<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    /**
     * Lista paginada de todos los usuarios (solo superadmin).
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');

        $users = User::query()
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/UserManagement', [
            'users'  => $users,
            'search' => $search,
        ]);
    }

    /**
     * Crea un nuevo usuario con rol admin.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => User::ROLE_ADMIN,
        ]);

        return back()->with('success', 'Usuario administrador creado.');
    }

    /**
     * Cambia el rol de un usuario (admin ↔ user).
     * El superadmin (email hardcodeado) no puede ser modificado.
     */
    public function updateRole(Request $request, User $user)
    {
        if ($user->isSuperAdmin()) {
            return back()->withErrors(['role' => 'No se puede modificar el superadmin.']);
        }

        $request->validate([
            'role' => ['required', 'in:admin,user'],
        ]);

        $user->update(['role' => $request->role]);

        return back()->with('success', 'Rol actualizado.');
    }
}
