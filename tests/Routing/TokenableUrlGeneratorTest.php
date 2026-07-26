<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Routing;

use LauLamanApps\Tokenable\Routing\TokenableUrlGenerator;
use LauLamanApps\Tokenable\Tests\CreatesTokenizer;
use LauLamanApps\Tokenable\Tests\Fixtures\FooController;
use LauLamanApps\Tokenable\Tests\Fixtures\FooEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class TokenableUrlGeneratorTest extends TestCase
{
    use CreatesTokenizer;

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/tokenable-test-'.uniqid('', true);
        mkdir($this->cacheDir);
    }

    protected function tearDown(): void
    {
        @unlink($this->cacheDir.'/tokenable_routes.php');
        @rmdir($this->cacheDir);
    }

    public function testEncodesIntParameterIntoToken(): void
    {
        $captured = $this->generate(['foo' => 42]);

        self::assertSame($this->createTokenizer()->encode(FooEntity::class, 42), $captured['foo']);
    }

    public function testEncodesEntityParameterIntoToken(): void
    {
        $captured = $this->generate(['foo' => new FooEntity(42)]);

        self::assertSame($this->createTokenizer()->encode(FooEntity::class, 42), $captured['foo']);
    }

    public function testLeavesUnmappedParametersUntouched(): void
    {
        $captured = $this->generate(['foo' => 42], routeName: 'unmapped_route');

        self::assertSame(42, $captured['foo']);
    }

    public function testLeavesAlreadyEncodedTokenStringUntouched(): void
    {
        $token = $this->createTokenizer()->encode(FooEntity::class, 42);

        $captured = $this->generate(['foo' => $token]);

        // A string value is already a token: it must pass through unchanged.
        self::assertSame($token, $captured['foo']);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed> the parameters the decorated (inner) router actually received
     */
    private function generate(array $parameters, string $routeName = 'foo_show'): array
    {
        $collection = new RouteCollection();
        $collection->add('foo_show', new Route('/foo/{foo}', ['_controller' => FooController::class]));

        $captured = [];
        $inner = $this->createMock(RouterInterface::class);
        $inner->method('getRouteCollection')->willReturn($collection);
        $inner->method('generate')->willReturnCallback(
            function (string $name, array $params = []) use (&$captured): string {
                $captured = $params;

                return '/generated';
            },
        );

        $generator = new TokenableUrlGenerator(
            $inner,
            $this->createTokenizer([FooEntity::class]),
            $this->cacheDir,
        );

        $generator->generate($routeName, $parameters);

        return $captured;
    }
}
