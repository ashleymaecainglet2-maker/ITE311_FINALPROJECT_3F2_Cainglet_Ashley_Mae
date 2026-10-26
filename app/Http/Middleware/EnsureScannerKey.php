<?php

namespace App\Http\Middleware;

use App\Models\Gym;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureScannerKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $gymSlug = $request->header('X-Gym-Slug') ?: $request->input('gym_slug');
        $providedKey = (string) $request->header('X-Scanner-Key', '');

        if (blank($gymSlug)) {
            return response()->json([
                'status' => 'invalid_request',
                'message' => 'The X-Gym-Slug header is required.',
            ], 422);
        }

        $gym = Gym::query()
            ->where('slug', $gymSlug)
            ->where('is_active', true)
            ->first();

        if (! $gym) {
            return response()->json([
                'status' => 'invalid_gym',
                'message' => 'Gym not found or inactive.',
            ], 404);
        }

        if ($providedKey === '') {
            return response()->json([
                'status' => 'unauthorized',
                'message' => 'Scanner key is required.',
            ], 401);
        }

        $configuredKey = config('fitledger.scanner.key');
        $validCentralKey = filled($configuredKey) && hash_equals((string) $configuredKey, $providedKey);
        $validGymKey = filled($gym->scanner_key) && hash_equals((string) $gym->scanner_key, $providedKey);

        if (! $validCentralKey && ! $validGymKey) {
            return response()->json([
                'status' => 'unauthorized',
                'message' => 'Invalid scanner key for this gym.',
            ], 401);
        }

        $request->attributes->set('scanner_gym', $gym);

        return $next($request);
    }
}
