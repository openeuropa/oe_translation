<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry_mock\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oe_translation_epoetry_mock\MockServer;
use OpenEuropa\EPoetry\Serializer\Serializer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Mock controller for ePoetry routes.
 */
class MockController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $classResolver
   *   The class resolver.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ClassResolverInterface $classResolver) {
    $this->entityTypeManager = $entity_type_manager;
    $this->classResolver = $classResolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('class_resolver')
    );
  }

  /**
   * Runs the soap server.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty response.
   */
  public function server(Request $request): Response {
    $handler = $this->classResolver->getInstanceFromDefinition(MockServer::class);

    $request_xml = file_get_contents("php://input");
    if ($request_xml == "") {
      throw new NotFoundHttpException();
    }

    $xml = str_ireplace([
      'SOAPENV:',
      'SOAP:',
      'eu:',
      'SOAP-ENV:',
      'ns1:',
      'xsi:',
    ], '', $request_xml);
    $xml = simplexml_load_string($xml);
    if (!$xml->Body->children()) {
      // @todo handle this.
      throw new NotFoundHttpException();
    }
    $method = key($xml->Body->children());
    if (!method_exists($handler, $method)) {
      // @todo handle this.
      throw new NotFoundHttpException();
    }

    $response_object = $handler->{$method}($xml);
    $serializer = new Serializer();
    $xml_string = $serializer->serialize($response_object, 'xml', ['xml_root_node_name' => 'ns0:createLinguisticRequestResponse']);
    $xml_string = trim(str_replace('<?xml version="1.0"?>', '', $xml_string));

    $wrapper = file_get_contents(drupal_get_path('module', 'oe_translation_epoetry_mock') . '/fixtures/response_wrapper.xml');
    $wrapper = str_replace('@response', $xml_string, $wrapper);

    $response = new Response($wrapper);
    $response->headers->set('Content-type', 'application/xml; charset=utf-8');

    return $response;
  }

}
