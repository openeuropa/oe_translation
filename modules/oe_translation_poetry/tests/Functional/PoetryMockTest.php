<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\oe_translation_poetry_mock\PoetryMock;
use Drupal\Tests\BrowserTestBase;
use EC\Poetry\Messages\MessageInterface;

/**
 * Tests the poetry mock.
 */
class PoetryMockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'content_translation',
    'tmgmt',
    'oe_translation',
    'oe_translation_poetry',
    'oe_translation_poetry_mock',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->container->get('config.factory')->getEditable('oe_translation_poetry.settings')
      ->set('service_wsdl', PoetryMock::getWsdlUrl())
      ->save();
  }

  /**
   * Tests that requests to Poetry are being mocked.
   */
  public function testRequestResponse(): void {
    /** @var \Drupal\oe_translation_poetry\Poetry $poetry */
    $poetry = $this->container->get('oe_translation_poetry.client');

    // Assert that the Poetry service gets properly instantiated.
    $expected_settings = [];
    $expected_settings['identifier.code'] = 'WEB';
    $expected_settings['identifier.sequence'] = 'EWCMS_SEQUENCE';
    $expected_settings['identifier.year'] = date('Y');
    $expected_settings['service.wsdl'] = PoetryMock::getWsdlUrl();
    $expected_settings['service.username'] = 'admin';
    $expected_settings['service.password'] = 'admin';
    $expected_settings['notification.username'] = 'admin';
    $expected_settings['notification.password'] = 'admin';

    $settings = $poetry->getSettings();
    foreach ($expected_settings as $name => $setting) {
      $this->assertEqual($setting, $settings[$name]);
    }

    // Create a test message and check that Mocked responses are returned.
    /** @var \EC\Poetry\Messages\Requests\CreateTranslationRequest $message */
    $message = $poetry->get('request.create_translation_request');

    $test_id = 1;
    $id = $message->getIdentifier();
    $id->setNumber('40012');
    $id->setVersion('0');
    $id->setPart(33);
    $message->setIdentifier($id);
    $message->withDetails()
      ->setClientId($test_id)
      ->setTitle('Translation title');

    $message->withContact()
      ->setType('author')
      ->setNickname('Test');

    $message->withSource()
      ->setFormat('HTML')
      ->setName('test.html')
      ->setFile(base64_encode('<p>The text</p>'))
      ->setLegiswriteFormat('No')
      ->withSourceLanguage()
      ->setCode('en')
      ->setPages(1);

    $message->withTarget()
      ->setLanguage('fr')
      ->setFormat('HTML')
      ->setAction('INSERT')
      ->setDelay(date('d/m/y'));

    $client = $poetry->getClient();
    $response = $client->send($message);
    $this->assertInstanceOf(MessageInterface::class, $response);
    $expected = file_get_contents(drupal_get_path('module', 'oe_translation_poetry_mock') . '/fixtures/' . $test_id . '.xml');
    $this->assertEqual($expected, $response->getRaw());

    $test_id = 2;
    $details = $message->getDetails();
    $details->setClientId($test_id);
    $message->setDetails($details);

    $response = $client->send($message);
    $this->assertInstanceOf(MessageInterface::class, $response);
    $expected = file_get_contents(drupal_get_path('module', 'oe_translation_poetry_mock') . '/fixtures/error.xml');
    $this->assertEqual($expected, $response->getRaw());
  }

}
