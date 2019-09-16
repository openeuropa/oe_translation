<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\oe_translation_poetry\Poetry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles the notifications coming from Poetry.
 */
class NotificationsController extends ControllerBase {

  /**
   * The Poetry service.
   *
   * @var \Drupal\oe_translation_poetry\Poetry
   */
  protected $poetry;

  /**
   * The notification subscriber.
   *
   * @var \Drupal\oe_translation_poetry\EventSubscriber\PoetryNotificationSubscriber
   */
  protected $notificationSubscriber;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * NotificationsController constructor.
   *
   * @param \Drupal\oe_translation_poetry\Poetry $poetry
   *   The Poetry service.
   * @param \Symfony\Component\EventDispatcher\EventSubscriberInterface $notificationSubscriber
   *   The notification subscriber.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Tbe logger factory.
   */
  public function __construct(Poetry $poetry, EventSubscriberInterface $notificationSubscriber, RequestStack $requestStack, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->poetry = $poetry;
    $this->notificationSubscriber = $notificationSubscriber;
    $this->requestStack = $requestStack;
    $this->logger = $loggerChannelFactory->get('oe_translation_poetry');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_translation_poetry.client.default'),
      $container->get('oe_translation_poetry.notification_subscriber'),
      $container->get('request_stack'),
      $container->get('logger.factory')
    );
  }

  /**
   * Soap server handling Poetry notifications.
   */
  public function handle(): Response {
    $this->poetry->getEventDispatcher()->addSubscriber($this->notificationSubscriber);
    $server = $this->poetry->getServer();

    ob_start();
    $server->handle();
    $result = ob_get_contents();
    ob_end_clean();

    $response = new Response($result);
    $response->headers->set('Content-type', 'application/xml; charset=utf-8');
    return $response;
  }

}
