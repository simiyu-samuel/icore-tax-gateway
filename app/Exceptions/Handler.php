<?php
namespace App\Exceptions;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request; // Import Request
use Throwable;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Str; // Import Str for UUID in traceId fallback

class Handler extends ExceptionHandler
{
    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // You can add custom reporting logic here (e.g., send to Sentry/Bugsnag)
        });

        // If you prefer to handle KraApiException here directly, uncomment and use this:
        // $this->renderable(function (KraApiException $e, Request $request) {
        //     return $e->render($request); // Delegate rendering to the KraApiException itself
        // });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $e): SymfonyResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API specific exceptions and return a consistent JSON response.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Throwable $exception
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleApiException($request, Throwable $exception): JsonResponse
    {
        // Default error values for the Gateway's response
        $statusCode = SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR;
        $error = 'Internal Server Error';
        $message = 'An unexpected error occurred.';
        $gatewayErrorCode = 'ICORE_UNEXPECTED_ERROR';
        $details = [];

        if ($exception instanceof AuthenticationException) {
            $statusCode = SymfonyResponse::HTTP_UNAUTHORIZED;
            $error = 'Unauthorized';
            $message = 'Authentication required.';
            $gatewayErrorCode = 'ICORE_AUTH_REQUIRED';
        } elseif ($exception instanceof ValidationException) {
            $statusCode = SymfonyResponse::HTTP_BAD_REQUEST;
            $error = 'Bad Request';
            $message = 'One or more validation errors occurred.';
            $gatewayErrorCode = 'ICORE_VALIDATION_ERROR';
            $details['fieldErrors'] = $this->formatValidationErrors($exception->errors());
        } elseif ($exception instanceof KraApiException) {
            // If it's our custom KRA API exception, delegate to its render method
            // This means the logic in KraApiException's render will be used
            return $exception->render($request);
        }
        elseif ($exception instanceof HttpException) {
            $statusCode = $exception->getStatusCode();
            $error = SymfonyResponse::$statusTexts[$statusCode] ?? 'HTTP Error';
            $message = $exception->getMessage() ?: $error;
            $gatewayErrorCode = 'ICORE_HTTP_ERROR';
        }
        // For any other unexpected exceptions, show full details in debug mode
        if (config('app.debug')) {
            $details['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString()), // Convert to array of lines
            ];
        }

        return response()->json([
            'timestamp' => now()->toISOString(),
            'status' => $statusCode,
            'error' => $error,
            'message' => $message,
            'gatewayErrorCode' => $gatewayErrorCode,
            'details' => $details,
            'traceId' => $request->attributes->get('traceId') ?? (string) Str::uuid()
        ], $statusCode);
    }

    /**
     * Formats validation errors into a structured array.
     *
     * @param array $errors
     * @return array
     */
    protected function formatValidationErrors(array $errors): array
    {
        $formatted = [];
        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $formatted[] = [
                    'field' => $field,
                    'code' => Str::upper(Str::snake($field . '_invalid')), // Basic code, refine as needed
                    'message' => $message
                ];
            }
        }
        return $formatted;
    }
}