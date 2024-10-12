<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_epoetry\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;
use Drupal\oe_translation_epoetry\NotificationEndpointResolver;

/**
 * Tests the ePoetry notification callback resolver.
 *
 * @group batch1
 */
class NotificationsResolverTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_epoetry',
    'oe_translation_remote',
  ];

  /**
   * Tests the notification endpoint resolver.
   */
  public function testNotificationEndpointResolver(): void {
    $expected = Url::fromRoute('oe_translation_epoetry.notifications_endpoint')->setAbsolute()->toString(TRUE)->getGeneratedUrl();
    $this->assertEquals($expected, NotificationEndpointResolver::resolve());

    $settings = Settings::getAll();
    $settings['epoetry.notification.endpoint_prefix'] = 'http://example.com';
    new Settings($settings);

    $this->assertEquals('http://example.com/localhost/translation-request/epoetry/notifications', NotificationEndpointResolver::resolve());
  }

}
