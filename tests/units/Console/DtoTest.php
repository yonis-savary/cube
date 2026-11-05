<?php

namespace Cube\Tests\Units\Console;

use Cube\Console\Args;
use Cube\Console\Commands\DataToObject\Dto;
use Cube\Core\Autoloader\Applications;
use Cube\Env\Configuration;
use Cube\Tests\Units\Console\Classes\GeneratesIntoATestNamespace;
use Cube\Utils\Path;
use Cube\Web\Http\HttpClient;
use Cube\Web\Http\HttpMockServer;
use Cube\Web\Http\MockServers;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DtoTest extends TestCase
{
    use GeneratesIntoATestNamespace;

    /** The examples of the `Pet` schema of the Swagger Petstore, see Fixtures/openapi-petstore.json */
    protected const PET_SAMPLE = '{"id": 10, "name": "doggie", "category": {"id": 1, "name": "Dogs"}, "photoUrls": ["https://petstore.example.com/doggie.png"], "tags": [{"id": 3, "name": "friendly"}], "status": "available"}';

    protected ?string $receivedAuthorization = null;

    protected function setUp(): void
    {
        MockServers::getInstance()->set(HttpClient::class, HttpMockServer::fromArray([
            '/album.json' => function (Request $request) {
                $this->receivedAuthorization = $request->getHeader('Authorization');

                return Response::json(['title' => 'Blue Train']);
            },
        ]));
    }

    protected function tearDown(): void
    {
        MockServers::removeInstance();
        $this->removeGeneratedDirectory();
    }

    /**
     * @param string[] $argv
     *
     * @return array{int,string} exit code, output
     */
    protected function callCommand(array $argv): array
    {
        ob_start();
        $code = Dto::call(Args::fromArgv($argv));

        return [$code, ob_get_clean()];
    }

    /**
     * An invalid --name stops the command right after the sample is read, before anything is written.
     *
     * @param string[] $argv
     *
     * @return array{int,string} exit code, output
     */
    protected function readSample(array $argv): array
    {
        return $this->callCommand([...$argv, '--name=album']);
    }

    public function testASampleIsReadFromAJsonText()
    {
        [, $output] = $this->readSample(['{"title": "Blue Train"}']);

        $this->assertStringContainsString('--name [album] is not a class name', $output);
    }

    public function testASampleIsReadFromAFile()
    {
        $file = Path::join(sys_get_temp_dir(), 'dto-sample-'.bin2hex(random_bytes(6)).'.json');
        file_put_contents($file, '{"title": "Blue Train"}');

        try {
            [, $output] = $this->readSample([$file]);
        } finally {
            unlink($file);
        }

        $this->assertStringContainsString('--name [album] is not a class name', $output);
    }

    public function testASampleIsFetchedFromAUrlWithTheGivenHeaders()
    {
        [, $output] = $this->readSample(['https://music.example.com/album.json', '--header=Authorization: Bearer secret']);

        $this->assertStringContainsString('--name [album] is not a class name', $output);
        $this->assertEquals('Bearer secret', $this->receivedAuthorization);
    }

    public function testAnArgumentThatIsNeitherAFileAUrlNorJsonIsRejected()
    {
        [$code, $output] = $this->readSample(['album.json']);

        $this->assertEquals(1, $code);
        $this->assertStringContainsString('[album.json] is neither a file, an http(s) URL nor a valid JSON text', $output);
    }

    public function testAListOrAScalarIsRejected()
    {
        [$code, $output] = $this->readSample(['[{"title": "Blue Train"}]']);
        $this->assertEquals(1, $code);
        $this->assertStringContainsString('The sample is a JSON list', $output);

        [$code, $output] = $this->readSample(['12']);
        $this->assertEquals(1, $code);
        $this->assertStringContainsString('holds a JSON scalar', $output);
    }

    public function testASampleGeneratesItsClassesIntoTheNamespace()
    {
        $namespace = $this->generatedNamespace();

        [$code, $output] = $this->callCommand([self::PET_SAMPLE, '--name=Pet', "--namespace={$namespace}"]);

        $this->assertEquals(0, $code, $output);

        $files = $this->writtenFiles($output);
        $this->assertEqualsCanonicalizing([
            Path::join($this->generatedDirectory, 'Pet/Pet.php'),
            Path::join($this->generatedDirectory, 'Pet/Category/Category.php'),
            Path::join($this->generatedDirectory, 'Pet/Tags/Tags.php'),
        ], $files);
        $this->assertGeneratedFilesAreValid($files);

        $pet = "{$namespace}\\Pet\\Pet"::fromData(json_decode(self::PET_SAMPLE, true));
        $this->assertEquals('Dogs', $pet->category->name);
        $this->assertEquals('friendly', $pet->tags[0]->name);
    }

    public function testTheDefaultNamespaceIsTheDataToObjectDirectoryOfTheApplication()
    {
        $this->generatedNamespace();
        mkdir($this->generatedDirectory);

        [$code, $output] = (new Configuration(new Applications($this->generatedDirectory)))
            ->asGlobalInstance(fn () => $this->callCommand(['{"id": 1, "name": "Dogs"}', '--name=Category']))
        ;

        $this->assertEquals(0, $code, $output);
        $this->assertEquals([Path::join($this->generatedDirectory, 'Integration/DataToObject/Category/Category.php')], $this->writtenFiles($output));
    }
}
