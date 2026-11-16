<?php

namespace App\Http\Middleware;

use Closure;

class SximoAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if( !$request->header('Authorization')){
            return response()->json(['message' => 'Not a valid API request.'],401);
        }

        $token = $request->header('Authorization');
        $token = str_replace('Bearer ','', $token);
        $user = \DB::table('tb_token')
                ->leftJoin('tb_users', 'tb_users.id', '=', 'tb_token.userId')
                ->where('token', trim($token) )
                ->get();
        if(count($user) <= 0) {
            return response()->json(['message' => ' Un Authenticated.'],401);
        }
        
        $profile = $user[0];
        // group_id kini ada di tabel pivot tb_users_groups, bukan kolom tb_users
        if(!isset($profile->group_id)) {
            $group = \DB::table('tb_users_groups')->where('user_id', $profile->userId)->first();
            $profile->group_id = $group ? $group->group_id : null;
        }

        $request->attributes->add(['profile' => $profile ]);
        return $next($request);
        
    }
}
