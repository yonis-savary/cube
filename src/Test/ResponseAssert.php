<?php

namespace Cube\Test;

use Cube\Http\Response;
use Cube\Http\StatusCode;
use Cube\Logger\Logger;
use PHPUnit\Framework\Assert;

class ResponseAssert extends Assert
{
    public function __construct(
        protected Response $response
    ) {}

    public function body(): string
    {
        return $this->response->getBody();
    }

    public function json(): mixed
    {
        return json_decode($this->body(), JSON_THROW_ON_ERROR);
    }

    public function assertJsonContent(mixed $expectedContent): self
    {
        self::assertEquals($expectedContent, $this->json());

        return $this;
    }

    public function assertResponseCode(int $expectedCode): self
    {
        if ($expectedCode != $this->response->getStatusCode()) {
            Logger::getInstance()->error('Failed asserting {actuel} response code matches expected {expected}', [
                'actuel' => $this->response->getStatusCode(),
                'expected' => $expectedCode,
            ]);
            Logger::getInstance()->error(print_r($this->response->getHeaders(), true));
            Logger::getInstance()->error(print_r($this->body(), true));
        }

        self::assertEquals($expectedCode, $this->response->getStatusCode());

        return $this;
    }

    public function assertHeaderEqual(string $header, string $expectedContent): self
    {
        self::assertEquals($expectedContent, $this->response->getHeader($header));

        return $this;
    }

    public function assertIsJson(): self
    {
        self::assertTrue($this->response->isJSON());

        return $this;
    }

    public function assertContinue(): self
    {
        return $this->assertResponseCode(StatusCode::CONTINUE);
    }

    public function assertSwitchingProtocols(): self
    {
        return $this->assertResponseCode(StatusCode::SWITCHING_PROTOCOLS);
    }

    public function assertProcessing(): self
    {
        return $this->assertResponseCode(StatusCode::PROCESSING);
    }

    public function assertEarlyHints(): self
    {
        return $this->assertResponseCode(StatusCode::EARLY_HINTS);
    }

    public function assertOk(): self
    {
        return $this->assertResponseCode(StatusCode::OK);
    }

    public function assertCreated(): self
    {
        return $this->assertResponseCode(StatusCode::CREATED);
    }

    public function assertAccepted(): self
    {
        return $this->assertResponseCode(StatusCode::ACCEPTED);
    }

    public function assertNonAuthoritativeInformation(): self
    {
        return $this->assertResponseCode(StatusCode::NON_AUTHORITATIVE_INFORMATION);
    }

    public function assertNoContent(): self
    {
        return $this->assertResponseCode(StatusCode::NO_CONTENT);
    }

    public function assertResetContent(): self
    {
        return $this->assertResponseCode(StatusCode::RESET_CONTENT);
    }

    public function assertPatientContent(): self
    {
        return $this->assertResponseCode(StatusCode::PARTIAL_CONTENT);
    }

    public function assertMultiStatus(): self
    {
        return $this->assertResponseCode(StatusCode::MULTI_STATUS);
    }

    public function assertAlreadyReported(): self
    {
        return $this->assertResponseCode(StatusCode::ALREADY_REPORTED);
    }

    public function assertImUsed(): self
    {
        return $this->assertResponseCode(StatusCode::IM_USED);
    }

    public function assertMultipleChoices(): self
    {
        return $this->assertResponseCode(StatusCode::MULTIPLE_CHOICES);
    }

    public function assertMovedPermanently(): self
    {
        return $this->assertResponseCode(StatusCode::MOVED_PERMANENTLY);
    }

    public function assertFound(): self
    {
        return $this->assertResponseCode(StatusCode::FOUND);
    }

    public function assertSeeOther(): self
    {
        return $this->assertResponseCode(StatusCode::SEE_OTHER);
    }

    public function assertNotModified(): self
    {
        return $this->assertResponseCode(StatusCode::NOT_MODIFIED);
    }

    public function assertUseProxy(): self
    {
        return $this->assertResponseCode(StatusCode::USE_PROXY);
    }

    public function assertUnused(): self
    {
        return $this->assertResponseCode(StatusCode::UNUSED);
    }

    public function assertTemporaryRedirect(): self
    {
        return $this->assertResponseCode(StatusCode::TEMPORARY_REDIRECT);
    }

    public function assertPermanentRedirect(): self
    {
        return $this->assertResponseCode(StatusCode::PERMANENT_REDIRECT);
    }

    public function assertBadRequest(): self
    {
        return $this->assertResponseCode(StatusCode::BAD_REQUEST);
    }

    public function assertUnauthorized(): self
    {
        return $this->assertResponseCode(StatusCode::UNAUTHORIZED);
    }

    public function assertPaymentRequired(): self
    {
        return $this->assertResponseCode(StatusCode::PAYMENT_REQUIRED);
    }

    public function assertForbidden(): self
    {
        return $this->assertResponseCode(StatusCode::FORBIDDEN);
    }

    public function assertNotFound(): self
    {
        return $this->assertResponseCode(StatusCode::NOT_FOUND);
    }

    public function assertMethodNotAllowed(): self
    {
        return $this->assertResponseCode(StatusCode::METHOD_NOT_ALLOWED);
    }

    public function assertNotAcceptable(): self
    {
        return $this->assertResponseCode(StatusCode::NOT_ACCEPTABLE);
    }

    public function assertProxyAuthenticationRequired(): self
    {
        return $this->assertResponseCode(StatusCode::PROXY_AUTHENTICATION_REQUIRED);
    }

    public function assertRequestTimeout(): self
    {
        return $this->assertResponseCode(StatusCode::REQUEST_TIMEOUT);
    }

    public function assertConflict(): self
    {
        return $this->assertResponseCode(StatusCode::CONFLICT);
    }

    public function assertGone(): self
    {
        return $this->assertResponseCode(StatusCode::GONE);
    }

    public function assertLengthRequired(): self
    {
        return $this->assertResponseCode(StatusCode::LENGTH_REQUIRED);
    }

    public function assertPreconditionFailed(): self
    {
        return $this->assertResponseCode(StatusCode::PRECONDITION_FAILED);
    }

    public function assertContentTooLarge(): self
    {
        return $this->assertResponseCode(StatusCode::CONTENT_TOO_LARGE);
    }

    public function assertURITooLong(): self
    {
        return $this->assertResponseCode(StatusCode::URI_TOO_LONG);
    }

    public function assertUnsupportedMediaType(): self
    {
        return $this->assertResponseCode(StatusCode::UNSUPPORTED_MEDIA_TYPE);
    }

    public function assertRangeNotSatisfiable(): self
    {
        return $this->assertResponseCode(StatusCode::RANGE_NOT_SATISFIABLE);
    }

    public function assertExpectationFailed(): self
    {
        return $this->assertResponseCode(StatusCode::EXPECTATION_FAILED);
    }

    public function assertImATeapot(): self
    {
        return $this->assertResponseCode(StatusCode::IM_A_TEAPOT);
    }

    public function assertMisdirectedRequest(): self
    {
        return $this->assertResponseCode(StatusCode::MISDIRECTED_REQUEST);
    }

    public function assertUnprocessableContent(): self
    {
        return $this->assertResponseCode(StatusCode::UNPROCESSABLE_CONTENT);
    }

    public function assertLocked(): self
    {
        return $this->assertResponseCode(StatusCode::LOCKED);
    }

    public function assertFailedDependency(): self
    {
        return $this->assertResponseCode(StatusCode::FAILED_DEPENDENCY);
    }

    public function assertTooEarly(): self
    {
        return $this->assertResponseCode(StatusCode::TOO_EARLY);
    }

    public function assertUpgradeRequired(): self
    {
        return $this->assertResponseCode(StatusCode::UPGRADE_REQUIRED);
    }

    public function assertPreconditionRequired(): self
    {
        return $this->assertResponseCode(StatusCode::PRECONDITION_REQUIRED);
    }

    public function assertTooManyRequests(): self
    {
        return $this->assertResponseCode(StatusCode::TOO_MANY_REQUESTS);
    }

    public function assertRequestHeaderFieldsTooLarge(): self
    {
        return $this->assertResponseCode(StatusCode::REQUEST_HEADER_FIELDS_TOO_LARGE);
    }

    public function assertUnavailableForLegalReasons(): self
    {
        return $this->assertResponseCode(StatusCode::UNAVAILABLE_FOR_LEGAL_REASONS);
    }

    public function assertInternalServerError(): self
    {
        return $this->assertResponseCode(StatusCode::INTERNAL_SERVER_ERROR);
    }

    public function assertNotImplemented(): self
    {
        return $this->assertResponseCode(StatusCode::NOT_IMPLEMENTED);
    }

    public function assertBadGateway(): self
    {
        return $this->assertResponseCode(StatusCode::BAD_GATEWAY);
    }

    public function assertServiceUnavailable(): self
    {
        return $this->assertResponseCode(StatusCode::SERVICE_UNAVAILABLE);
    }

    public function assertGatewayTimeout(): self
    {
        return $this->assertResponseCode(StatusCode::GATEWAY_TIMEOUT);
    }

    public function assertHttpVersionNotSupported(): self
    {
        return $this->assertResponseCode(StatusCode::HTTP_VERSION_NOT_SUPPORTED);
    }

    public function assertVariantAlsoNegociates(): self
    {
        return $this->assertResponseCode(StatusCode::VARIANT_ALSO_NEGOTIATES);
    }

    public function assertInsufficientStorage(): self
    {
        return $this->assertResponseCode(StatusCode::INSUFFICIENT_STORAGE);
    }

    public function assertLoopDetected(): self
    {
        return $this->assertResponseCode(StatusCode::LOOP_DETECTED);
    }

    public function assertNotExtended(): self
    {
        return $this->assertResponseCode(StatusCode::NOT_EXTENDED);
    }

    public function assertNetworkAuthenticationRequired(): self
    {
        return $this->assertResponseCode(StatusCode::NETWORK_AUTHENTICATION_REQUIRED);
    }
}
