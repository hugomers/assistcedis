<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\StoreSegment;


class Authenticate
{
   public function handle($request, Closure $next)
    {
        $header = $request->header('Authorization');
        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response('Unauthorized.', 401);
        }
        $token = substr($header, 7);
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
            if (Carbon::parse($payload['exp'])->isPast()) {
                return response('Token expired.', 401);
            }
        } catch (\Exception $e) {
            return response('Invalid token.', 401);
        }
        $user = $payload;
        $listUser = User::find($user['uid']);

        if ($listUser->_state == 3) {
            return response()->json([
                'state' => 3,
                'error' => 'Usuario Bloqueado, Favor de acercarce a un encargado :0'
            ], 403);
        }
        if ($listUser->_state == 4) {
            return response()->json([
                'state' => 4,
                'error' => 'Usuario dado de Baja x_x'
            ], 403);
        }
        if ($listUser->_state == 5) {
            return response()->json([
                'state' => 5,
                'error' => 'Inicia sesion de nuevo :)'
            ], 401);
        }
        // $segmentaciones = StoreSegment::all();
        // $ips = null;
        // switch ($user['rol']) {
        //     case 'root':
        //     case 'dir':
        //     case 'des':
        //     case 'com':
        //     case 'adm':
        //         $ips = '*';
        //         break;
        //     case 'gen':
        //     case 'aux':
        //     case 'aud':
        //     case 'chf':
        //     case 'gce':
        //     case 'jch':
        //     case 'audc':
        //         $ips = $segmentaciones->pluck('segment')->toArray();
        //         break;
        //     default:
        //         $ips = $segmentaciones->where('_store', $listUser->_store)->pluck('segment')->toArray();
        //         break;
        // }
        // $ip = $request->ip();

        // if ($ips !== '*' && !collect($ips)->first(fn($seg) => str_starts_with($ip, $seg))) {
        //     return response()->json([
        //         'error' => 'Acceso denegado: IP no permitida',
        //         'ip' => $ip
        //     ], 403);
        // }
        return $next($request);
    }
}
