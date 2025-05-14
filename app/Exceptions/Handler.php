<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (MethodNotAllowedHttpException $e) {
            return response()->json([
                'message' => 'Metode HTTP tidak diizinkan. Silakan gunakan metode yang benar untuk endpoint ini.',
                'error' => 'Method Not Allowed',
                'allowed_methods' => $e->getHeaders()['Allow'] ?? []
            ], 405);
        });
    }
} 