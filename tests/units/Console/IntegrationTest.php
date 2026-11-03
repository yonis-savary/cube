<?php

namespace Cube\Tests\Units\Console;

use Cube\Console\Args;
use Cube\Console\Commands\DataToObject\Integration;
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
class IntegrationTest extends TestCase
{
    protected const FIXTURE = 'tests/units/Data/DataToObject/Generator/Fixtures/openapi-billing.json';

    protected ?string $receivedAuthorization = null;

    protected function setUp(): void
    {
        $document = file_get_contents(Path::relative(self::FIXTURE));

        MockServers::getInstance()->set(HttpClient::class, HttpMockServer::fromArray([
            '/openapi.json' => function (Request $request) use ($document) {
                $this->receivedAuthorization = $request->getHeader('Authorization');

                return Response::ok($document);
            },
            '/index.html' => Response::ok('<html></html>'),
        ]));
    }

    protected function tearDown(): void
    {
        MockServers::removeInstance();
    }

    /**
     * @param string[] $argv
     *
     * @return array{int,string} exit code, output
     */
    protected function callCommand(array $argv): array
    {
        ob_start();
        $code = Integration::call(Args::fromArgv($argv));

        return [$code, ob_get_clean()];
    }

    public function testADocumentIsFetchedFromAUrlWithTheGivenHeaders()
    {
        // An endpoint matching nothing stops the command right after the document is read, before anything is written
        [$code, $output] = $this->callCommand(['https://billing.example.com/openapi.json', '--header=Authorization: Bearer secret', '--endpoint=nothing']);

        $this->assertEquals(1, $code);
        $this->assertStringContainsString('No operation matches [nothing]', $output);
        $this->assertEquals('Bearer secret', $this->receivedAuthorization);
    }

    public function testAnUnsuccessfulAnswerStopsTheCommand()
    {
        [$code, $output] = $this->callCommand(['https://billing.example.com/missing.json']);

        $this->assertEquals(1, $code);
        $this->assertStringContainsString('Could not fetch https://billing.example.com/missing.json : the server answered 404', $output);
    }

    public function testAnAnswerThatIsNotJsonStopsTheCommand()
    {
        [$code, $output] = $this->callCommand(['https://billing.example.com/index.html']);

        $this->assertEquals(1, $code);
        $this->assertStringContainsString('https://billing.example.com/index.html is not a valid JSON document', $output);
    }

    public function testAHeaderNeedsAUrl()
    {
        [$code, $output] = $this->callCommand([self::FIXTURE, '--header=Authorization: Bearer secret']);

        $this->assertEquals(1, $code);
        $this->assertStringContainsString('--header only applies when the document is fetched from a URL', $output);
    }

    public function testAHeaderWithoutColonIsRejected()
    {
        [$code, $output] = $this->callCommand(['https://billing.example.com/openapi.json', '--header=Authorization']);

        $this->assertEquals(1, $code);
        $this->assertStringContainsString('Header [Authorization] is not of the form "Name: value"', $output);
    }
}
