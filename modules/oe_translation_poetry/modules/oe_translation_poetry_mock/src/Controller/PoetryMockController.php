<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_mock\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\oe_translation_poetry_mock\PoetryMock;
use Drupal\oe_translation_poetry_mock\PoetryMockFixturesGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Zend\Diactoros\Response\XmlResponse;

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
   * Poetry constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\oe_translation_poetry_mock\PoetryMockFixturesGenerator $fixturesGenerator
   *   The mock fixtures generator.
   */
  public function __construct(RequestStack $requestStack, PoetryMockFixturesGenerator $fixturesGenerator) {
    $this->request = $requestStack->getCurrentRequest();
    $this->fixturesGenerator = $fixturesGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('oe_translation_poetry_mock.fixture_generator')
    );
  }

  /**
   * Returns the WSDL page.
   *
   * @return \Zend\Diactoros\Response\XmlResponse
   *   The XML response.
   */
  public function wsdl(): XmlResponse {
    $path = drupal_get_path('module', 'oe_translation_poetry_mock') . '/poetry_mock.wsdl.xml';
    $wsdl = file_get_contents($path);
    $base_path = $this->request->getSchemeAndHttpHost();
    if ($this->request->getBasePath() !== "/") {
      $base_path .= $this->request->getBasePath();
    }
    $wsdl = str_replace('@base_path', $base_path, $wsdl);
    return new XmlResponse($wsdl);
  }

  /**
   * Runs the soap server.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty response.
   */
  public function server(): Response {
    $wsdl = PoetryMock::getWsdlUrl();
    $url = Url::fromRoute('oe_translation_poetry_mock.server')->toString();
    $options = ['uri' => $url];
    $server = new \SoapServer($wsdl, $options);
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
