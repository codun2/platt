<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey as StoredKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Make writes safe to retry. A POST/PATCH/PUT/DELETE carrying an `Idempotency-Key`
 * header (the offline outbox always sends one) has its first successful JSON response
 * stored per user; a replay with the same key gets that response back instead of
 * running again — a sale whose reply was lost on a dropped connection is never
 * recorded twice.
 */
class IdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = substr((string) $request->header('Idempotency-Key', ''), 0, 80);
        $user = $request->user();
        if ($key === '' || ! $user || ! in_array($request->method(), ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return $next($request);
        }

        if ($hit = StoredKey::where('user_id', $user->id)->where('key', $key)->first()) {
            return response($hit->response, $hit->status, ['Content-Type' => 'application/json', 'Idempotent-Replayed' => 'true']);
        }

        $response = $next($request);
        if ($response->getStatusCode() < 300 && str_starts_with((string) $response->headers->get('Content-Type'), 'application/json')) {
            try {
                StoredKey::create(['user_id' => $user->id, 'key' => $key, 'method' => $request->method(), 'path' => substr($request->path(), 0, 255),
                    'status' => $response->getStatusCode(), 'response' => $response->getContent(), 'created_at' => now()]);
            } catch (\Throwable $e) {
                report($e);   // remembering the key must never fail the write itself
            }
        }

        return $response;
    }
}
