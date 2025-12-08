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
            // $request->merge(['authUser' => $payload]);


        } catch (\Exception $e) {
            return response('Invalid token.', 401);
        }
        $user = $payload;
        $listUser = User::find($user['uid']);
        $segmentaciones = StoreSegment::all();
        $ips = null;
        switch ($user['rol']) {
            case 'root':
            case 'dir':
            case 'des':
            case 'com':
            case 'adm':
                $ips = '*';
                break;
            case 'gen':
            case 'aux':
            case 'aud':
            case 'chf':
            case 'gce':
            case 'jch':
            case 'audc':
                $ips = $segmentaciones->pluck('segment')->toArray();
                break;
            default:
                $ips = $segmentaciones->where('_store', $listUser->_store)->pluck('segment')->toArray();
                break;
        }
        $ip = $request->ip();

        if ($ips !== '*' && !collect($ips)->first(fn($seg) => str_starts_with($ip, $seg))) {
            return response()->json([
                'error' => 'Acceso denegado: IP no permitida',
                'ip' => $ip,
                'records'=>$records
            ], 403);
        }
        return $next($request);
    }
}
