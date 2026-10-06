<?php

namespace Cube\Tests\Units\Web;

use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Web\Http\Rules\UploadRule;
use Cube\Web\Http\Upload;
use PHPUnit\Framework\TestCase;
use React\Http\Io\BufferedBody;
use React\Http\Io\UploadedFile;

/**
 * @internal
 */
class UploadTest extends TestCase
{
    use HasTemporaryStorage;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('upload-test-');
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryStorage();
    }

    public function testAnUploadDescribesTheFileItCarries()
    {
        $upload = $this->newUpload('invoice.pdf', 'documents');

        $this->assertEquals('invoice.pdf', $upload->filename);
        $this->assertEquals('pdf', $upload->extension);
        $this->assertEquals('documents', $upload->inputName);
        $this->assertEquals(UPLOAD_ERR_OK, $upload->error);
    }

    public function testEveryPhpErrorCodeHasItsOwnMessage()
    {
        $codes = [
            UPLOAD_ERR_OK, UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE, UPLOAD_ERR_PARTIAL,
            UPLOAD_ERR_NO_FILE, UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION,
        ];

        $messages = [];
        foreach ($codes as $code) {
            $messages[] = $this->newUpload('invoice.pdf', 'documents', $code)->getPHPUploadErrorMessage();
        }

        $this->assertCount(count($codes), array_unique($messages));
    }

    public function testAnUnknownErrorCodeStillGetsAMessage()
    {
        $message = $this->newUpload('invoice.pdf', 'documents', 99)->getPHPUploadErrorMessage();

        $this->assertStringContainsString('99', $message);
    }

    public function testMovingAnUploadAnswersItsNewPath()
    {
        $upload = $this->newUpload('invoice.pdf', 'documents');
        $destination = $this->storage->child('moved');

        $newPath = $upload->move($destination, 'invoice.pdf');

        $this->assertEquals($destination->path('invoice.pdf'), $newPath);
        $this->assertEquals('file content', file_get_contents($newPath));
    }

    public function testMovingTwiceAnswersTheSamePath()
    {
        $upload = $this->newUpload('invoice.pdf', 'documents');
        $destination = $this->storage->child('moved');

        $firstPath = $upload->move($destination, 'invoice.pdf');

        $this->assertEquals($firstPath, $upload->move($destination, 'other-name.pdf'));
    }

    public function testAFailedUploadIsNotMoved()
    {
        $upload = $this->newUpload('invoice.pdf', 'documents', UPLOAD_ERR_NO_FILE);

        $this->assertFalse($upload->move($this->storage->child('moved'), 'invoice.pdf'));
    }

    public function testMovingOverAnExistingFileIsRefused()
    {
        $destination = $this->storage->child('moved');
        $destination->write('invoice.pdf', 'already there');

        $upload = $this->newUpload('invoice.pdf', 'documents');

        $this->assertFalse($upload->move($destination, 'invoice.pdf'));
        $this->assertEquals('already there', file_get_contents($destination->path('invoice.pdf')));
    }

    public function testAnUploadRuleRefusesAFailedUpload()
    {
        $rule = new UploadRule();

        $this->assertTrue($rule->validate($this->newUpload('invoice.pdf', 'documents'))->isValid());
        $this->assertFalse($rule->validate($this->newUpload('invoice.pdf', 'documents', UPLOAD_ERR_PARTIAL))->isValid());
    }

    public function testAnUploadRuleRefusesAMissingUpload()
    {
        $this->assertFalse((new UploadRule())->validate(null)->isValid());
        $this->assertTrue((new UploadRule(nullable: true))->validate(null)->isValid());
    }

    public function testAnUploadRuleCanCapTheFileSize()
    {
        $upload = $this->newUpload('invoice.pdf', 'documents');

        $this->assertTrue((new UploadRule())->withMaxSize(Upload::MB)->validate($upload)->isValid());
        $this->assertFalse((new UploadRule())->withMaxSize(2)->validate($upload)->isValid());
    }

    public function testAPsrUploadedFileIsWrittenToATemporaryFile()
    {
        $upload = Upload::fromPsrUploadedFile(
            new UploadedFile(new BufferedBody('invoice content'), 15, UPLOAD_ERR_OK, 'invoice.pdf', 'application/pdf'),
            'documents'
        );

        try {
            $this->assertEquals('invoice.pdf', $upload->filename);
            $this->assertEquals('pdf', $upload->extension);
            $this->assertEquals('application/pdf', $upload->type);
            $this->assertEquals(15, $upload->size);
            $this->assertEquals('documents', $upload->inputName);
            $this->assertEquals('invoice content', file_get_contents($upload->tempName));
        } finally {
            unlink($upload->tempName);
        }
    }

    public function testAFailedPsrUploadedFileHasNoTemporaryFile()
    {
        $upload = Upload::fromPsrUploadedFile(new UploadedFile(new BufferedBody(''), 0, UPLOAD_ERR_NO_FILE, null, null), 'documents');

        $this->assertEquals(UPLOAD_ERR_NO_FILE, $upload->error);
        $this->assertEquals('', $upload->tempName);
    }

    public function testAPsrUploadedFileCanBeMoved()
    {
        $upload = Upload::fromPsrUploadedFile(new UploadedFile(new BufferedBody('invoice content'), 15, UPLOAD_ERR_OK, 'invoice.pdf', 'application/pdf'));

        $newPath = $upload->move($this->storage->child('moved'), 'invoice.pdf');

        $this->assertEquals('invoice content', file_get_contents($newPath));
    }

    protected function newUpload(string $filename, string $inputName, int $error = UPLOAD_ERR_OK): Upload
    {
        $tempName = $this->storage->path(uniqid('tmp-'));
        file_put_contents($tempName, 'file content');

        return new Upload([
            'name' => $filename,
            'type' => 'application/pdf',
            'tmp_name' => $tempName,
            'error' => $error,
            'size' => filesize($tempName),
        ], $inputName);
    }
}
