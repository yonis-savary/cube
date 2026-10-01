<?php 

namespace Cube\Tests\Units\Web;

use Cube\Env\Logger\Logger;
use Cube\Env\Storage;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Web\Helpers\LargeUpload\LargeUpload;
use Cube\Web\Helpers\LargeUpload\LargeUploadManager;
use Cube\Web\Helpers\LargeUpload\LargeUploadManagerConfiguration;
use PHPUnit\Framework\TestCase;

class LargeUploadTest extends TestCase
{
    use HasTemporaryStorage;

    public function testLargeUploadChunksAndWrap() {
        $textFilePath = Storage::getInstance()->path(uniqid('randomTextFile-'));

        $randomTextFileWrite = fopen($textFilePath, "w");
        // Generate a 20 Mb file !
        for ($i=0; $i<20; $i++) {
            fwrite($randomTextFileWrite, bin2hex(random_bytes(512 * 1024)));
        }
        fclose($randomTextFileWrite);


        $largeUpload = new LargeUpload(
            uniqid("randomLargeUpload-"),
            Storage::getInstance()->child(uniqid('largeUploads-'))
        );


        $randomTextFileRead = fopen($textFilePath, "r");
        $i = 0;
        while ($data = fgets($randomTextFileRead, 1024 * 1024)) {
            $largeUpload->addChunk($i, $data);
            $i++;
        }
        fclose($randomTextFileRead);


        $largeUploadResult = $largeUpload->wrap(Storage::getInstance(), false);

        $this->assertEquals(
            filesize($textFilePath),
            filesize($largeUploadResult)
        );

        $this->assertEquals(
            md5_file($textFilePath),
            md5_file($largeUploadResult)
        );

    }

    /** An identifier holding `..` resolved outside the upload directory, so delete() wiped any directory. */
    public function testAnIdentifierCannotEscapeTheUploadDirectory()
    {
        $this->setUpTemporaryStorage('large-upload-test-');
        $victim = $this->storage->child('victim');
        $victim->write('important.txt', 'keep me');

        $manager = new LargeUploadManager(new LargeUploadManagerConfiguration($this->storage->child('uploads')->getRoot()));

        try {
            $this->assertNull($manager->find('../victim'));
            $this->assertFalse($manager->delete('../victim'));
            $this->assertTrue($victim->isFile('important.txt'));
        } finally {
            $this->tearDownTemporaryStorage();
        }
    }
}
