<?php

namespace Drupal\block_panelizer_usage\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Link;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("block_panelizer_usage__default_view_modes")
 */
class block_panelizer_usage__default_view_modes extends FieldPluginBase {

  public $block_panelizer_usage_displays;
  public $bundle_info;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityManager = \Drupal::entityManager();
    $this->panelizer = \Drupal::service('panelizer');
    $this->configFactory = \Drupal::service('config.factory');
    $this->block_panelizer_usage_displays = $this->getPanelizeredDisplays();
    $this->bundle_info = $this->entityManager->getAllBundleInfo();
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['hide_alter_empty'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $plugin_uuid = $values->_entity->get('uuid')->getString();
    $report = [];
    foreach ($this->block_panelizer_usage_displays as $panelizered_display) {
      $config = $panelizered_display->getConfiguration();
      if (!empty($config['blocks'])) {
        foreach ($config['blocks'] as $panel_uuid => $block) {
          $block_uuid_array = explode(':', $block['id']);
          $this_block_uuid = end($block_uuid_array);
          if ($this_block_uuid == $plugin_uuid) {
            list($entity_type, $bundle, $view_mode) = explode(':', $config['storage_id']);
            $bundle_info = $this->bundle_info;
            $route_machine_name = str_replace(':', '__', $config['storage_id']);
            $link_render = Link::createFromRoute(
              $bundle_info[$entity_type][$bundle]['label'],
              'panelizer.wizard.edit',
              ['machine_name' => $route_machine_name,
                'step' => 'content']
            )->toRenderable();
            $report[] = render($link_render);
          }
        }
      }
    }
    if (!empty($report)) {
      return ['#markup' => implode(', ', $report)];
    }
  }

  public function getPanelizeredDisplays() {
    // Loop through node types and get their enabled display modes that are panelizered.
    $panel_displays = [];

    foreach ($this->entityManager->getStorage('node_type')->loadMultiple() as $node_type) {
      $values = [
        'targetEntityType' => 'node',
        'bundle' => $node_type->toArray()['type'],
        'status' => TRUE,
      ];
      $entity_view_display = EntityViewDisplay::create($values);

      // Loop through the enabled view modes.
      foreach ($this->getEntityDisplays($entity_view_display) as $id => $view_display_mode) {
        list($entity_type, $bundle, $view_mode) = explode('.', $view_display_mode->id());
        // Only load displays that have been panelizered.
        if ($panel_display_array = $this->panelizer->getDefaultPanelsDisplays($entity_type, $bundle, $view_mode)) {
          $panel_displays[] = array_values($panel_display_array)[0];
        }
      }
    }
    return $panel_displays;
  }

  public function getEntityDisplays(EntityViewDisplayInterface $entity) {
    $load_ids = [];
    $display_entity_type = $entity->getEntityTypeId();
    $entity_type = $this->entityManager->getDefinition($display_entity_type);
    $config_prefix = $entity_type->getConfigPrefix();
    $ids = $this->configFactory->listAll($config_prefix . '.' . $entity->getTargetEntityTypeId() . '.' . $entity->getTargetBundle() . '.');
    foreach ($ids as $id) {
      $config_id = str_replace($config_prefix . '.', '', $id);
      list(,, $display_mode) = explode('.', $config_id);
      if ($display_mode != 'default') {
        $load_ids[] = $config_id;
      }
    }
    return $this->entityManager->getStorage($display_entity_type)->loadMultiple($load_ids);
  }

}

