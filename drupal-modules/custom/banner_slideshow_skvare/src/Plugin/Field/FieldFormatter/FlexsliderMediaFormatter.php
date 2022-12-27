<?php

namespace Drupal\banner_slideshow_skvare\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\flexslider_fields\Plugin\Field\FieldFormatter\FlexsliderFormatterTrait;

/**
 * Plugin implementation of the '<flexslider>' formatter.
 *
 * @FieldFormatter(
 *   id = "flexslider_media_sk",
 *   label = @Translation("FlexSlider Media"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class FlexsliderMediaFormatter extends EntityReferenceEntityFormatter {
  use FlexsliderFormatterTrait;

  /**
   * An array of elements for the rendering protection.
   *
   * @var array
   */
  protected static $cacheElements = [];

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return self::getDefaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return $this->buildSettingsSummary($this);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return $this->buildSettingsForm($this);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $values = $items->getValue();
    $values = array_column($values, 'target_id');
    $cacheKey = implode('_', $values) . '_' . $langcode;
    if (!isset(static::$cacheElements[$cacheKey])) {
      static::$cacheElements[$cacheKey] = parent::viewElements($items, $langcode);
    }
    $elements = static::$cacheElements[$cacheKey];
    $sliderItems = [];
    foreach ($elements as $delta => $element) {
      $sliderItems[$delta] = [
        'slide' => \Drupal::service('renderer')->render($element),
      ];
    }

    return [
      '#theme'      => 'flexslider',
      '#flexslider' => [
        'settings' => $this->getSettings(),
        'items'    => $sliderItems,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $storage = $field_definition->getFieldStorageDefinition();

    return $storage->isMultiple() && $storage->getSetting('target_type') === 'media';
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    return $dependencies + $this->getOptionsetDependencies($this);
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $changed = parent::onDependencyRemoval($dependencies);

    if ($this->optionsetDependenciesDeleted($this, $dependencies)) {
      $changed = TRUE;
    }

    return $changed;
  }

}
