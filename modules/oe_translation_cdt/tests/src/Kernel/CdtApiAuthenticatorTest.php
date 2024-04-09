<?php

declare(strict_types=1);

use Drupal\Component\Datetime\Time;
use Drupal\Core\State\StateInterface;
use Drupal\oe_translation_cdt\Api\CdtApiAuthenticator;
use Drupal\oe_translation_cdt\Exception\CdtConnectionException;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;
use OpenEuropa\CdtClient\Contract\ApiClientInterface;
use OpenEuropa\CdtClient\Model\Response\Token;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Tests the CDT API client wrapper.
 *
 * @group batch1
 */
class CdtApiAuthenticatorTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_cdt',
    'oe_translation_remote',
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
   * The authenticator.
   */
  private CdtApiAuthenticator $authenticator;

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

    $this->authenticator = $this->container->get('oe_translation_cdt.api_authenticator');
    $this->authenticator->resetAuthentication();
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
    $this->authenticator->authenticate();
    $this->assertEquals($this->state->get('cdt.token'), serialize($this->token));
    $this->assertEquals($this->state->get('cdt.token_expiry_date'), 300);
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
    $this->authenticator->authenticate();
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
    $this->authenticator->authenticate();
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
    $this->authenticator->authenticate();
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
    $this->authenticator->authenticate();
  }

  /**
   * Test multiple authentication attempts.
   */
  public function testMultipleAuthenticationAttempts(): void {
    $this->authenticator->resetAuthentication();
    $this->apiProphecy->requestToken()
      ->shouldBeCalledOnce()
      ->willReturn($this->token);
    $this->apiProphecy->checkConnection()
      ->willReturn(TRUE)
      ->shouldBeCalledOnce();
    $this->authenticator->authenticate();
    $this->authenticator->authenticate();
  }

}
