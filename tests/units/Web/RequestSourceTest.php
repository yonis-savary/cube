<?php

namespace Cube\Tests\Units\Web;

use Cube\Web\Http\Request;
use Cube\Web\Http\RequestSource;
use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Rules\Rule;
use Cube\Web\Http\Rules\UploadRule;
use Cube\Web\Http\Upload;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class RequestSourceTest extends TestCase
{
    public function testTheQueryStringWinsOverTheBodyByDefault()
    {
        $request = $this->request(RequestSource::Merged, ['amount' => Param::integer()], '/pay?amount=999', '{"amount": 10}');

        $this->assertEquals(999, $request->validated('amount'));
    }

    /** With the merged source, a query parameter replaced the field a client signed in its body. */
    public function testABodySourceIgnoresTheQueryString()
    {
        $request = $this->request(RequestSource::Body, ['amount' => Param::integer()], '/pay?amount=999', '{"amount": 10}');

        $this->assertEquals(10, $request->validated('amount'));
    }

    public function testABodySourceValidatesAJsonListAsIs()
    {
        $request = $this->request(RequestSource::Body, Param::array(Param::integer()), '/ids?page=2', '[1, 2, 3]');

        $this->assertTrue($request->isValid());
        $this->assertEquals([1, 2, 3], $request->validated());
    }

    public function testABodySourceValidatesAJsonScalar()
    {
        $request = $this->request(RequestSource::Body, Param::integer(), '/quantity', '42');

        $this->assertEquals(42, $request->validated());
    }

    public function testABodySourceKeepsTheUploadsOfAForm()
    {
        $upload = Upload::fromContent('%PDF-1.4 a document', 'invoice');
        $request = new class('POST', '/invoices', post: ['label' => 'March'], uploads: [$upload]) extends Request {
            public function getValidatedSource(): RequestSource
            {
                return RequestSource::Body;
            }

            public function getRules(): array
            {
                return ['label' => Param::string(), 'invoice' => UploadRule::new()];
            }
        };

        $this->assertEquals('March', $request->validated('label'));
        $this->assertSame($upload, $request->validated('invoice'));
    }

    public function testAKeyCannotBeReadFromAListBody()
    {
        $request = $this->request(RequestSource::Body, Param::array(Param::integer()), '/ids', '[1, 2]');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not an object');

        $request->validated('page');
    }

    public function testARawBodySourceReceivesTheBodyAsSent()
    {
        $request = $this->request(RequestSource::RawBody, UploadRule::fromRawBody()->withMimeType('application/pdf'), '/documents', '%PDF-1.4 a document', 'application/pdf');

        $this->assertInstanceOf(Upload::class, $request->validated());
        $this->assertFalse($this->request(RequestSource::RawBody, UploadRule::fromRawBody(), '/documents', '', 'application/pdf')->isValid());
    }

    public function testARawBodyCanHoldABase64File()
    {
        $request = $this->request(RequestSource::RawBody, UploadRule::fromBase64(), '/avatars', Base64UploadTest::PNG, 'text/plain');

        $this->assertEquals('image/png', $request->validated()->type);
    }

    /**
     * @param array<string,Rule>|Rule $rules
     */
    protected function request(RequestSource $source, array|Rule $rules, string $path, string $body, string $contentType = 'application/json'): Request
    {
        return new class($source, $rules, $path, $body, $contentType) extends Request {
            public function __construct(
                protected RequestSource $source,
                protected array|Rule $rules,
                string $path,
                string $body,
                string $contentType,
            ) {
                parent::__construct('POST', $path, headers: ['content-type' => $contentType], body: $body);
            }

            public function getValidatedSource(): RequestSource
            {
                return $this->source;
            }

            public function getRules(): array|Rule
            {
                return $this->rules;
            }
        };
    }
}
