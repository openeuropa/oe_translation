<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_active_revision_link_lists_test\Plugin\LinkDisplay;

use Drupal\Core\Link;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkDisplayPluginBase;

/**
 * Title display of link list links.
 *
 * Renders a simple list of links.
 *
 * @LinkDisplay(
 *   id = "oe_translation_active_revision_link_lists_test_teaser",
 *   label = @Translation("Active Revision Test Teaser"),
 *   description = @Translation("Simple easer link list."),
 *   bundles = { "dynamic", "manual" }
 * )
 */
class Teaser extends LinkDisplayPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(LinkCollectionInterface $links): array {
    $items = [];
    foreach ($links as $link) {
      $items[] = [
        '#type' => 'container',
        0 => Link::fromTextAndUrl($link->getTitle(), $link->getUrl())->toRenderable(),
        1 => $link->getTeaser(),
      ];
    }

    $build = [];

    $build['list'] = [
      '#theme' => 'item_list__title_link_display_plugin',
      '#items' => $items,
      '#title' => $this->configuration['title'],
    ];

    return $build;
  }

}
