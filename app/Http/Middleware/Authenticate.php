<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\StoreSegment;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Auth;



class Authenticate
{
    public function handle($request, Closure $next){
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $payload = JWTAuth::getPayload();

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token Expirado, Inicia sesion de nuevo :!'
            ], 401);
        }

        if ($user->_state == 3) {
            return response()->json([
                'state' => 3,
                'error' => 'Usuario Bloqueado, Favor de acercarce a un encargado :0'
            ], 403);
        }
        if ($user->_state == 4) {
            return response()->json([
                'state' => 4,
                'error' => 'Usuario dado de Baja x_x'
            ], 403);
        }
        if ($user->_state == 5) {
            return response()->json([
                'state' => 5,
                'error' => 'Inicia sesion de nuevo :)'
            ], 401);
        }


        $request->attributes->set('ctx', [
            'uid'  => $payload->get('uid'),
            'sid' => $payload->get('_store'),
            'rid'   => $payload->get('rol'),
        ]);
        Auth::setUser($user);
        return $next($request);
    }
}
