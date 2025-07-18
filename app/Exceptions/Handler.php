<?php

namespace App\Exceptions;

use App\Services\LoggingService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
            // Log all exceptions
            LoggingService::logError($e->getMessage(), [], $e);
        });

        // Handle custom exceptions
        $this->renderable(function (TechnicianException $e, Request $request) {
            return $this->handleCustomException($e, $request, 'Technician');
        });

        $this->renderable(function (ClientException $e, Request $request) {
            return $this->handleCustomException($e, $request, 'Client');
        });

        $this->renderable(function (LocationException $e, Request $request) {
            return $this->handleCustomException($e, $request, 'Location');
        });

        $this->renderable(function (InvoiceException $e, Request $request) {
            return $this->handleCustomException($e, $request, 'Invoice');
        });

        $this->renderable(function (ReportException $e, Request $request) {
            return $this->handleCustomException($e, $request, 'Report');
        });

        // Handle validation exceptions
        $this->renderable(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                    'code' => 'VALIDATION_ERROR'
                ], 422);
            }
        });

        // Handle model not found exceptions
        $this->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found',
                    'code' => 'NOT_FOUND'
                ], 404);
            }
        });

        // Handle not found HTTP exceptions
        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page not found',
                    'code' => 'NOT_FOUND'
                ], 404);
            }
        });

        // Handle authentication exceptions
        $this->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                    'code' => 'UNAUTHENTICATED'
                ], 401);
            }
        });

        // Handle HTTP exceptions
        $this->renderable(function (HttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'An error occurred',
                    'code' => 'HTTP_ERROR'
                ], $e->getStatusCode());
            }
        });
    }

    /**
     * Handle custom exceptions with consistent response format.
     */
    protected function handleCustomException(Throwable $e, Request $request, string $type): ?JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $type . '_ERROR'
            ], $e->getCode() ?: 500);
        }

        // For web requests, flash error message and redirect
        if ($request->isMethod('GET')) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return null;
    }

    /**
     * Report or log an exception.
     */
    public function report(Throwable $e): void
    {
        // Log the exception with additional context
        LoggingService::logError($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ], $e);

        parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Log the exception before rendering
        LoggingService::logError('Exception rendered', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
        ], $e);

        return parent::render($request, $e);
    }
} 