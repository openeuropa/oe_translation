<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\oe_translation_poetry\Poetry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
   * NotificationsController constructor.
   *
   * @param \Drupal\oe_translation_poetry\Poetry $poetry
   *   The Poetry service.
   * @param \Symfony\Component\EventDispatcher\EventSubscriberInterface $notificationSubscriber
   *   The notification subscriber.
   */
  public function __construct(Poetry $poetry, EventSubscriberInterface $notificationSubscriber) {
    $this->poetry = $poetry;
    $this->notificationSubscriber = $notificationSubscriber;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_translation_poetry.client.default'),
      $container->get('oe_translation_poetry.notification_subscriber')
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
