<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Planes\PlanOrganizador;
use App\Support\EsquemaUsuarios;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        EsquemaUsuarios::asegurar();

        $users = User::query()
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $planes = app(PlanOrganizador::class);
        $users->getCollection()->each(fn (User $u) => $u->setAttribute('plan', $planes->resumen($u)));

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

    /**
     * Activa un plan a mano (pago por transferencia, cortesía, reposición).
     * Queda registrado como proveedor "manual" con quién lo dio.
     */
    public function otorgarPlan(Request $request, User $user, PlanOrganizador $planes)
    {
        $datos = $request->validate([
            'plan' => ['required', 'in:base,pro'],
            'dias' => ['required', 'integer', 'min:1', 'max:366'],
        ]);

        $planes->registrarPago(
            userId: $user->id,
            plan: $datos['plan'],
            proveedor: 'manual',
            referencia: 'manual:'.Str::uuid(),
            monto: 0,
            fin: now()->addDays((int) $datos['dias']),
            notas: 'Otorgado por '.$request->user()->email,
        );

        return back()->with('success', 'Plan activado.');
    }

    /** Termina ya los planes vigentes del usuario. */
    public function revocarPlan(User $user, PlanOrganizador $planes)
    {
        $planes->revocarTodo($user);

        return back()->with('success', 'Plan terminado.');
    }
}
