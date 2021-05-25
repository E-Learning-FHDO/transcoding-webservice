<?php

namespace App\Http\Middleware;

use Closure;
use Carbon\Carbon;

class EnsureUserIsActive
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
        $active_from = isset($request->user()->active_from) ?? false;
        $active_to = isset($request->user()->active_to) ?? false;

        if (!$request->user()->active)  {
            return response()->json(array('message' => 'Forbidden, user is not set as active'), 403);
        }
        if ($active_from && Carbon::now()->lessThan($request->user()->active_from))  {
            return response()->json(array('message' => 'Forbidden, user active_from date not reached'), 403);
        }
        if ($active_to && Carbon::now()->greaterThanOrEqualTo($request->user()->active_to))  {
            return response()->json(array('message' => 'Forbidden, user active_to date reached'), 403);
        }
        return $next($request);
    }
}
