<?php

namespace Drupal\oe_translation;

use Symfony\Component\Routing\RouteCollection;

/**
 * Interface for TMGMT translator plugins that provide routes.
 */
interface RouteProvidingTranslatorInterface {

  /**
   * Returns the route definitions for the plugin.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   The route collection.
   */
  public function getRoutes(): RouteCollection;

}
