<?php

namespace App\Exceptions;

use Exception;
use Throwable;
use Illuminate\Http\Request; // Import Request for the render method
use Symfony\Component\HttpFoundation\Response as SymfonyResponse; // Use Symfony Response alias

class KraApiException extends Exception
{
    /**
     * The raw XML/JSON response received from KRA, if available.
     * @var string|null
     */
    public ?string $kraRawResponse;

    /**
     * The specific KRA error code (e.g., "40", "31").
     * @var string|null
     */
    public ?string $kraErrorCode;

    /**
     * The HTTP status code that was returned from the immediate KRA API call, if available.
     * @var int|null
     */
    public ?int $kraHttpStatus;

    /**
     * Create a new KRA API exception instance.
     *
     * @param string $message A general message describing the error.
     * @param string|null $kraRawResponse The raw response body from KRA.
     * @param string|null $kraErrorCode The KRA-specific error code.
     * @param int|null $kraHttpStatus The HTTP status code from the KRA response.
     * @param int $code A numeric error code (optional, defaults to 0).
     * @param Throwable|null $previous The previous exception in the call stack (optional).
     */
    public function __construct(string $message = "", ?string $kraRawResponse = null, ?string $kraErrorCode = null, ?int $kraHttpStatus = null, int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->kraRawResponse = $kraRawResponse;
        $this->kraErrorCode = $kraErrorCode;
        $this->kraHttpStatus = $kraHttpStatus;
    }

    /**
     * Render the exception into an HTTP response.
     * This method formats the KRA-specific error into our consistent API error structure.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render(Request $request): SymfonyResponse
    {
        // Default error values for the Gateway's response
        $statusCode = SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR; // Default to 500
        $gatewayErrorCode = 'KRA_API_ERROR'; // Default internal code
        $translatedMessage = 'An unexpected error occurred during KRA communication.';
        $error = 'Internal Server Error'; // Default HTTP status name

        // Map KRA specific error codes to appropriate HTTP status codes and messages
        switch ($this->kraErrorCode) {
            case '00': // No error (should ideally not be caught here, but for completeness)
                $statusCode = SymfonyResponse::HTTP_OK;
                $gatewayErrorCode = 'KRA_SUCCESS';
                $translatedMessage = 'KRA operation successful.';
                $error = 'OK';
                break;
            case '11': // internal memory full
            case '12': // internal data corrupted
            case '13': // internal memory error
            case '20': // Real Time Clock error
            case '91': // Backup error
            case '99': // Hardware intervention is necessary
                $statusCode = SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR;
                $gatewayErrorCode = 'KRA_DEVICE_INTERNAL_ERROR';
                $translatedMessage = 'KRA device internal error. Please contact KRA support with the Trace ID.';
                $error = 'Internal Server Error';
                break;
            case '30': // wrong command code (Should be caught by Gateway's own validation)
            case '31': // wrong data format in the TIS request data
            case '33': // wrong tax rate in the TIS request data
            case '34': // invalid receipt number in the TIS request data
                $statusCode = SymfonyResponse::HTTP_BAD_REQUEST;
                $gatewayErrorCode = 'KRA_REQUEST_DATA_INVALID';
                $translatedMessage = 'The request data sent to KRA was invalid or malformed. Please review input.';
                $error = 'Bad Request';
                break;
            case '32': // wrong PIN in the TIS request data
                $statusCode = SymfonyResponse::HTTP_FORBIDDEN;
                $gatewayErrorCode = 'KRA_INVALID_PIN';
                $translatedMessage = 'The Taxpayer PIN provided is invalid or unauthorized for this operation with KRA.';
                $error = 'Forbidden';
                break;
            case '40': // OSCU/VSCU not activated
                $statusCode = SymfonyResponse::HTTP_PRECONDITION_FAILED; // Or 409 Conflict
                $gatewayErrorCode = 'KRA_DEVICE_NOT_ACTIVATED';
                $translatedMessage = 'The KRA device is not activated or initialized. Please complete device setup.';
                $error = 'Precondition Failed';
                break;
            case '41': // OSCU/VSCU already activated
                $statusCode = SymfonyResponse::HTTP_CONFLICT;
                $gatewayErrorCode = 'KRA_DEVICE_ALREADY_ACTIVATED';
                $translatedMessage = 'The KRA device is already activated. No action needed.';
                $error = 'Conflict';
                break;
            case '42': // OSCU/VSCU Authentication error
                $statusCode = SymfonyResponse::HTTP_UNAUTHORIZED; // From KRA's perspective
                $gatewayErrorCode = 'KRA_DEVICE_AUTH_FAILED';
                $translatedMessage = 'Authentication failed with the KRA device. Gateway configuration error. Please contact support.';
                $error = 'Unauthorized';
                break;
            case '90': // Internet error (KRA device has no internet)
                $statusCode = SymfonyResponse::HTTP_SERVICE_UNAVAILABLE;
                $gatewayErrorCode = 'KRA_DEVICE_NETWORK_ERROR';
                $translatedMessage = 'The KRA device has no internet connection. Please check device network.';
                $error = 'Service Unavailable';
                break;
            default:
                // If it's an unknown KRA error code or just a generic error from KraApi service
                if ($this->getMessage()) {
                    $translatedMessage = $this->getMessage();
                }
                break;
        }

        // Override status code if the initial HTTP status from KRA was a client/server error
        if ($this->kraHttpStatus && $this->kraHttpStatus >= 400) {
             $statusCode = $this->kraHttpStatus;
             $error = SymfonyResponse::$statusTexts[$statusCode] ?? 'HTTP Error';
        }


        // Return the formatted JSON response
        return response()->json([
            'timestamp' => now()->toISOString(),
            'status' => $statusCode,
            'error' => $error,
            'message' => $translatedMessage,
            'gatewayErrorCode' => $gatewayErrorCode,
            'details' => [
                'kraErrorCode' => $this->kraErrorCode,
                'kraRawResponse' => config('app.debug') ? $this->kraRawResponse : null, // Only show raw response in debug mode
                'kraHttpStatus' => $this->kraHttpStatus,
            ],
            'traceId' => $request->attributes->get('traceId') // Use traceId from middleware
        ], $statusCode);
    }
}