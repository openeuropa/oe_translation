<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_mock\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\oe_translation_poetry\NotificationEndpointResolver;
use Drupal\oe_translation_poetry\Poetry;
use Drupal\oe_translation_poetry_mock\PoetryMock;
use Drupal\oe_translation_poetry_mock\PoetryMockFixturesGenerator;
use Drupal\tmgmt\JobInterface;
use EC\Poetry\Messages\Components\Identifier;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for running the Poetry mock server.
 */
class PoetryMockController extends ControllerBase {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The mock fixtures generator.
   *
   * @var \Drupal\oe_translation_poetry_mock\PoetryMockFixturesGenerator
   */
  protected $fixturesGenerator;

  /**
   * The Poetry service.
   *
   * @var \Drupal\oe_translation_poetry\Poetry
   */
  protected $poetry;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * Poetry constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\oe_translation_poetry_mock\PoetryMockFixturesGenerator $fixturesGenerator
   *   The mock fixtures generator.
   * @param \Drupal\oe_translation_poetry\Poetry $poetry
   *   The Poetry service.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   */
  public function __construct(RequestStack $requestStack, PoetryMockFixturesGenerator $fixturesGenerator, Poetry $poetry, Messenger $messenger, Renderer $renderer) {
    $this->request = $requestStack->getCurrentRequest();
    $this->fixturesGenerator = $fixturesGenerator;
    $this->poetry = $poetry;
    $this->messenger = $messenger;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('oe_translation_poetry_mock.fixture_generator'),
      $container->get('oe_translation_poetry.client.default'),
      $container->get('messenger'),
      $container->get('renderer')
    );
  }

  /**
   * Returns the WSDL page.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The XML response.
   */
  public function wsdl(): Response {
    $path = drupal_get_path('module', 'oe_translation_poetry_mock') . '/poetry_mock.wsdl.xml';
    $wsdl = file_get_contents($path);
    $base_path = $this->request->getSchemeAndHttpHost();
    if ($this->request->getBasePath() !== "/") {
      $base_path .= $this->request->getBasePath();
    }
    $wsdl = str_replace('@base_path', $base_path, $wsdl);
    $response = new Response($wsdl);
    $response->headers->set('Content-type', 'application/xml; charset=utf-8');
    return $response;
  }

  /**
   * Runs the soap server.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty response.
   */
  public function server(): Response {
    $url = Url::fromRoute('oe_translation_poetry_mock.server')->toString();
    $options = ['uri' => $url];
    // Instantiate the server without WSDL because in tests it makes a request
    // to the actual site instead of the test site instance.
    $server = new \SoapServer(NULL, $options);
    $mock = new PoetryMock($this->fixturesGenerator);
    $server->setObject($mock);

    ob_start();
    $server->handle();
    $result = ob_get_contents();
    ob_end_clean();

    $response = new Response($result);
    $response->headers->set('Content-type', 'application/xml; charset=utf-8');
    return $response;
  }

  /**
   * Sends a status notification to the endpoint.
   *
   * @param \Drupal\tmgmt\JobInterface $tmgmt_job
   *   The job.
   * @param string $status
   *   The status.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse|\Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function sendStatusNotification(JobInterface $tmgmt_job, string $status): Response {
    $identifier_info = $tmgmt_job->get('poetry_request_id')->get(0)->getValue();
    $language = [
      'code' => strtoupper($tmgmt_job->getRemoteTargetLanguage()),
    ];

    $accepted_languages = [];
    $rejected_languages = [];
    $cancelled_languages = [];

    if ($status === 'ONG') {
      $language['date'] = '30/08/2019 23:59';
      $language['accepted_date'] = '30/09/2019 23:59';
      $accepted_languages[] = $language;
    }

    if ($status === 'CNL') {
      $cancelled_languages[] = $language;
    }

    if ($status === 'REF') {
      $rejected_languages[] = $language;
    }

    // We need to render this in a new context to prevent cache leaks.
    $this->renderer->executeInRenderContext(new RenderContext(), function () use ($identifier_info, $status, $accepted_languages, $rejected_languages, $cancelled_languages) {
      $status_notification = $this->fixturesGenerator->statusNotification($identifier_info, $status, $accepted_languages, $rejected_languages, $cancelled_languages);
      $this->performNotification($status_notification);
    });

    $action_map = [
      'ONG' => 'accept',
      'CNL' => 'cancel',
      'REF' => 'refuse',
    ];
    $this->messenger->addMessage($this->t('The status notification to @action the job has been sent', ['@action' => $action_map[$status]]));
    $destination = $this->request->query->get('destination');
    if ($destination) {
      return new TrustedRedirectResponse($destination);
    }

    return new Response('ok');
  }

  /**
   * Sends a dummy translation notification.
   *
   * @param \Drupal\tmgmt\JobInterface $tmgmt_job
   *   The job item.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse|\Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function sendTranslationNotification(JobInterface $tmgmt_job): Response {
    $identifier_info = $tmgmt_job->get('poetry_request_id')->get(0)->getValue();
    $identifier = new Identifier();
    foreach ($identifier_info as $name => $value) {
      $identifier->offsetSet($name, $value);
    }
    $items = $tmgmt_job->getItems();
    $item = reset($items);
    $data = \Drupal::service('tmgmt.data')->filterTranslatable($item->getData());
    foreach ($data as $field => &$info) {
      // Check whether this is a new translation or not by checking for a
      // stored translation for the field.
      if (isset($info['#translation'])) {
        $info['#text'] = $info['#translation']['#text'] . ' OVERRIDDEN';
      }
      else {
        $info['#text'] .= ' - ' . $tmgmt_job->getTargetLangcode();

      }
    }

    // We need to render this in a new context to prevent cache leaks.
    $this->renderer->executeInRenderContext(new RenderContext(), function () use ($identifier_info, $identifier, $tmgmt_job, $data, $item) {
      $translation_notification = $this->fixturesGenerator->translationNotification($identifier, $tmgmt_job->getRemoteTargetLanguage(), $data, (int) $item->id(), (int) $tmgmt_job->id());
      $this->performNotification($translation_notification);
    });

    $this->messenger->addMessage($this->t('The translation notification has been sent'));
    $destination = $this->request->query->get('destination');
    if ($destination) {
      return new TrustedRedirectResponse($destination);
    }

    return new Response('ok');
  }

  /**
   * Runs the soap server types endpoint.
   *
   * @todo This might not be needed so it could be removed.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty response.
   */
  public function serverTypes(): Response {
    return new Response();
  }

  /**
   * Calls the notification endpoint with a message.
   *
   * This mimics notification requests sent by Poetry.
   *
   * @param string $message
   *   The message.
   *
   * @return string
   *   The response XML.
   */
  protected function performNotification(string $message): string {
    $settings = $this->poetry->getSettings();
    $url = NotificationEndpointResolver::resolve();
    // Instantiate the client without WSDL because in tests it makes a request
    // to the actual site instead of the test site instance.
    $client = new \SoapClient(NULL, [
      'cache_wsdl' => WSDL_CACHE_NONE,
      'location' => $url,
      'uri' => 'urn:OEPoetryClient',
    ]);
    return $client->__soapCall('handle', [
      $settings['notification.username'],
      $settings['notification.password'],
      $message,
    ]);
  }

}
