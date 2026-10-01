<?php

namespace Cube\Tests\Units\Web;

use Cube\Web\Http\Exceptions\InvalidRequestMethodException;
use Cube\Web\Http\Request;
use Cube\Web\Router\Route;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class RouteTest extends TestCase
{
    public function testBuildPath() {
        $route = Route::get("/product/{product}/price/{price}", fn() => null);

        $this->assertEquals("/product/1/price/2", $route->buildPath([1,2]));
    }

    /**
     * A route is stored without its trailing slash so `/products` and `/products/` reach the
     * same callback : trimming one character too many used to eat the last letter of the path.
     */
    public function testATrailingSlashIsTrimmedAndNothingElse()
    {
        $this->assertEquals('/products', (new Route('/products/', fn () => null))->getPath());
        $this->assertEquals('/products', (new Route('/products', fn () => null))->getPath());
        $this->assertEquals('/', (new Route('/', fn () => null))->getPath());
    }

    public function testARouteDeclaredWithATrailingSlashStillMatches()
    {
        $route = Route::get('/products/', fn () => null);

        $this->assertTrue($route->match(new Request('GET', '/products')));
    }

    public function testOptionsFactoryUsesTheHttpMethodName()
    {
        $this->assertEquals(['OPTIONS'], Route::options('/', fn () => null)->getMethods());
    }

    public function testEveryFactoryCarriesItsMethod()
    {
        $factories = [
            'get' => 'GET',
            'post' => 'POST',
            'put' => 'PUT',
            'patch' => 'PATCH',
            'delete' => 'DELETE',
        ];

        foreach ($factories as $factory => $method) {
            $this->assertEquals([$method], Route::{$factory}('/', fn () => null)->getMethods());
        }

        $this->assertEquals([], Route::any('/', fn () => null)->getMethods());
    }

    public function testMatchAnswersABoolean()
    {
        $withSlug = Route::get('/products/{id}', fn () => null);
        $withoutSlug = Route::get('/products', fn () => null);

        $this->assertTrue($withSlug->match(new Request('GET', '/products/12')));
        $this->assertFalse($withSlug->match(new Request('GET', '/orders/12')));
        $this->assertTrue($withoutSlug->match(new Request('GET', '/products')));
        $this->assertFalse($withoutSlug->match(new Request('GET', '/products/12')));
    }

    public function testSlugValuesAreKeyedByName()
    {
        $route = Route::get('/agencies/{agency}/products/{int:product}', fn () => null);
        $request = new Request('GET', '/agencies/lyon/products/12');

        $this->assertTrue($route->match($request));
        $this->assertEquals(['agency' => 'lyon', 'product' => '12'], $request->getSlugValues());
    }

    public function testATypedSlugRefusesTheWrongFormat()
    {
        $route = Route::get('/products/{int:product}', fn () => null);

        $this->assertTrue($route->match(new Request('GET', '/products/12')));
        $this->assertFalse($route->match(new Request('GET', '/products/screen')));
    }

    public function testSlugValuesAreUrlDecoded()
    {
        $route = Route::get('/products/{name}', fn () => null);
        $request = new Request('GET', '/products/office%20chair');

        $route->match($request);

        $this->assertEquals(['name' => 'office chair'], $request->getSlugValues());
    }

    public function testAPreviousMatchDoesNotLeaveItsSlugsBehind()
    {
        $withSlug = Route::get('/products/{id}', fn () => null);
        $withoutSlug = Route::get('/orders', fn () => null);

        $request = new Request('GET', '/products/12');
        $withSlug->match($request);
        $this->assertNotEmpty($request->getSlugValues());

        $request->setPath('/orders');
        $withoutSlug->match($request);
        $this->assertEquals([], $request->getSlugValues());
    }

    public function testAMatchingPathWithTheWrongMethodIsReported()
    {
        $route = Route::post('/products', fn () => null);

        $this->expectException(InvalidRequestMethodException::class);

        $route->match(new Request('GET', '/products'));
    }

    public function testSettersDoNotDropWhatTheyAreGiven()
    {
        $route = Route::get('/products', fn () => null);

        $route->setMiddlewares(['App\Middlewares\IsAdmin']);
        $route->setExtras(['label' => 'products']);

        $this->assertEquals(['App\Middlewares\IsAdmin'], $route->getMiddlewares());
        $this->assertEquals(['label' => 'products'], $route->getExtras());
    }

    public function testBuildPathAcceptsFewerParametersThanSlugs()
    {
        $route = Route::get('/product/{product}/price/{price}', fn () => null);

        $this->assertEquals('/product/1/price/', $route->buildPath([1]));
    }

    /** An encoded slash was decoded after matching, so a single slug could carry `../` segments. */
    public function testAnEncodedSlashDoesNotSplitIntoTheSlug()
    {
        $route = Route::get('/uploads/{identifier}', fn () => null);
        $request = new Request('GET', '/uploads/..%2F..%2Fsecrets');

        $this->assertFalse($route->match($request));
        $this->assertStringNotContainsString('/', $request->getSlugValues()['identifier'] ?? '');
    }

    public function testAnAnySlugKeepsAnEncodedSlash()
    {
        $route = Route::get('/files/{any:path}', fn () => null);
        $request = new Request('GET', '/files/reports%2F2026.pdf');

        $this->assertTrue($route->match($request));
        $this->assertEquals('reports/2026.pdf', $request->getSlugValues()['path']);
    }

    /** Static parts of a slugged route were not regex-quoted, so a dot matched any character. */
    public function testADotInAStaticPartIsLiteral()
    {
        $route = Route::get('/api/v1.0/products/{id}', fn () => null);

        $this->assertFalse($route->match(new Request('GET', '/api/v1x0/products/5')));
    }
}
