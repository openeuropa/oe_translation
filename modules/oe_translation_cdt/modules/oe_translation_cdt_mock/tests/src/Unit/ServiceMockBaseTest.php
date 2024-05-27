<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_cdt_mock\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\oe_translation_cdt_mock\Plugin\ServiceMock\ServiceMockBase;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Psr7\Request;

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

    $this->serviceMockStub = $this->getMockForAbstractClass(ServiceMockBase::class, [
      [],
      'service_mock',
      [],
      $this->createMock(ModuleExtensionList::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(LoggerChannelFactoryInterface::class),
    ]);

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
    $this->serviceMockStub->expects(self::any())
      ->method('getEndpointUrlPath')
      ->willReturn('/method/:parameter1/:parameter_2/:parameter-3/::parameter4/:PARAMETER5');
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
  }

  /**
   * Tests matching URLs.
   */
  public function testUrlMatching(): void {
    $this->serviceMockStub->expects(self::any())
      ->method('getEndpointUrlPath')
      ->willReturn('/method/:parameter1/:parameter2');
    self::assertTrue($this->serviceMockStub->applies(new Request('GET', 'https://example.com/api/method/1/2'), []));
    self::assertTrue($this->serviceMockStub->applies(new Request('GET', 'https://example.com/api/method/aa/bb'), []));
    self::assertFalse($this->serviceMockStub->applies(new Request('GET', 'https://example.com/api/method/1/2/3'), []));
    self::assertFalse($this->serviceMockStub->applies(new Request('GET', 'https://example.com/api/method/1'), []));
    self::assertFalse($this->serviceMockStub->applies(new Request('GET', 'http://example.com/api/method/1/2'), []));
    self::assertFalse($this->serviceMockStub->applies(new Request('GET', '/method/1/2'), []));
  }

}
