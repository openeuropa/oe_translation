<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Language list' formatter.
 *
 * Shows the translation request languages with their statuses, as well as
 * operations to review the translation data.
 *
 * @FieldFormatter(
 *   id = "oe_translation_remote_language_list",
 *   label = @Translation("Language list"),
 *   field_types = {
 *     "oe_translation_language_with_status"
 *   }
 * )
 */
class LanguageListFormatter extends FormatterBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $header = [
      $this->t('Language'),
      $this->t('Status'),
      $this->t('Operations'),
    ];

    $rows = [];
    $cache = new CacheableMetadata();

    foreach ($items as $delta => $item) {
      $language = $this->entityTypeManager->getStorage('configurable_language')->load($item->langcode);
      $review = Url::fromRoute('entity.oe_translation_request.remote_translation_review', [
        'oe_translation_request' => $items->getEntity()->id(),
        'language' => $item->langcode,
      ], ['query' => ['destination' => Url::fromRoute('<current>')->toString()]]);
      $review_access = $review->access(NULL, TRUE);
      $cache->addCacheableDependency($review_access);
      $operations = [
        '#type' => 'operations',
        '#links' => [],
      ];

      if ($review_access->isAllowed()) {
        $operations['#links'][] = [
          'title' => $this->t('Review'),
          'url' => $review,
        ];
      }

      $row = [
        'hreflang' => $language->id(),
        'data' => [
          'language' => $language->label(),
          'status' => [
            'data' => [
              '#theme' => 'tooltip',
              '#label' => $item->status,
              '#text' => $items->getEntity()->getLanguageStatusDescription($item->status, $item->langcode),
            ],
          ],
          'operations' => ['data' => $operations],
        ],
      ];

      $rows[] = $row;
    }

    $element = [
      '#theme' => 'table__remote_language_list',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => [
        'class' => ['remote-translation-languages-table'],
        'data-translation-request' => $items->getEntity()->id(),
      ],
    ];

    $cache->applyTo($element);
    return $element;
  }

}
