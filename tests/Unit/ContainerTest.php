<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Container;
use Exception;
use stdClass;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    #[Test]
    public function bind_registersService(): void
    {
        $this->container->bind('service', fn() => new stdClass());

        $this->assertTrue($this->container->has('service'));
    }

    #[Test]
    public function make_resolvesBindingWithClosure(): void
    {
        $this->container->bind('service', fn() => new stdClass());

        $result = $this->container->make('service');

        $this->assertInstanceOf(stdClass::class, $result);
    }

    #[Test]
    public function make_returnsNewInstanceEachTimeForBind(): void
    {
        $this->container->bind('service', fn() => new stdClass());

        $first = $this->container->make('service');
        $second = $this->container->make('service');

        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function singleton_returnsSameInstanceEachTime(): void
    {
        $this->container->singleton('service', fn() => new stdClass());

        $first = $this->container->make('service');
        $second = $this->container->make('service');

        $this->assertSame($first, $second);
    }

    #[Test]
    public function instance_registersExistingInstance(): void
    {
        $instance = new stdClass();
        $instance->id = 'test-123';

        $this->container->instance('service', $instance);

        $resolved = $this->container->make('service');
        $this->assertSame($instance, $resolved);
        $this->assertEquals('test-123', $resolved->id);
    }

    #[Test]
    public function instance_hasReturnsTrueAfterRegistration(): void
    {
        $this->container->instance('service', new stdClass());

        $this->assertTrue($this->container->has('service'));
    }

    #[Test]
    public function has_returnsFalseForUnregisteredService(): void
    {
        $this->assertFalse($this->container->has('not-registered'));
    }

    #[Test]
    public function has_returnsTrueForBinding(): void
    {
        $this->container->bind('service', fn() => new stdClass());

        $this->assertTrue($this->container->has('service'));
    }

    #[Test]
    public function has_returnsTrueForSingleton(): void
    {
        $this->container->singleton('service', fn() => new stdClass());

        $this->assertTrue($this->container->has('service'));
    }

    #[Test]
    public function make_throwsExceptionForUnregisteredService(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Service not found: unknown-service');

        $this->container->make('unknown-service');
    }

    #[Test]
    public function bind_closureReceivesContainer(): void
    {
        $this->container->bind('dependency', fn() => 'dependency-value');
        $this->container->bind('service', function ($container) {
            return (object)['dep' => $container->make('dependency')];
        });

        $result = $this->container->make('service');

        $this->assertEquals('dependency-value', $result->dep);
    }

    #[Test]
    public function singleton_closureReceivesContainer(): void
    {
        $this->container->singleton('dependency', fn() => 'singleton-dep');
        $this->container->singleton('service', function ($container) {
            return (object)['dep' => $container->make('dependency')];
        });

        $result = $this->container->make('service');

        $this->assertEquals('singleton-dep', $result->dep);
    }

    #[Test]
    public function instance_takesPreferenceOverBinding(): void
    {
        $this->container->bind('service', fn() => (object)['source' => 'binding']);

        $instance = new stdClass();
        $instance->source = 'instance';
        $this->container->instance('service', $instance);

        $resolved = $this->container->make('service');
        $this->assertEquals('instance', $resolved->source);
    }

    #[Test]
    public function singleton_onlyCreatesInstanceOnce(): void
    {
        $callCount = 0;
        $this->container->singleton('service', function () use (&$callCount) {
            $callCount++;
            return new stdClass();
        });

        $this->container->make('service');
        $this->container->make('service');
        $this->container->make('service');

        $this->assertEquals(1, $callCount);
    }
}
