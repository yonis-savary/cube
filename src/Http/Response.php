<?php

namespace Cube\Http;

use Cube\Data\DataToObject;
use Cube\Logger\Logger;
use Cube\Models\Model;
use Psr\Log\LoggerInterface;
use RuntimeException;

class Response extends HttpMessage
{
    protected int $statusCode;

    protected $displayCallback;

    public function __construct(
        int $statusCode = StatusCode::NO_CONTENT,
        string $body = '',
        array $headers = []
    ) {
        $this->statusCode = $statusCode;
        $this->setBody($body);
        $this->setHeaders($headers);
    }

    // Generic functions by Http Response Code

    public static function continue(mixed $content = null): self
    {
        return new self(StatusCode::CONTINUE, $content);
    }

    public static function switchingProtocols(mixed $content = null): self
    {
        return new self(StatusCode::SWITCHING_PROTOCOLS, $content);
    }

    public static function processing(mixed $content = null): self
    {
        return new self(StatusCode::PROCESSING, $content);
    }

    public static function earlyHints(mixed $content = null): self
    {
        return new self(StatusCode::EARLY_HINTS, $content);
    }

    public static function ok(mixed $content = null): self
    {
        return new self(StatusCode::OK, $content);
    }

    public static function created(mixed $content = null): self
    {
        return new self(StatusCode::CREATED, $content);
    }

    public static function accepted(mixed $content = null): self
    {
        return new self(StatusCode::ACCEPTED, $content);
    }

    public static function nonAuthoritativeInformation(mixed $content = null): self
    {
        return new self(StatusCode::NON_AUTHORITATIVE_INFORMATION, $content);
    }

    public static function noContent(mixed $content = null): self
    {
        return new self(StatusCode::NO_CONTENT, $content);
    }

    public static function resetContent(mixed $content = null): self
    {
        return new self(StatusCode::RESET_CONTENT, $content);
    }

    public static function patientContent(mixed $content = null): self
    {
        return new self(StatusCode::PARTIAL_CONTENT, $content);
    }

    public static function multiStatus(mixed $content = null): self
    {
        return new self(StatusCode::MULTI_STATUS, $content);
    }

    public static function alreadyReported(mixed $content = null): self
    {
        return new self(StatusCode::ALREADY_REPORTED, $content);
    }

    public static function imUsed(mixed $content = null): self
    {
        return new self(StatusCode::IM_USED, $content);
    }

    public static function multipleChoices(mixed $content = null): self
    {
        return new self(StatusCode::MULTIPLE_CHOICES, $content);
    }

    public static function movedPermanently(mixed $content = null): self
    {
        return new self(StatusCode::MOVED_PERMANENTLY, $content);
    }

    public static function found(mixed $content = null): self
    {
        return new self(StatusCode::FOUND, $content);
    }

    public static function seeOther(mixed $content = null): self
    {
        return new self(StatusCode::SEE_OTHER, $content);
    }

    public static function notModified(mixed $content = null): self
    {
        return new self(StatusCode::NOT_MODIFIED, $content);
    }

    public static function useProxy(mixed $content = null): self
    {
        return new self(StatusCode::USE_PROXY, $content);
    }

    public static function unused(mixed $content = null): self
    {
        return new self(StatusCode::UNUSED, $content);
    }

    public static function temporaryRedirect(mixed $content = null): self
    {
        return new self(StatusCode::TEMPORARY_REDIRECT, $content);
    }

    public static function permanentRedirect(mixed $content = null): self
    {
        return new self(StatusCode::PERMANENT_REDIRECT, $content);
    }

    public static function badRequest(mixed $content = null): self
    {
        return new self(StatusCode::BAD_REQUEST, $content);
    }

    public static function unauthorized(mixed $content = null): self
    {
        return new self(StatusCode::UNAUTHORIZED, $content);
    }

    public static function paymentRequired(mixed $content = null): self
    {
        return new self(StatusCode::PAYMENT_REQUIRED, $content);
    }

    public static function forbidden(mixed $content = null): self
    {
        return new self(StatusCode::FORBIDDEN, $content);
    }

    public static function notFound(mixed $content = null): self
    {
        return new self(StatusCode::NOT_FOUND, $content);
    }

    public static function methodNotAllowed(mixed $content = null): self
    {
        return new self(StatusCode::METHOD_NOT_ALLOWED, $content);
    }

    public static function notAcceptable(mixed $content = null): self
    {
        return new self(StatusCode::NOT_ACCEPTABLE, $content);
    }

    public static function proxyAuthenticationRequired(mixed $content = null): self
    {
        return new self(StatusCode::PROXY_AUTHENTICATION_REQUIRED, $content);
    }

    public static function requestTimeout(mixed $content = null): self
    {
        return new self(StatusCode::REQUEST_TIMEOUT, $content);
    }

    public static function conflict(mixed $content = null): self
    {
        return new self(StatusCode::CONFLICT, $content);
    }

    public static function gone(mixed $content = null): self
    {
        return new self(StatusCode::GONE, $content);
    }

    public static function lengthRequired(mixed $content = null): self
    {
        return new self(StatusCode::LENGTH_REQUIRED, $content);
    }

    public static function preconditionFailed(mixed $content = null): self
    {
        return new self(StatusCode::PRECONDITION_FAILED, $content);
    }

    public static function contentTooLarge(mixed $content = null): self
    {
        return new self(StatusCode::CONTENT_TOO_LARGE, $content);
    }

    public static function uriTooLong(mixed $content = null): self
    {
        return new self(StatusCode::URI_TOO_LONG, $content);
    }

    public static function unsupportedMediaType(mixed $content = null): self
    {
        return new self(StatusCode::UNSUPPORTED_MEDIA_TYPE, $content);
    }

    public static function rangeNotSatisfiable(mixed $content = null): self
    {
        return new self(StatusCode::RANGE_NOT_SATISFIABLE, $content);
    }

    public static function expectationFailed(mixed $content = null): self
    {
        return new self(StatusCode::EXPECTATION_FAILED, $content);
    }

    public static function imATeapot(mixed $content = null): self
    {
        return new self(StatusCode::IM_A_TEAPOT, $content);
    }

    public static function misdirectedRequest(mixed $content = null): self
    {
        return new self(StatusCode::MISDIRECTED_REQUEST, $content);
    }

    public static function unprocessableContent(mixed $content = null): self
    {
        return new self(StatusCode::UNPROCESSABLE_CONTENT, $content);
    }

    public static function locked(mixed $content = null): self
    {
        return new self(StatusCode::LOCKED, $content);
    }

    public static function failedDependency(mixed $content = null): self
    {
        return new self(StatusCode::FAILED_DEPENDENCY, $content);
    }

    public static function tooEarly(mixed $content = null): self
    {
        return new self(StatusCode::TOO_EARLY, $content);
    }

    public static function upgradeRequired(mixed $content = null): self
    {
        return new self(StatusCode::UPGRADE_REQUIRED, $content);
    }

    public static function preconditionRequired(mixed $content = null): self
    {
        return new self(StatusCode::PRECONDITION_REQUIRED, $content);
    }

    public static function tooManyRequests(mixed $content = null): self
    {
        return new self(StatusCode::TOO_MANY_REQUESTS, $content);
    }

    public static function requestHeaderFieldsTooLarge(mixed $content = null): self
    {
        return new self(StatusCode::REQUEST_HEADER_FIELDS_TOO_LARGE, $content);
    }

    public static function unavailableForLegalReasons(mixed $content = null): self
    {
        return new self(StatusCode::UNAVAILABLE_FOR_LEGAL_REASONS, $content);
    }

    public static function internalServerError(mixed $content = null): self
    {
        return new self(StatusCode::INTERNAL_SERVER_ERROR, $content);
    }

    public static function notImplemented(mixed $content = null): self
    {
        return new self(StatusCode::NOT_IMPLEMENTED, $content);
    }

    public static function badGateway(mixed $content = null): self
    {
        return new self(StatusCode::BAD_GATEWAY, $content);
    }

    public static function serviceUnavailable(mixed $content = null): self
    {
        return new self(StatusCode::SERVICE_UNAVAILABLE, $content);
    }

    public static function gatewayTimeout(mixed $content = null): self
    {
        return new self(StatusCode::GATEWAY_TIMEOUT, $content);
    }

    public static function httpVersionNotSupported(mixed $content = null): self
    {
        return new self(StatusCode::HTTP_VERSION_NOT_SUPPORTED, $content);
    }

    public static function variantAlsoNegociates(mixed $content = null): self
    {
        return new self(StatusCode::VARIANT_ALSO_NEGOTIATES, $content);
    }

    public static function insufficientStorage(mixed $content = null): self
    {
        return new self(StatusCode::INSUFFICIENT_STORAGE, $content);
    }

    public static function loopDetected(mixed $content = null): self
    {
        return new self(StatusCode::LOOP_DETECTED, $content);
    }

    public static function notExtended(mixed $content = null): self
    {
        return new self(StatusCode::NOT_EXTENDED, $content);
    }

    public static function networkAuthenticationRequired(mixed $content = null): self
    {
        return new self(StatusCode::NETWORK_AUTHENTICATION_REQUIRED, $content);
    }

    // Generic Response by Content Type

    public static function file(string $path, int $code = StatusCode::OK, ?string $attachmentFile = null): self
    {
        $response = (new self($code))
            ->withResponseCallback(function () use (&$path) { readfile($path); })
            ->setHeader('Content-Length', filesize($path))
        ;

        if ($attachmentFile) {
            $response->setHeader('Content-Type', 'application/octet-stream');
            $response->setHeader('Content-Disposition', 'attachment; filename='.basename($attachmentFile));
        } else {
            $response->setHeader('Content-Type', mime_content_type($path));
        }

        return $response;
    }

    public static function download(string $path, int $code = StatusCode::OK, ?string $attachmentFile = null): self
    {
        return self::file($path, $code, $attachmentFile ?? basename($path));
    }

    public static function json(mixed $value, int $code = StatusCode::OK): self
    {
        if ($value instanceof Model) {
            $value = $value->toArray();
        }

        return new self(
            $code,
            json_encode($value, JSON_THROW_ON_ERROR),
            ['Content-Type' => 'application/json']
        );
    }

    public static function html(mixed $value, int $code = StatusCode::OK): self
    {
        return new self(
            $code,
            $value,
            ['Content-Type' => 'text/html']
        );
    }

    public function logSelf(?LoggerInterface $logger = null)
    {
        $logger ??= Logger::getInstance();
        $logger->log('info', '{code} {content-type}', [
            'code' => $this->getStatusCode(),
            'content-type' => $this->getHeader('content-type') ?? 'unknown mime type',
        ]);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isOk(): bool
    {
        return ((int) ($this->statusCode/100)) == 2;
    }

    public function withResponseCallback(callable $callback): self
    {
        $this->displayCallback = $callback;

        return $this;
    }

    public function withClientCaching(int $timeToLive): self
    {
        $this->setHeader("Cache-control", "max-age=$timeToLive");
        return $this;
    }

    public function display(bool $sendHeaders = true)
    {
        if ($sendHeaders) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        if ($callback = $this->displayCallback) {
            $callback($this->statusCode, $this->body);
        } else {
            echo $this->getBody();
        }
    }

    /**
     * @template TClass of DataToObject
     * @param class-string<TClass> $dataToObjectClass
     * @return TClass
     */
    public function toObject(string $dataToObjectClass): DataToObject
    {
        if (!$this->isOk())
        {
            Logger::getInstance()->error("{error}", ['error' => $this->getHeaders()]);
            Logger::getInstance()->error("{error}", ['error' => $this->getBody()]);
            throw new RuntimeException("Could not create dataToObject instance from data, response code is ". $this->getStatusCode());
        }

        return $dataToObjectClass::fromData($this->getJSON());
    }

    public function exit(bool $sendHeaders = true)
    {
        $this->display($sendHeaders);

        exit;
    }
}
