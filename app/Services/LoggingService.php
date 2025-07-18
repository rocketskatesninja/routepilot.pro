<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class LoggingService
{
    /**
     * Log an error with context.
     */
    public static function logError(string $message, array $context = [], ?\Throwable $exception = null): void
    {
        $logData = [
            'level' => 'error',
            'message' => $message,
            'user_id' => Auth::id(),
            'user_email' => Auth::user()?->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'context' => $context,
        ];

        if ($exception) {
            $logData['exception'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        Log::error($message, $logData);
    }

    /**
     * Log user actions for audit trail.
     */
    public static function logUserAction(string $action, array $data = [], ?string $model = null, ?int $modelId = null): void
    {
        $logData = [
            'level' => 'info',
            'action' => $action,
            'user_id' => Auth::id(),
            'user_email' => Auth::user()?->email,
            'ip_address' => request()->ip(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'data' => $data,
        ];

        if ($model) {
            $logData['model'] = $model;
        }

        if ($modelId) {
            $logData['model_id'] = $modelId;
        }

        Log::info("User Action: {$action}", $logData);
    }

    /**
     * Log performance metrics.
     */
    public static function logPerformance(string $operation, float $duration, array $context = []): void
    {
        $logData = [
            'level' => 'info',
            'operation' => $operation,
            'duration_ms' => $duration,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'context' => $context,
        ];

        Log::info("Performance: {$operation} took {$duration}ms", $logData);
    }

    /**
     * Log database queries that take longer than threshold.
     */
    public static function logSlowQuery(string $sql, float $duration, array $bindings = []): void
    {
        if ($duration > 100) { // Log queries taking more than 100ms
            $logData = [
                'level' => 'warning',
                'sql' => $sql,
                'duration_ms' => $duration,
                'bindings' => $bindings,
                'url' => request()->fullUrl(),
                'user_id' => Auth::id(),
            ];

            Log::warning("Slow Query: {$duration}ms", $logData);
        }
    }

    /**
     * Log security events.
     */
    public static function logSecurityEvent(string $event, array $context = []): void
    {
        $logData = [
            'level' => 'warning',
            'event' => $event,
            'user_id' => Auth::id(),
            'user_email' => Auth::user()?->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'context' => $context,
        ];

        Log::warning("Security Event: {$event}", $logData);
    }

    /**
     * Log API requests and responses.
     */
    public static function logApiRequest(Request $request, $response = null, float $duration = null): void
    {
        $logData = [
            'level' => 'info',
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'user_id' => Auth::id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_data' => $request->except(['password', 'password_confirmation']),
        ];

        if ($response) {
            $logData['response_status'] = $response->getStatusCode();
            $logData['response_data'] = $response->getContent();
        }

        if ($duration !== null) {
            $logData['duration_ms'] = $duration;
        }

        Log::info("API Request: {$request->method()} {$request->path()}", $logData);
    }

    /**
     * Log file operations.
     */
    public static function logFileOperation(string $operation, string $filePath, array $context = []): void
    {
        $logData = [
            'level' => 'info',
            'operation' => $operation,
            'file_path' => $filePath,
            'user_id' => Auth::id(),
            'ip_address' => request()->ip(),
            'context' => $context,
        ];

        Log::info("File Operation: {$operation} on {$filePath}", $logData);
    }

    /**
     * Log export operations.
     */
    public static function logExport(string $type, int $recordCount, string $format = 'csv'): void
    {
        $logData = [
            'level' => 'info',
            'export_type' => $type,
            'record_count' => $recordCount,
            'format' => $format,
            'user_id' => Auth::id(),
            'user_email' => Auth::user()?->email,
            'ip_address' => request()->ip(),
        ];

        Log::info("Export: {$type} ({$recordCount} records) to {$format}", $logData);
    }
} 