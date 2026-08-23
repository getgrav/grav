<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Processors\InitializeProcessor;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * Class InitializeProcessorTest
 */
class InitializeProcessorTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav $grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    protected function tearDown(): void
    {
    }

    protected function handleRedirectRequest(string $requestPath): ?\Psr\Http\Message\ResponseInterface
    {
        $factory = new Psr17Factory();
        $uri = $factory->createUri('http://localhost:8080' . $requestPath);
        $request = $factory->createServerRequest('GET', $uri);

        $processor = new InitializeProcessor($this->grav);
        $method = new ReflectionMethod(InitializeProcessor::class, 'handleRedirectRequest');
        $method->setAccessible(true);

        return $method->invoke($processor, $request);
    }

    public function testRedirectTrailingSlashKeepsCustomBaseUrlWhenPhysicalPathIncludesIt(): void
    {
        $config = $this->grav['config'];
        $currentBase = $config->get('system.custom_base_url');
        $config->set('system.custom_base_url', 'http://localhost:8080/custombaseurl');

        /** @var \Grav\Common\Uri $uri */
        $uri = $this->grav['uri'];
        $uri->initializeWithUrlAndRootPath('http://localhost:8080/custombaseurl/page1/', '')->init();

        $response = $this->handleRedirectRequest('/custombaseurl/page1/');

        $this->assertNotNull($response);
        $this->assertSame(
            'http://localhost:8080/custombaseurl/page1',
            $response->getHeaderLine('Location')
        );

        $config->set('system.custom_base_url', $currentBase);
    }

    public function testRedirectTrailingSlashRestoresCustomBaseUrlWhenPhysicalPathIsStripped(): void
    {
        $config = $this->grav['config'];
        $currentBase = $config->get('system.custom_base_url');
        $config->set('system.custom_base_url', 'http://localhost:8080/custombaseurl');

        /** @var \Grav\Common\Uri $uri */
        $uri = $this->grav['uri'];
        // The reverse proxy strips the custom base path before it reaches PHP.
        $uri->initializeWithUrlAndRootPath('http://localhost:8080/page1/', '')->init();

        $response = $this->handleRedirectRequest('/page1/');

        $this->assertNotNull($response);
        $this->assertSame(
            'http://localhost:8080/custombaseurl/page1',
            $response->getHeaderLine('Location')
        );

        $config->set('system.custom_base_url', $currentBase);
    }
}
