<?php

declare(strict_types=1);

namespace Drupal\oe_translation\TranslationSourceFieldProcessor;

use CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;
use Drupal\address\FieldHelper;
use Drupal\address\LabelHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Translation source field processor for the Address field type.
 *
 * The address field comes with different labels depending on the address format
 * so we need to change the default property labels when we translate.
 */
class AddressFieldProcessor extends DefaultFieldProcessor implements ContainerInjectionInterface {

  /**
   * The address format repository.
   *
   * @var \CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface
   */
  protected $addressFormatRepository;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $configFactory, AddressFormatRepositoryInterface $addressFormatRepository) {
    parent::__construct($configFactory);
    $this->addressFormatRepository = $addressFormatRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('address.address_format_repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function extractTranslatableData(FieldItemListInterface $field): array {
    $data = parent::extractTranslatableData($field);

    foreach (Element::children($data) as $delta) {
      $country_code = $field->get($delta)->country_code;
      $address_format = $this->addressFormatRepository->get($country_code);
      $labels = LabelHelper::getFieldLabels($address_format);
      $processed_labels = [];
      foreach ($labels as $address_part_name => $label) {
        $property_name = FieldHelper::getPropertyName($address_part_name);
        if ($property_name) {
          $processed_labels[$property_name] = $label;
        }
      }

      foreach ($data[$delta] as $address_part_name => &$address_part) {
        if (isset($processed_labels[$address_part_name])) {
          $address_part['#label'] = $processed_labels[$address_part_name];
        }
      }
    }

    return $data;
  }

}
