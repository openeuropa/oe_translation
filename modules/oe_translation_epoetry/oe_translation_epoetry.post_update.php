<?php

/**
 * @file
 * Post update functions for the OE Translation ePoetry module.
 */

use Drupal\views\Entity\View;

/**
 * Update ePoetry translations request dashboard view.
 */
function oe_translation_epoetry_post_update_0001() {
  if (!\Drupal::moduleHandler()->moduleExists('views')) {
    return;
  }

  $view = View::load('epoetry_translation_request_dashboard');
  if (!$view) {
    // It's possible a site has not installed the view or deleted it.
    return;
  }

  /** @var \Drupal\views\ViewEntityInterface $access */
  $display = &$view->getDisplay('default');
  $access = &$display['display_options']['access'];
  if ($access && $access['type'] === 'perm' && $access['options']['perm'] === 'translate any entity') {
    \Drupal::service('plugin.manager.views.access')->clearCachedDefinitions();
    $access['type'] = 'oe_translation_epoetry_translation_requests';
    unset($access['options']);
    $view->save();
  }
}

/**
 * Mark ePoetry translators as enabled.
 */
function oe_translation_epoetry_post_update_0002() {
  /** @var \Drupal\oe_translation_remote\Entity\RemoteTranslatorProviderInterface[] $translators */
  $translators = \Drupal::entityTypeManager()->getStorage('remote_translation_provider')->loadByProperties(['plugin' => 'epoetry']);
  foreach ($translators as $translator) {
    $translator->set('enabled', TRUE);
    $translator->save();
  }
}
