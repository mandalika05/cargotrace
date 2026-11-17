<?php
/*
 * ============================================================
 * BUAT APA : Pintu masuk konfigurasi aplikasi Laravel 12.
 *            Di sini kita mendaftarkan:
 *            1) route web + api,
 *            2) nama pendek middleware 'role',
 *            3) aturan: semua request /api/* dijawab JSON (tanpa halaman error HTML).
 * ============================================================
 */

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // P14: header keamanan di semua respons (web dan API)
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Nama pendek 'role' -> dipakai di route: ->middleware('role:admin,operator')
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Request ke /api/* selalu dijawab JSON (401/403/404/422 rapi)
        $exceptions->shouldRenderJsonWhen(
            fn ($request, $e) => $request->is('api/*') || $request->expectsJson()
        );

        // P11: jawaban 404 API yang rapi (tanpa membocorkan nama kelas model)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });
    })->create();
