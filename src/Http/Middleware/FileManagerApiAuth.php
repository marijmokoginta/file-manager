<?php

namespace M2code\FileManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class FileManagerApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $tokens = Config::get('file-manager.api.token');

        if (empty($tokens)) {
            return $next($request);
        }

        $tokenString = is_array($tokens) ? implode(',', $tokens) : (string) $tokens;
        $validTokens = array_filter(
            array_map('trim', explode(',', $tokenString))
        );

        if (empty($validTokens)) {
            return $next($request);
        }

        $bearerToken = $request->bearerToken();

        if (!$bearerToken || !in_array($bearerToken, $validTokens, true)) {
            return response()->json([
                'message' => 'Unauthorized. Invalid or missing API token.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
