<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * A student still on their default password (last name + ID number, easy to guess) must change it
 * before using ClearTrack: every page sends them to Account until they do. Students who changed it
 * before this was tracked are recognised once (their password isn't the default) and let through.
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->password_changed_at || $request->routeIs('settings', 'settings.password', 'logout') || !$user->hasRole('student')) {
            return $next($request);
        }

        if (!Hash::check(User::defaultPassword($user->name, $user->username), $user->password)) {
            $user->forceFill(['password_changed_at' => now()])->save(); // already their own password

            return $next($request);
        }

        return redirect()->route('settings');
    }
}
