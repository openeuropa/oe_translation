<?php

/**
 * @file
 * OpenEuropa Translation Poetry post updates file.
 */

declare(strict_types = 1);

use Drupal\tmgmt\Entity\Translator;

/**
 * Removing the poetry endpoint from the configuration.
 */
function oe_translation_poetry_post_update_remove_service_wsdl(): void {
  $translator = Translator::load('poetry');
  if (!$translator) {
    return;
  }

  $settings = $translator->getSettings();
  if (!isset($settings['service_wsdl'])) {
    return;
  }

  // Remove the old service WSDL from the configuration to keep it inline
  // with the schema.
  unset($settings['service_wsdl']);
  $translator->setSettings($settings);
  $translator->save();
}
