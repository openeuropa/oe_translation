<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\oe_translation_poetry_mock\PoetryMock;
use Drupal\Tests\BrowserTestBase;
use EC\Poetry\Exceptions\ParsingException;
use EC\Poetry\Messages\MessageInterface;
use EC\Poetry\Messages\Responses\Status;

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
   * Tests that requests to Poetry are being mocked.
   */
  public function testRequestResponse(): void {
    /** @var \Drupal\oe_translation_poetry\Poetry $poetry */
    $poetry = $this->container->get('oe_translation_poetry.client.default');
    /** @var \Drupal\oe_translation_poetry_mock\PoetryMockFixturesGenerator $fixture_generator */
    $fixture_generator = $this->container->get('oe_translation_poetry_mock.fixture_generator');

    // Assert that the Poetry service gets properly instantiated.
    $expected_settings = [];
    $expected_settings['identifier.code'] = 'WEB';
    $expected_settings['identifier.year'] = date('Y');
    $expected_settings['service.username'] = 'admin';
    $expected_settings['service.password'] = 'admin';
    $expected_settings['notification.username'] = 'admin';
    $expected_settings['notification.password'] = 'admin';
    $settings = $poetry->getSettings();

    foreach ($expected_settings as $name => $setting) {
      $this->assertEqual($settings[$name], $setting);
    }

    $settings->set('service.wsdl', PoetryMock::getWsdlUrl());
    // Create a test message and check that Mocked responses are returned.
    /** @var \EC\Poetry\Messages\Requests\CreateTranslationRequest $message */
    $message = $poetry->get('request.create_translation_request');

    $id = $message->getIdentifier();
    $id->setNumber('40012');
    $id->setVersion('0');
    $id->setPart(33);
    $message->setIdentifier($id);
    $message->withDetails()
      ->setClientId($id->getFormattedIdentifier())
      ->setTitle('Translation title');

    $message->withContact()
      ->setType('auteur')
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
    $expected = $fixture_generator->responseFromMessage($message);
    $this->assertEqual($expected, $response->getRaw());

    // Check the request and response have been logged.
    $result = $this->container->get('database')
      ->select('watchdog', 'w')
      ->range(0, 1)
      ->fields('w', ['variables'])
      ->condition('message', 'Poetry event <strong>@name</strong>: <br /><br />Username: <strong>@username</strong> <br /><br />Password: <strong>@password</strong> \n\n<pre>@message</pre>')
      ->execute()
      ->fetchCol(0);
    $this->assertCount(1, $result);
    $logged_message = trim(unserialize(reset($result))['@message'], "'");
    $logged_message_id = (string) simplexml_load_string($logged_message)->request->attributes()['id'];
    $this->assertEqual($logged_message_id, 'WEB/' . date('Y') . '/40012/0/33/TRA');
  }

  /**
   * Tests that wrong requests to Poetry return errors.
   */
  public function testErrorResponses(): void {
    /** @var \Drupal\oe_translation_poetry\Poetry $poetry */
    $poetry = $this->container->get('oe_translation_poetry.client.default');
    $settings = $poetry->getSettings();
    $settings->set('service.wsdl', PoetryMock::getWsdlUrl());

    // Use wrong credentials to access the service.
    $settings->set('service.username', 'user');
    $settings->set('service.password', 'password');

    /** @var \EC\Poetry\Messages\Requests\CreateTranslationRequest $message */
    $message = $poetry->get('request.create_translation_request');
    // Generate an identifier that is already ongoing.
    $id = $message->getIdentifier();
    $id->setNumber('9999');
    $id->setVersion('0');
    $id->setPart(0);
    $message->setIdentifier($id);
    $client = $poetry->getClient();
    try {
      $response = $client->send($message);
    }
    catch (ParsingException $exception) {
      $this->assertEqual($exception->getMessage(), "XML message could not be parsed: ERROR: Authentication failed.");
    }

    // Use the correct credentials to access the service.
    $settings->set('service.username', 'admin');
    $settings->set('service.password', 'admin');

    // Generate an identifier that is already ongoing.
    $id->setNumber('9999');
    $id->setVersion('0');
    $id->setPart(0);
    $message->setIdentifier($id);
    $client = $poetry->getClient();
    $response = $client->send($message);
    $this->assertInstanceOf(Status::class, $response);
    $this->assertEqual($response->getRequestStatus()->getMessage(), 'Error in xmlActions:newRequest: A request with the same references if already in preparation another product exists for this reference.');

    $this->container->set('oe_translation_poetry.client.default', NULL);
    /** @var \Drupal\oe_translation_poetry\Poetry $poetry */
    $poetry = $this->container->get('oe_translation_poetry.client.default');
    $settings = $poetry->getSettings();
    $settings->set('service.wsdl', PoetryMock::getWsdlUrl());

    // Generate an identifier with a wrong number.
    $id->setNumber('0');
    $id->setVersion('0');
    $id->setPart(0);
    $message->setIdentifier($id);
    $client = $poetry->getClient();
    $response = $client->send($message);
    $this->assertInstanceOf(Status::class, $response);
    $this->assertEqual($response->getRequestStatus()->getMessage(), 'Error in xmlActions:newRequest: Application general error : Element DEMANDEID.NUMERO.XMLTEXT is undefined in REQ_ROOT.,');
  }

}
