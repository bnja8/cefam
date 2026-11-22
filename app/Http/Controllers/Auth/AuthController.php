<?php


namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Support\People\Rut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    // Valida y guarda un nuevo registro.
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $rut = $request->input('rut');
        $password = $request->input('password');
        if (! is_string($rut) || ! is_string($password) || strlen($rut) > 32 || strlen($password) > 256) {
            return $this->invalidCredentials($request);
        }

        $canonicalRut = Rut::limpiarRut($rut);
        if (! Rut::validarRut($canonicalRut)) {
            return $this->invalidCredentials($request);
        }

        if (Auth::attempt(['rut' => $canonicalRut, 'password' => $password, 'activo' => true])) {
            $request->session()->regenerate();

            return ($request->expectsJson() || $request->routeIs('api.*'))
                ? $this->authenticatedUserResponse($request)
                : redirect()->intended(route('dashboard'));
        }

        return $this->invalidCredentials($request);
    }

    private function invalidCredentials(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson() && ! $request->routeIs('api.*')) {
            return redirect()->route('login')
                ->withErrors(['rut' => 'Credenciales inválidas'])
                ->withInput($request->only('rut'));
        }

        return response()->json([
            'message' => 'Credenciales inválidas',
            'errors' => ['rut' => ['Credenciales inválidas']],
        ], 422);
    }

    public function user(Request $request): JsonResponse
    {
        return $this->authenticatedUserResponse($request);
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ($request->expectsJson() || $request->routeIs('api.*'))
            ? response()->json(['data' => ['message' => 'Sesión cerrada']])
            : redirect()->route('login')->with('status', 'Sesión cerrada.');
    }

    private function authenticatedUserResponse(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 401);

        return response()->json([
            'data' => [
                'rut' => $user->rut,
                'nombre' => $user->nombre_completo,
                'roles' => $user->roles()->orderBy('roles.rol_id')->pluck('nombre')->all(),
            ],
        ]);
    }
}
