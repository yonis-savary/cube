<?php

namespace Cube\Tests\Units\Web;

use Cube\Env\Storage;
use Cube\Web\Http\Rules\UploadRule;
use Cube\Web\Http\Upload;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class Base64UploadTest extends TestCase
{
    // A 1x1 transparent PNG
    public const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testABase64StringBecomesAnUpload()
    {
        $upload = UploadRule::fromBase64()->validate(self::PNG)->getResult();

        $this->assertInstanceOf(Upload::class, $upload);
        $this->assertEquals('image/png', $upload->type);
        $this->assertEquals('png', $upload->extension);
        $this->assertSame(base64_decode(self::PNG), file_get_contents($upload->tempName));
    }

    public function testADataUriBecomesAnUpload()
    {
        $upload = UploadRule::fromBase64()->validate('data:image/png;base64,'.self::PNG)->getResult();

        $this->assertEquals('image/png', $upload->type);
    }

    public function testTheUrlSafeAlphabetWithoutPaddingIsAccepted()
    {
        $urlSafe = rtrim(strtr(self::PNG, '+/', '-_'), '=');

        $this->assertTrue(UploadRule::fromBase64()->validate($urlSafe)->isValid());
    }

    /** A client can name any type in a data URI : only the content tells what the file is. */
    public function testADataUriDeclaringAnotherTypeIsRefused()
    {
        $validation = UploadRule::fromBase64()->validate('data:application/pdf;base64,'.self::PNG);

        $this->assertFalse($validation->isValid());
        $this->assertStringContainsString('declares [application/pdf] but holds [image/png]', json_encode($validation->getErrors(), JSON_UNESCAPED_SLASHES));
    }

    public function testAValueThatIsNoBase64IsRefused()
    {
        $validation = UploadRule::fromBase64()->validate('not base64 at all !');

        $this->assertFalse($validation->isValid());
        $this->assertStringContainsString('must be a base64 encoded file', json_encode($validation->getErrors(), JSON_UNESCAPED_SLASHES));
    }

    public function testTheUsualUploadChecksApply()
    {
        $this->assertTrue(UploadRule::fromBase64()->withMimeType('image/png')->validate(self::PNG)->isValid());
        $this->assertFalse(UploadRule::fromBase64()->withMimeType('application/pdf')->validate(self::PNG)->isValid());
        $this->assertFalse(UploadRule::fromBase64()->withMaxSize(10)->validate(self::PNG)->isValid());
    }

    public function testANullableRuleAcceptsNull()
    {
        $this->assertTrue(UploadRule::fromBase64(nullable: true)->validate(null)->isValid());
        $this->assertFalse(UploadRule::fromBase64()->validate(null)->isValid());
    }

    public function testADecodedUploadCanBeMoved()
    {
        $storage = Storage::getInstance()->child(uniqid('base64-upload-'));
        $upload = UploadRule::fromBase64()->validate(self::PNG)->getResult();

        $path = $upload->move($storage, 'pixel.png');

        $this->assertSame(base64_decode(self::PNG), file_get_contents($path));
        unlink($path);
        rmdir($storage->getRoot());
    }

    public function testARawBodyBecomesAnUpload()
    {
        $upload = UploadRule::fromRawBody()->validate('%PDF-1.4 a document')->getResult();

        $this->assertEquals('application/pdf', $upload->type);
        $this->assertFalse(UploadRule::fromRawBody()->validate('')->isValid());
    }
}
