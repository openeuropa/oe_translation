<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry_mock\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\State\StateInterface;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_epoetry_mock\EpoetryTranslationMockHelper;
use Drupal\oe_translation_epoetry_mock\MockServer;
use OpenEuropa\EPoetry\Serializer\Serializer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $classResolver
   *   The class resolver.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ClassResolverInterface $classResolver, StateInterface $state, ModuleExtensionList $module_extension_list) {
    $this->entityTypeManager = $entity_type_manager;
    $this->classResolver = $classResolver;
    $this->state = $state;
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('class_resolver'),
      $container->get('state'),
      $container->get('extension.list.module')
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

    $error_response = $this->state->get('oe_translation_epoetry_mock_response_error', []);
    if ($error_response) {
      $wrapper = file_get_contents($this->moduleExtensionList->getPath('oe_translation_epoetry_mock') . '/fixtures/error_wrapper.xml');
      $wrapper = str_replace(['@code', '@string'], $error_response, $wrapper);
      $response = new Response($wrapper);
      $response->setStatusCode(500);
      $response->headers->set('Content-type', 'application/xml; charset=utf-8');
      return $response;
    }

    $xml = str_ireplace([
      'SOAPENV:',
      'SOAP:',
      'eu:',
      'SOAP-ENV:',
      'ns1:',
      'xsi:',
      ':ProxyTicket',
    ], '', $request_xml);
    $xml = simplexml_load_string($xml);
    if ((string) $xml->Header->ecas !== 'ticket' && !\Drupal::state()->get('oe_translation_epoetry_mock.bypass_mock_authentication', FALSE)) {
      throw new AccessDeniedHttpException();
    }

    if (!$xml->Body->children()) {
      // @todo handle this.
      throw new NotFoundHttpException();
    }
    $children = (array) $xml->Body->children();
    if (!$children) {
      throw new NotFoundHttpException();
    }

    $method = key($children);
    if (!method_exists($handler, $method)) {
      // @todo handle this.
      throw new NotFoundHttpException();
    }

    $response_object = $handler->{$method}($xml);
    $serializer = new Serializer();
    $xml_string = $serializer->serialize($response_object, 'xml', ['xml_root_node_name' => 'ns0:' . $method . 'Response']);
    $xml_string = trim(str_replace('<?xml version="1.0"?>', '', $xml_string));

    $wrapper = file_get_contents($this->moduleExtensionList->getPath('oe_translation_epoetry_mock') . '/fixtures/response_wrapper.xml');
    $wrapper = str_replace('@response', $xml_string, $wrapper);

    $response = new Response($wrapper);
    $response->headers->set('Content-type', 'application/xml; charset=utf-8');

    return $response;
  }

  /**
   * Notifies a request.
   *
   * Used until we have the notifications endpoint in place.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $oe_translation_request
   *   The ePoetry request entity.
   * @param string $notification
   *   The JSON encoded notification string.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function notify(TranslationRequestEpoetryInterface $oe_translation_request, string $notification, Request $request): RedirectResponse {
    $notification = Json::decode($notification);
    EpoetryTranslationMockHelper::notifyRequest($oe_translation_request, $notification);
    $this->messenger()
      ->addStatus($this->t('The translation request for @label has been updated.',
        [
          '@label' => $oe_translation_request->getContentEntity()->label(),
        ]));
    $destination = $request->query->get('destination');
    if (!$destination) {
      throw new NotFoundHttpException();
    }

    return new RedirectResponse($destination);
  }

}
