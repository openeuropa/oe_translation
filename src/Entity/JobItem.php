<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Entity;

use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\tmgmt\Entity\JobItem as OriginalJobItem;

/**
 * Override of the original TMGMT Job Item entity class.
 */
class JobItem extends OriginalJobItem {

  /**
   * {@inheritdoc}
   *
   * We need to override this method because it makes a hard assumption on
   * the translation being saved on the latest loaded revision. And as such,
   * if we save the translation on a previous revision, the message building
   * logic breaks. So we need to fix this.
   */
  public function accepted($message = NULL, $variables = [], $type = 'status') {
    if (!isset($message)) {
      $message_info = $this->generateAcceptedMessage();
      return parent::accepted($message_info['message'], $message_info['variables'], $type);
    }

    return parent::accepted($message, $variables, $type);
  }

  /**
   * Creates the message for when a translation is accepted.
   *
   * We use the EntitySourceTranslationInfoInterface to determine the entity
   * into which the translation was saved to generate the message.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function generateAcceptedMessage(): array {
    $source_url = $this->getSourceUrl();

    // Load the entity onto which the translation was supposedly saved.
    $entity = NULL;
    $translation = NULL;
    $plugin_definition = $this->getSourcePlugin()->getPluginDefinition();

    if (isset($plugin_definition['entity_translation_info'])) {
      /** @var \Drupal\oe_translation\EntitySourceTranslationInfoInterface $translation_info */
      $translation_info = \Drupal::service($plugin_definition['entity_translation_info']);
      /** @var \Drupal\Core\Entity\TranslatableInterface $entity */
      $entity = $translation_info->getEntityFromJobItem($this);
      $translation = $entity->hasTranslation($this->getJob()->getTargetLangcode()) ? $entity->getTranslation($this->getJob()->getTargetLangcode()) : NULL;
    }

    if (!$entity instanceof TranslatableInterface || !$translation instanceof TranslatableInterface) {
      return [
        'message' => $source_url ? 'The translation for <a href=":source_url">@source</a> has been accepted.' : 'The translation for @source has been accepted.',
        'variables' => $source_url ? [
          ':source_url' => $source_url->toString(),
          '@source' => $this->getSourceLabel(),
        ] : ['@source' => $this->getSourceLabel()],
      ];
    }

    try {
      $translation_url = $translation->toUrl();
    }
    catch (UndefinedLinkTemplateException $e) {
      $translation_url = NULL;
    }

    return [
      'message' => $source_url && $translation_url ? 'The translation for <a href=":source_url">@source</a> has been accepted as <a href=":target_url">@target</a>.' : 'The translation for @source has been accepted as @target.',
      'variables' => $translation_url ? [
        ':source_url' => $source_url->toString(),
        '@source' => $this->getSourceLabel(),
        ':target_url' => $translation_url->toString(),
        '@target' => $translation ? $translation->label() : $this->getSourceLabel(),
      ] : ['@source' => $this->getSourceLabel(), '@target' => ($translation ? $translation->label() : $this->getSourceLabel())],
    ];
  }

}
