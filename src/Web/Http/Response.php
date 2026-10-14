<?php

namespace Cube\Web\Http;

use Cube\Data\Bunch;
use Cube\Data\DataToObject;
use Cube\Env\Logger\Logger;
use Cube\Data\Models\Model;
use Cube\Event\Events\DisplayingResponse;
use Cube\Web\Http\Configuration\CORSConfiguration;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use React\Http\Message\Response as ReactResponse;

use function Cube\isDebug;

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

    public static function fromContent(int $statusCode, mixed $content = null): static
    {
        if (is_null($content) || is_string($content))
            return new static($statusCode, $content);

        return static::json($content, $statusCode);
    }

    protected static function redirect(int $statusCode, string $location): static
    {
        return (new static($statusCode))->withHeaders(['Location' => $location]);
    }

    // Generic functions by Http Response Code

    public static function continue(mixed $content = null): static
    {
        return static::fromContent(StatusCode::CONTINUE, $content);
    }

    public static function switchingProtocols(mixed $content = null): static
    {
        return static::fromContent(StatusCode::SWITCHING_PROTOCOLS, $content);
    }

    public static function processing(mixed $content = null): static
    {
        return static::fromContent(StatusCode::PROCESSING, $content);
    }

    public static function earlyHints(mixed $content = null): static
    {
        return static::fromContent(StatusCode::EARLY_HINTS, $content);
    }

    public static function ok(mixed $content = null): static
    {
        return static::fromContent(StatusCode::OK, $content);
    }

    public static function created(mixed $content = null): static
    {
        return static::fromContent(StatusCode::CREATED, $content);
    }

    public static function accepted(mixed $content = null): static
    {
        return static::fromContent(StatusCode::ACCEPTED, $content);
    }

    public static function nonAuthoritativeInformation(mixed $content = null): static
    {
        return static::fromContent(StatusCode::NON_AUTHORITATIVE_INFORMATION, $content);
    }

    public static function noContent(): static
    {
        return new static(StatusCode::NO_CONTENT);
    }

    public static function resetContent(mixed $content = null): static
    {
        return static::fromContent(StatusCode::RESET_CONTENT, $content);
    }

    public static function partialContent(mixed $content = null): static
    {
        return static::fromContent(StatusCode::PARTIAL_CONTENT, $content);
    }

    public static function multiStatus(mixed $content = null): static
    {
        return static::fromContent(StatusCode::MULTI_STATUS, $content);
    }

    public static function alreadyReported(mixed $content = null): static
    {
        return static::fromContent(StatusCode::ALREADY_REPORTED, $content);
    }

    public static function imUsed(mixed $content = null): static
    {
        return static::fromContent(StatusCode::IM_USED, $content);
    }

    public static function multipleChoices(mixed $content = null): static
    {
        return static::fromContent(StatusCode::MULTIPLE_CHOICES, $content);
    }

    public static function movedPermanently(string $location): static
    {
        return static::redirect(StatusCode::MOVED_PERMANENTLY, $location);
    }

    public static function found(string $location): static
    {
        return static::redirect(StatusCode::FOUND, $location);
    }

    public static function seeOther(string $location): static
    {
        return static::redirect(StatusCode::SEE_OTHER, $location);
    }

    public static function notModified(mixed $content = null): static
    {
        return static::fromContent(StatusCode::NOT_MODIFIED, $content);
    }

    public static function useProxy(mixed $content = null): static
    {
        return static::fromContent(StatusCode::USE_PROXY, $content);
    }

    public static function unused(mixed $content = null): static
    {
        return static::fromContent(StatusCode::UNUSED, $content);
    }

    public static function temporaryRedirect(string $location): static
    {
        return static::redirect(StatusCode::TEMPORARY_REDIRECT, $location);
    }

    public static function permanentRedirect(string $location): static
    {
        return static::redirect(StatusCode::PERMANENT_REDIRECT, $location);
    }

    public static function badRequest(mixed $content = null): static
    {
        return static::fromContent(StatusCode::BAD_REQUEST, $content);
    }

    public static function unauthorized(mixed $content = null): static
    {
        return static::fromContent(StatusCode::UNAUTHORIZED, $content);
    }

    public static function paymentRequired(mixed $content = null): static
    {
        return static::fromContent(StatusCode::PAYMENT_REQUIRED, $content);
    }

    public static function forbidden(mixed $content = null): static
    {
        return static::fromContent(StatusCode::FORBIDDEN, $content);
    }

    public static function notFound(mixed $content = null): static
    {
        return static::fromContent(StatusCode::NOT_FOUND, $content);
    }

    public static function methodNotAllowed(mixed $content = null): static
    {
        return static::fromContent(StatusCode::METHOD_NOT_ALLOWED, $content);
    }

    public static function notAcceptable(mixed $content = null): static
    {
        return static::fromContent(StatusCode::NOT_ACCEPTABLE, $content);
    }

    public static function proxyAuthenticationRequired(mixed $content = null): static
    {
        return static::fromContent(StatusCode::PROXY_AUTHENTICATION_REQUIRED, $content);
    }

    public static function requestTimeout(mixed $content = null): static
    {
        return static::fromContent(StatusCode::REQUEST_TIMEOUT, $content);
    }

    public static function conflict(mixed $content = null): static
    {
        return static::fromContent(StatusCode::CONFLICT, $content);
    }

    public static function gone(mixed $content = null): static
    {
        return static::fromContent(StatusCode::GONE, $content);
    }

    public static function lengthRequired(mixed $content = null): static
    {
        return static::fromContent(StatusCode::LENGTH_REQUIRED, $content);
    }

    public static function preconditionFailed(mixed $content = null): static
    {
        return static::fromContent(StatusCode::PRECONDITION_FAILED, $content);
    }

    public static function contentTooLarge(mixed $content = null): static
    {
        return static::fromContent(StatusCode::CONTENT_TOO_LARGE, $content);
    }

    public static function uriTooLong(mixed $content = null): static
    {
        return static::fromContent(StatusCode::URI_TOO_LONG, $content);
    }

    public static function unsupportedMediaType(mixed $content = null): static
    {
        return static::fromContent(StatusCode::UNSUPPORTED_MEDIA_TYPE, $content);
    }

    public static function rangeNotSatisfiable(mixed $content = null): static
    {
        return static::fromContent(StatusCode::RANGE_NOT_SATISFIABLE, $content);
    }

    public static function expectationFailed(mixed $content = null): static
    {
        return static::fromContent(StatusCode::EXPECTATION_FAILED, $content);
    }

    public static function imATeapot(mixed $content = null): static
    {
        return static::fromContent(StatusCode::IM_A_TEAPOT, $content);
    }

    public static function misdirectedRequest(mixed $content = null): static
    {
        return static::fromContent(StatusCode::MISDIRECTED_REQUEST, $content);
    }

    public static function unprocessableContent(mixed $content = null): static
    {
        return static::fromContent(StatusCode::UNPROCESSABLE_CONTENT, $content);
    }

    public static function locked(mixed $content = null): static
    {
        return static::fromContent(StatusCode::LOCKED, $content);
    }

    public static function failedDependency(mixed $content = null): static
    {
        return static::fromContent(StatusCode::FAILED_DEPENDENCY, $content);
    }

    public static function tooEarly(mixed $content = null): static
    {
        return static::fromContent(StatusCode::TOO_EARLY, $content);
    }

    public static function upgradeRequired(mixed $content = null): static
    {
        return static::fromContent(StatusCode::UPGRADE_REQUIRED, $content);
    }

    public static function preconditionRequired(mixed $content = null): static
    {
        return static::fromContent(StatusCode::PRECONDITION_REQUIRED, $content);
    }

    public static function tooManyRequests(mixed $content = null): static
    {
        return static::fromContent(StatusCode::TOO_MANY_REQUESTS, $content);
    }

    public static function requestHeaderFieldsTooLarge(mixed $content = null): static
    {
        return static::fromContent(StatusCode::REQUEST_HEADER_FIELDS_TOO_LARGE, $content);
    }

    public static function unavailableForLegalReasons(mixed $content = null): static
    {
        return static::fromContent(StatusCode::UNAVAILABLE_FOR_LEGAL_REASONS, $content);
    }

    public static function internalServerError(mixed $content = null): static
    {
        return static::fromContent(StatusCode::INTERNAL_SERVER_ERROR, $content);
    }

    public static function notImplemented(mixed $content = null): static
    {
        return static::fromContent(StatusCode::NOT_IMPLEMENTED, $content);
    }

    public static function badGateway(mixed $content = null): static
    {
        return static::fromContent(StatusCode::BAD_GATEWAY, $content);
    }

    public static function serviceUnavailable(mixed $content = null): static
    {
        return static::fromContent(StatusCode::SERVICE_UNAVAILABLE, $content);
    }

    public static function gatewayTimeout(mixed $content = null): static
    {
        return static::fromContent(StatusCode::GATEWAY_TIMEOUT, $content);
    }

    public static function httpVersionNotSupported(mixed $content = null): static
    {
        return static::fromContent(StatusCode::HTTP_VERSION_NOT_SUPPORTED, $content);
    }

    public static function variantAlsoNegotiates(mixed $content = null): static
    {
        return static::fromContent(StatusCode::VARIANT_ALSO_NEGOTIATES, $content);
    }

    public static function insufficientStorage(mixed $content = null): static
    {
        return static::fromContent(StatusCode::INSUFFICIENT_STORAGE, $content);
    }

    public static function loopDetected(mixed $content = null): static
    {
        return static::fromContent(StatusCode::LOOP_DETECTED, $content);
    }

    public static function notExtended(mixed $content = null): static
    {
        return static::fromContent(StatusCode::NOT_EXTENDED, $content);
    }

    public static function networkAuthenticationRequired(mixed $content = null): static
    {
        return static::fromContent(StatusCode::NETWORK_AUTHENTICATION_REQUIRED, $content);
    }

    // Generic Response by Content Type

    public static function file(string $path, int $code = StatusCode::OK, ?string $attachmentFile = null): static
    {
        $response = (new static($code))
            ->withResponseCallback(function () use (&$path) { readfile($path); })
            ->setHeader('Content-Type', FileMIMETypes::getFileMIMEType($attachmentFile ?? $path))
            ->setHeader('X-Content-Type-Options', 'nosniff')
        ;

        if ($attachmentFile) {
            $response->setHeader('Content-Type', 'application/octet-stream');
            $response->setHeader('Content-Disposition', self::attachmentDisposition(basename($attachmentFile)));
        }

        return $response;
    }

    // RFC 6266 : a quoted ASCII fallback for old clients, then the exact UTF-8 name
    protected static function attachmentDisposition(string $filename): string
    {
        $fallback = preg_replace('/[^\x20-\x7E]|["\\\\]/', '_', $filename);

        return sprintf('attachment; filename="%s"; filename*=UTF-8\'\'%s', $fallback, rawurlencode($filename));
    }

    public static function download(string $path, int $code = StatusCode::OK, ?string $attachmentFile = null): static
    {
        return static::file($path, $code, $attachmentFile ?? basename($path));
    }

    protected static function toJsonValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof Model => $value->toArray(),
            $value instanceof Bunch => self::toJsonValue($value->toArray()),
            is_array($value) => array_map(self::toJsonValue(...), $value),
            default => $value,
        };
    }

    public static function json(mixed $value, int $code = StatusCode::OK): static
    {
        return new static(
            $code,
            json_encode(self::toJsonValue($value), JSON_THROW_ON_ERROR),
            ['Content-Type' => 'application/json']
        );
    }

    public static function text(string $value, int $code = StatusCode::OK): static
    {
        return new static(
            $code,
            $value,
            ['Content-Type' => 'text/plain']
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

    public static function fromThrowable(\Throwable $thrown): static
    {
        $message = 'Internal Server Error';

        if (isDebug()) {
            $message .= "\n\n".$thrown->getMessage();
            $message .= "\n".$thrown->getTraceAsString();
        }

        return static::text($message, StatusCode::INTERNAL_SERVER_ERROR);
    }

    public function logSelf(?LoggerInterface $logger = null): static
    {
        $logger ??= Logger::getInstance();
        $logger->log('info', '{code} {content-type}', [
            'code' => $this->getStatusCode(),
            'content-type' => $this->getHeader('content-type') ?? 'unknown mime type',
        ]);

        return $this;
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

    public function withCORSHeaders(Request $request, ?array $allowedMethods=null, ?CORSConfiguration $configuration=null): static
    {
        $this->corsDefined = true;

        $configuration ??= CORSConfiguration::resolve();
        $anyOrigin = $configuration->allowsAnyOrigin();

        // The answer depends on the Origin header : caches must not serve it to another origin
        if (!$anyOrigin && count($configuration->allowedOrigins))
            $this->setHeader('Vary', Bunch::fromExplode(',', (string) $this->getHeader('Vary'))
                ->map(trim(...))
                ->filter(keepValues: true)
                ->push('Origin')
                ->uniques()
                ->join(', '));

        if (!$origin = $configuration->getAllowedOrigin($request->getHeader('origin')))
            return $this;

        $this->withHeaders([
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => $allowedMethods ? join(", ", $allowedMethods) : "*",
            'Access-Control-Allow-Headers' => $configuration->allowHeaders,
            'Access-Control-Max-Age' => (string) $configuration->maxAge,
        ]);

        if ($configuration->allowCredentials && !$anyOrigin)
            $this->setHeader('Access-Control-Allow-Credentials', 'true');

        return $this;
    }

    /** @param array<string,string> $headers */
    public function withDefaultHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            if (null === $this->getHeader($name))
                $this->setHeader($name, $value);
        }

        return $this;
    }

    public function withDefaultCORSHeaders(Request $request): static
    {
        if ($this->corsDefined)
            return $this;

        return $this->withCORSHeaders($request);
    }

    public function display(bool $sendHeaders = true): static
    {
        if (!(new DisplayingResponse($this))->dispatch())
            return $this;

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
        return $this;
    }

    public function toPsrResponse(): ResponseInterface
    {
        return new ReactResponse($this->statusCode, $this->headers, $this->getBody());
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
