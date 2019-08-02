<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_mock\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\oe_translation_poetry_mock\PoetryMock;
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
   * Poetry constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(RequestStack $requestStack) {
    $this->request = $requestStack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * Runs the soap server.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty response.
   */
  public function server(): Response {
    $wsdl = PoetryMock::getWsdlUrl($this->request);
    $url = Url::fromRoute('oe_translation_poetry_mock.server')->toString();
    $options = ['uri' => $url];
    $server = new \SoapServer($wsdl, $options);
    $server->setClass(PoetryMock::class);

    $server->handle();
    return new Response();
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

}
