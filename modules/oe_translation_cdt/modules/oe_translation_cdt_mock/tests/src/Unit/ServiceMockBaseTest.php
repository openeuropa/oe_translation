<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_cdt_mock\Unit;

use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use Drupal\oe_translation_cdt_mock\Plugin\ServiceMock\ServiceMockBase;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Test the helper mocking methods.
 *
 * @group batch1
 */
final class ServiceMockBaseTest extends UnitTestCase {

  /**
   * The service mock.
   */
  protected ServiceMockBase $serviceMockStub;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->serviceMockStub = new class() extends ServiceMockBase {
      /**
       * The endpoint URL path.
       */
      protected string $endpointPath;

      /**
       * Disable original plugin constructor.
       */
      public function __construct() {}

      /**
       * A new method that allows setting the endpoint URL path.
       *
       * @param string $path
       *   The endpoint URL path to set.
       */
      public function setEndpointUrlPath(string $path) {
        $this->endpointPath = $path;
      }

      /**
       * {@inheritdoc}
       */
      protected function getEndpointUrlPath(): string {
        return $this->endpointPath;
      }

      /**
       * {@inheritdoc}
       */
      protected function getEndpointResponse(RequestInterface $request): ResponseInterface {
        return new Response();
      }

    };

    $settings = [
      'cdt.base_api_url' => 'https://example.com/api',
    ];
    new Settings($settings);
  }

  /**
   * Gets a protected method for testing.
   *
   * @param string $name
   *   The method name.
   *
   * @return \ReflectionMethod
   *   The method.
   *
   * @throws \ReflectionException
   */
  protected static function getMethod($name): \ReflectionMethod {
    $class = new \ReflectionClass(ServiceMockBase::class);
    $method = $class->getMethod($name);
    return $method;
  }

  /**
   * Tests fetching path parameters.
   */
  public function testPathParameters(): void {
    $this->serviceMockStub->setEndpointUrlPath('/method/:parameter1/:parameter_2/:parameter-3/:parameter4/:PARAMETER5');
    $request1 = new Request('GET', 'https://example.com/api/method/1/2/3/4/5');
    $pathParameters = self::getMethod('getRequestParameters')->invokeArgs($this->serviceMockStub, [$request1]);
    self::assertEquals([
      'parameter1' => '1',
      'parameter_2' => '2',
      'parameter-3' => '3',
      'parameter4' => '4',
      'PARAMETER5' => '5',
    ], $pathParameters);

    $request2 = new Request('GET', 'https://example.com/api/method/1/2');
    $pathParameters = self::getMethod('getRequestParameters')->invokeArgs($this->serviceMockStub, [$request2]);
    self::assertEquals([
      'parameter1' => '1',
      'parameter_2' => '2',
      'parameter-3' => NULL,
      'parameter4' => NULL,
      'PARAMETER5' => NULL,
    ], $pathParameters);

    // Check what happens if the base url contains only host.
    $settings = [
      'cdt.base_api_url' => 'https://example.com',
    ];
    new Settings($settings);
    $request2 = new Request('GET', 'https://example.com/method/a/b/c/d/e');
    $pathParameters = self::getMethod('getRequestParameters')->invokeArgs($this->serviceMockStub, [$request2]);
    self::assertEquals([
      'parameter1' => 'a',
      'parameter_2' => 'b',
      'parameter-3' => 'c',
      'parameter4' => 'd',
      'PARAMETER5' => 'e',
    ], $pathParameters);
  }

  /**
   * Tests matching URLs.
   */
  public function testUrlMatching(): void {
    $this->serviceMockStub->setEndpointUrlPath('/method/:parameter1/:parameter2');
    self::assertTrue($this->serviceMockStub->applies(new Request('GET', 'https://example.com/api/method/1/2'), []));
    self::assertTrue($this->serviceMockStub->applies(new Request('GET', 'https://example.com/api/method/aa/bb'), []));
    self::assertFalse($this->serviceMockStub->applies(new Request('GET', 'https://example.com/api/method/1/2/3'), []));
    self::assertFalse($this->serviceMockStub->applies(new Request('GET', 'https://example.com/api/method/1'), []));
    self::assertFalse($this->serviceMockStub->applies(new Request('GET', 'http://example.com/api/method/1/2'), []));
    self::assertFalse($this->serviceMockStub->applies(new Request('GET', '/method/1/2'), []));

    // Check what happens if the base url contains only host.
    $settings = [
      'cdt.base_api_url' => 'https://example.com',
    ];
    new Settings($settings);
    self::assertFalse($this->serviceMockStub->applies(new Request('GET', 'https://example.com/method/1'), []));
    self::assertTrue($this->serviceMockStub->applies(new Request('GET', 'https://example.com/method/1/2'), []));
    self::assertFalse($this->serviceMockStub->applies(new Request('GET', 'https://example.com/method/1/2/3'), []));
  }

}
