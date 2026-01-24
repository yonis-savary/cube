<?php

namespace Tests\Integration;

use Cube\Test\CubeTestCase;
use Cube\Utils\File;

/**
 * @internal
 */
class UploadTest extends CubeTestCase
{
    public function testUpload()
    {
        $this->post('/documents')
            ->assertUnprocessableContent()
        ;

        $tooBigUpload = $this->makeFakeUploadFromContent('to-upload', json_encode(['key' => str_repeat('0', File::MEGABYTES)]));
        $this->post('/documents', uploads: [$tooBigUpload])
            ->assertUnprocessableContent()
        ;

        $correctUpload = $this->makeFakeUploadFromContent('to-upload', json_encode(['Hello' => 'Goodbye']));
        $documentName
            = $this->post('/documents', uploads: [$correctUpload])
                ->assertCreated()
                ->assertIsJson()
                ->json()
        ;

        $this->assertIsString($documentName);
        $this->assertStringStartsWith('file-', $documentName);
        $this->assertStringEndsWith('.json', $documentName);

        $document = $this->get("/documents/{$documentName}")
            ->assertOk()
            ->json()
        ;

        $this->assertEquals(['Hello' => 'Goodbye'], $document);
    }
}
