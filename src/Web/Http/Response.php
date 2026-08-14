<?php

namespace Cube\Web\Http;

use Cube\Data\DataToObject;
use Cube\Env\Logger\Logger;
use Cube\Data\Models\Model;
use Cube\Web\Http\Configuration\CORSConfiguration;
use Psr\Log\LoggerInterface;

class Response extends HttpMessage
{
    protected int $statusCode;

    /** @var ?callable */
    protected $displayCallback = null;
    protected bool $corsDefined = false;

    public function __construct(
        int $statusCode = StatusCode::NO_CONTENT,
        ?string $body = null,
        array $headers = []
    ) {
        $this->statusCode = $statusCode;
        $this->setBody($body ?? '');
        $this->setHeaders($headers);
        $this->corsDefined = false;
    }

    // Generic functions by Http Response Code

    public static function continue(mixed $content = null): static
    {
        return new static(StatusCode::CONTINUE, $content);
    }

    public static function switchingProtocols(mixed $content = null): static
    {
        return new static(StatusCode::SWITCHING_PROTOCOLS, $content);
    }

    public static function processing(mixed $content = null): static
    {
        return new static(StatusCode::PROCESSING, $content);
    }

    public static function earlyHints(mixed $content = null): static
    {
        return new static(StatusCode::EARLY_HINTS, $content);
    }

    public static function ok(mixed $content = null): static
    {
        return new static(StatusCode::OK, $content);
    }

    public static function created(mixed $content = null): static
    {
        return new static(StatusCode::CREATED, $content);
    }

    public static function accepted(mixed $content = null): static
    {
        return new static(StatusCode::ACCEPTED, $content);
    }

    public static function nonAuthoritativeInformation(mixed $content = null): static
    {
        return new static(StatusCode::NON_AUTHORITATIVE_INFORMATION, $content);
    }

    public static function noContent(): static
    {
        return new static(StatusCode::NO_CONTENT);
    }

    public static function resetContent(mixed $content = null): static
    {
        return new static(StatusCode::RESET_CONTENT, $content);
    }

    public static function partialContent(mixed $content = null): static
    {
        return new static(StatusCode::PARTIAL_CONTENT, $content);
    }

    public static function multiStatus(mixed $content = null): static
    {
        return new static(StatusCode::MULTI_STATUS, $content);
    }

    public static function alreadyReported(mixed $content = null): static
    {
        return new static(StatusCode::ALREADY_REPORTED, $content);
    }

    public static function imUsed(mixed $content = null): static
    {
        return new static(StatusCode::IM_USED, $content);
    }

    public static function multipleChoices(mixed $content = null): static
    {
        return new static(StatusCode::MULTIPLE_CHOICES, $content);
    }

    public static function movedPermanently(mixed $content = null): static
    {
        return new static(StatusCode::MOVED_PERMANENTLY, $content);
    }

    public static function found(mixed $content = null): static
    {
        return new static(StatusCode::FOUND, $content);
    }

    public static function seeOther(mixed $content = null): static
    {
        return new static(StatusCode::SEE_OTHER, $content);
    }

    public static function notModified(mixed $content = null): static
    {
        return new static(StatusCode::NOT_MODIFIED, $content);
    }

    public static function useProxy(mixed $content = null): static
    {
        return new static(StatusCode::USE_PROXY, $content);
    }

    public static function unused(mixed $content = null): static
    {
        return new static(StatusCode::UNUSED, $content);
    }

    public static function temporaryRedirect(mixed $content = null): static
    {
        return (new static(StatusCode::TEMPORARY_REDIRECT, null))->withHeaders([
            "Location" => $content
        ]);
    }

    public static function permanentRedirect(mixed $content = null): static
    {
        return (new static(StatusCode::PERMANENT_REDIRECT, null))->withHeaders([
            "Location" => $content
        ]);
    }

    public static function badRequest(mixed $content = null): static
    {
        return new static(StatusCode::BAD_REQUEST, $content);
    }

    public static function unauthorized(mixed $content = null): static
    {
        return new static(StatusCode::UNAUTHORIZED, $content);
    }

    public static function paymentRequired(mixed $content = null): static
    {
        return new static(StatusCode::PAYMENT_REQUIRED, $content);
    }

    public static function forbidden(mixed $content = null): static
    {
        return new static(StatusCode::FORBIDDEN, $content);
    }

    public static function notFound(mixed $content = null): static
    {
        return new static(StatusCode::NOT_FOUND, $content);
    }

    public static function methodNotAllowed(mixed $content = null): static
    {
        return new static(StatusCode::METHOD_NOT_ALLOWED, $content);
    }

    public static function notAcceptable(mixed $content = null): static
    {
        return new static(StatusCode::NOT_ACCEPTABLE, $content);
    }

    public static function proxyAuthenticationRequired(mixed $content = null): static
    {
        return new static(StatusCode::PROXY_AUTHENTICATION_REQUIRED, $content);
    }

    public static function requestTimeout(mixed $content = null): static
    {
        return new static(StatusCode::REQUEST_TIMEOUT, $content);
    }

    public static function conflict(mixed $content = null): static
    {
        return new static(StatusCode::CONFLICT, $content);
    }

    public static function gone(mixed $content = null): static
    {
        return new static(StatusCode::GONE, $content);
    }

    public static function lengthRequired(mixed $content = null): static
    {
        return new static(StatusCode::LENGTH_REQUIRED, $content);
    }

    public static function preconditionFailed(mixed $content = null): static
    {
        return new static(StatusCode::PRECONDITION_FAILED, $content);
    }

    public static function contentTooLarge(mixed $content = null): static
    {
        return new static(StatusCode::CONTENT_TOO_LARGE, $content);
    }

    public static function uriTooLong(mixed $content = null): static
    {
        return new static(StatusCode::URI_TOO_LONG, $content);
    }

    public static function unsupportedMediaType(mixed $content = null): static
    {
        return new static(StatusCode::UNSUPPORTED_MEDIA_TYPE, $content);
    }

    public static function rangeNotSatisfiable(mixed $content = null): static
    {
        return new static(StatusCode::RANGE_NOT_SATISFIABLE, $content);
    }

    public static function expectationFailed(mixed $content = null): static
    {
        return new static(StatusCode::EXPECTATION_FAILED, $content);
    }

    public static function imATeapot(mixed $content = null): static
    {
        return new static(StatusCode::IM_A_TEAPOT, $content);
    }

    public static function misdirectedRequest(mixed $content = null): static
    {
        return new static(StatusCode::MISDIRECTED_REQUEST, $content);
    }

    public static function unprocessableContent(mixed $content = null): static
    {
        return new static(StatusCode::UNPROCESSABLE_CONTENT, $content);
    }

    public static function locked(mixed $content = null): static
    {
        return new static(StatusCode::LOCKED, $content);
    }

    public static function failedDependency(mixed $content = null): static
    {
        return new static(StatusCode::FAILED_DEPENDENCY, $content);
    }

    public static function tooEarly(mixed $content = null): static
    {
        return new static(StatusCode::TOO_EARLY, $content);
    }

    public static function upgradeRequired(mixed $content = null): static
    {
        return new static(StatusCode::UPGRADE_REQUIRED, $content);
    }

    public static function preconditionRequired(mixed $content = null): static
    {
        return new static(StatusCode::PRECONDITION_REQUIRED, $content);
    }

    public static function tooManyRequests(mixed $content = null): static
    {
        return new static(StatusCode::TOO_MANY_REQUESTS, $content);
    }

    public static function requestHeaderFieldsTooLarge(mixed $content = null): static
    {
        return new static(StatusCode::REQUEST_HEADER_FIELDS_TOO_LARGE, $content);
    }

    public static function unavailableForLegalReasons(mixed $content = null): static
    {
        return new static(StatusCode::UNAVAILABLE_FOR_LEGAL_REASONS, $content);
    }

    public static function internalServerError(mixed $content = null): static
    {
        return new static(StatusCode::INTERNAL_SERVER_ERROR, $content);
    }

    public static function notImplemented(mixed $content = null): static
    {
        return new static(StatusCode::NOT_IMPLEMENTED, $content);
    }

    public static function badGateway(mixed $content = null): static
    {
        return new static(StatusCode::BAD_GATEWAY, $content);
    }

    public static function serviceUnavailable(mixed $content = null): static
    {
        return new static(StatusCode::SERVICE_UNAVAILABLE, $content);
    }

    public static function gatewayTimeout(mixed $content = null): static
    {
        return new static(StatusCode::GATEWAY_TIMEOUT, $content);
    }

    public static function httpVersionNotSupported(mixed $content = null): static
    {
        return new static(StatusCode::HTTP_VERSION_NOT_SUPPORTED, $content);
    }

    public static function variantAlsoNegotiates(mixed $content = null): static
    {
        return new static(StatusCode::VARIANT_ALSO_NEGOTIATES, $content);
    }

    public static function insufficientStorage(mixed $content = null): static
    {
        return new static(StatusCode::INSUFFICIENT_STORAGE, $content);
    }

    public static function loopDetected(mixed $content = null): static
    {
        return new static(StatusCode::LOOP_DETECTED, $content);
    }

    public static function notExtended(mixed $content = null): static
    {
        return new static(StatusCode::NOT_EXTENDED, $content);
    }

    public static function networkAuthenticationRequired(mixed $content = null): static
    {
        return new static(StatusCode::NETWORK_AUTHENTICATION_REQUIRED, $content);
    }

    // Generic Response by Content Type

    public static function file(string $path, int $code = StatusCode::OK, ?string $attachmentFile = null): static
    {
        $response = (new static($code))
            ->withResponseCallback(function () use (&$path) { readfile($path); })
            ->setHeader('Content-Type', FileMIMETypes::getFileMIMEType($attachmentFile ?? $path))
        ;

        if ($attachmentFile) {
            $response->setHeader('Content-Type', 'application/octet-stream');
            $response->setHeader('Content-Disposition', 'attachment; filename='.basename($attachmentFile));
        }

        return $response;
    }

    public static function download(string $path, int $code = StatusCode::OK, ?string $attachmentFile = null): static
    {
        return static::file($path, $code, $attachmentFile ?? basename($path));
    }

    public static function json(mixed $value, int $code = StatusCode::OK): static
    {
        if ($value instanceof Model) {
            $value = $value->toArray();
        }

        return new static(
            $code,
            json_encode($value, JSON_THROW_ON_ERROR),
            ['Content-Type' => 'application/json']
        );
    }

    public static function html(mixed $value, int $code = StatusCode::OK): static
    {
        return new static(
            $code,
            $value,
            ['Content-Type' => 'text/html']
        );
    }

    public function logstatic(?LoggerInterface $logger = null): void
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
        return ((int) ($this->statusCode / 100)) == 2;
    }

    public function withResponseCallback(callable $callback): static
    {
        $this->displayCallback = $callback;

        return $this;
    }

    public function withClientCaching(int $timeToLive): static
    {
        $this->setHeader('Cache-control', "max-age={$timeToLive}");

        return $this;
    }

    public function withHeaders(array $headers): static
    {
        foreach ($headers as $header => $value)
            $this->setHeader($header, $value);

        return $this;
    }

    public function getBody(): string
    {
        if ($callback = $this->displayCallback) {
            ob_start();
            ($callback)($this->statusCode, $this->body);
            return ob_get_clean();
        } else {
            return parent::getBody();
        }
    }

    public function withCORSHeaders(?array $allowedMethods=null, ?CORSConfiguration $configuration=null): static
    {
        $this->corsDefined = true;

        $configuration ??= CORSConfiguration::resolve();
        return $this->withHeaders([
            'Access-Control-Allow-Origin' => $configuration->allowOrigin,
            'Access-Control-Allow-Methods' => $allowedMethods ? join(", ", $allowedMethods) : "*",
            'Access-Control-Allow-Headers' => $configuration->allowHeaders,
            'Access-Control-Allow-Credentials' => $configuration->allowCredentials,
            'Access-Control-Max-Age' => $configuration->maxAge,
        ]);
    }

    public function display(bool $sendHeaders = true): void
    {
        if ($this->corsDefined == false)
            $this->withCORSHeaders();

        if ($sendHeaders) {
            http_response_code($this->statusCode);

            if (array_key_exists('cache-control', $this->headers)){
                header_remove('Pragma');
            }

            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $this->getBody();
    }

    /**
     * @template TClass of DataToObject
     *
     * @param class-string<TClass> $dataToObjectClass
     *
     * @return TClass
     */
    public function toObject(string $dataToObjectClass): DataToObject
    {
        if (!$this->isOk()) {
            Logger::getInstance()->error('{error}', ['error' => $this->getHeaders()]);
            Logger::getInstance()->error('{error}', ['error' => $this->getBody()]);

            throw new \RuntimeException('Could not create dataToObject instance from data, response code is '.$this->getStatusCode());
        }

        return $dataToObjectClass::fromData($this->getJSON());
    }

    public function exit(bool $sendHeaders = true): never
    {
        $this->display($sendHeaders);
        exit;
    }
}
