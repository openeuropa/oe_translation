<?php

declare(strict_types=1);

use Drupal\Component\Datetime\Time;
use Drupal\Core\State\StateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_translation_cdt\Api\CdtApiWrapper;
use Drupal\oe_translation_cdt\Exception\CdtConnectionException;
use OpenEuropa\CdtClient\Contract\ApiClientInterface;
use OpenEuropa\CdtClient\Model\Response\Token;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Tests the CDT API client wrapper.
 *
 * @group batch1
 */
class CdtApiWrapperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_cdt',
  ];

  /**
   * The API client prophecy.
   */
  private ApiClientInterface|ObjectProphecy $apiProphecy;

  /**
   * The token.
   */
  private Token $token;

  /**
   * The API wrapper.
   */
  private CdtApiWrapper $apiWrapper;

  /**
   * The state.
   */
  private StateInterface $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $time_prophecy = $this->prophesize(Time::class);
    $time_prophecy->getCurrentTime()->willReturn(100);
    $this->container->set('datetime.time', $time_prophecy->reveal());
    $this->apiProphecy = $this->prophesize(ApiClientInterface::class);
    $this->container->set('oe_translation_cdt.api_client', $this->apiProphecy->reveal());

    $this->token = (new Token())
      ->setAccessToken('abc')
      ->setExpiresIn(200)
      ->setTokenType('Bearer');
    $this->apiProphecy
      ->setToken($this->token)
      ->willReturn($this->apiProphecy->reveal());

    $this->apiWrapper = $this->container->get('oe_translation_cdt.api_wrapper');
    $this->apiWrapper->resetAuthentication();
    $this->state = $this->container->get('state');
  }

  /**
   * Test the valid authentication with the token request.
   */
  public function testValidRequestAuthentication(): void {
    $this->apiProphecy->requestToken()
      ->shouldBeCalledOnce()
      ->willReturn($this->token);
    $this->apiProphecy->checkConnection()
      ->willReturn(TRUE)
      ->shouldBeCalledOnce();
    $client = $this->apiWrapper->getClient();
    assert($client instanceof ApiClientInterface);
    $this->assertEquals(serialize($this->token), $this->state->get('cdt.token'));
    $this->assertEquals(300, $this->state->get('cdt.token_expiry_date'));
  }

  /**
   * Test the valid authentication with the stored token.
   */
  public function testValidStoredAuthentication(): void {
    $this->state->set('cdt.token', serialize($this->token));
    $this->state->set('cdt.token_expiry_date', 300);
    $this->apiProphecy->requestToken()->shouldNotBeCalled();
    $this->apiProphecy->checkConnection()
      ->willReturn(TRUE)
      ->shouldBeCalledOnce();
    $client = $this->apiWrapper->getClient();
    assert($client instanceof ApiClientInterface);
  }

  /**
   * Test the authentication with invalid stored token.
   */
  public function testInvalidStoredAuthentication(): void {
    $this->state->set('cdt.token', serialize($this->token));
    $this->state->set('cdt.token_expiry_date', 300);
    $this->apiProphecy->requestToken()
      ->shouldBeCalledOnce()
      ->willReturn($this->token);
    $this->apiProphecy->checkConnection()
      ->willReturn(FALSE, TRUE)
      ->shouldBeCalledTimes(2);
    $client = $this->apiWrapper->getClient();
    assert($client instanceof ApiClientInterface);
  }

  /**
   * Test the authentication with expired stored token.
   */
  public function testExpiredStoredAuthentication(): void {
    $this->state->set('cdt.token', serialize($this->token));
    $this->state->set('cdt.token_expiry_date', 50);
    $this->apiProphecy->requestToken()
      ->shouldBeCalledOnce()
      ->willReturn($this->token);
    $this->apiProphecy->checkConnection()
      ->willReturn(TRUE)
      ->shouldBeCalledOnce();
    $client = $this->apiWrapper->getClient();
    assert($client instanceof ApiClientInterface);
  }

  /**
   * Test the authentication with the valid token, but failed connection.
   */
  public function testInvalidConnection(): void {
    $this->apiProphecy->requestToken()
      ->shouldBeCalledOnce()
      ->willReturn($this->token);
    $this->apiProphecy->checkConnection()
      ->willReturn(FALSE)
      ->shouldBeCalledOnce();
    $this->expectExceptionObject(new CdtConnectionException('The connection to the CDT API could not be established.'));
    $client = $this->apiWrapper->getClient();
    assert($client instanceof ApiClientInterface);
  }

  /**
   * Test multiple authentication attempts.
   */
  public function testMultipleAuthenticationAttempts(): void {
    $this->apiProphecy->requestToken()
      ->shouldBeCalledOnce()
      ->willReturn($this->token);
    $this->apiProphecy->checkConnection()
      ->willReturn(TRUE)
      ->shouldBeCalledOnce();
    $client = $this->apiWrapper->getClient();
    assert($client instanceof ApiClientInterface);
    $client = $this->apiWrapper->getClient();
    assert($client instanceof ApiClientInterface);
  }

}
