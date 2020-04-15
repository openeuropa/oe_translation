<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_test;

use Drupal\oe_translation_poetry_mock\PoetryMock;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Pimple service provider used for adding the test SOAP client.
 */
class PoetrySoapProvider implements ServiceProviderInterface {

  /**
   * The cookies to add to the SOAP call.
   *
   * @var array
   */
  protected $cookies = [];

  /**
   * PoetrySoapProvider constructor.
   *
   * @param array $cookies
   *   The cookies to add to the SOAP call.
   */
  public function __construct(array $cookies = []) {
    $this->cookies = $cookies;
  }

  /**
   * {@inheritdoc}
   */
  public function register(Container $container) {

    $container['soap_client'] = function (Container $container) {
      $options = [
        'location' => PoetryMock::getServerUrl(),
        'uri' => 'poetryMock',
      ] + $container['settings']['client.options'];

      // Cannot pass directly the WSDL because it would make a request already
      // before setting the cookie below and would hit the actual site and not
      // the test site.
      $client = new \SoapClient(NULL, $options);

      foreach ($this->cookies as $name => $value) {
        $client->__setCookie($name, $value);
      }

      return $client;
    };
  }

}
