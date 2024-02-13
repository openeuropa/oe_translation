<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry;

use Drupal\Core\Site\Settings;
use Drupal\Core\Url;

/**
 * Resolves the correct endpoint for ePoetry notifications.
 */
class NotificationEndpointResolver {

  /**
   * Resolves the ePoetry notification endpoint.
   *
   * The endpoint is by default on the current site. However, we can have a
   * setting that adds a prefix so that the endpoint is on another site which
   * reroutes the requests back to the current site. For example, by default
   * the endpoint would be
   * https://example.com/translation-request/epoetry/notifications?wsdl.
   *
   * With the prefix, it becomes
   * https://prefix-path.com/example.com/translation-request/epoetry/notifications?wsdl.
   *
   * @return string
   *   The endpoint URL.
   */
  public static function resolve(): string {
    $prefix = Settings::get('epoetry.notification.endpoint_prefix');
    $url = Url::fromRoute('oe_translation_epoetry.notifications_endpoint')->setAbsolute();
    if (!$prefix) {
      // If we don't have a prefix, we default to the current site endpoint.
      return $url->toString(TRUE)->getGeneratedUrl();
    }

    // Otherwise, we prefix it with another site endpoint so it passes through
    // it before being routed back to the current site.
    $prefix = trim($prefix, '/') . '/';
    $parts = parse_url($url->toString(TRUE)->getGeneratedUrl());
    if (isset($parts['scheme'])) {
      unset($parts['scheme']);
    }

    $url = http_build_url($parts);
    return $prefix . $url;
  }

}
