<?php

namespace Drupal\block_panelizer_usage\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Link;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("block_panelizer_usage__block_regions")
 */
class block_panelizer_usage__block_regions extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityManager = \Drupal::entityManager();
    $this->theme_manager = \Drupal::service('theme.manager');
    $this->theme_handler = \Drupal::service('theme_handler');
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
    $options['theme_report'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $theme_checkboxes = [];
    foreach ($this->theme_handler->listInfo() as $theme_name => $theme) {
      $theme_checkboxes[$theme_name] = $theme->getName();
    }

    $form['theme_report'] = [
      '#type' => 'select',
      '#title' => t('Choose the theme from which to load blocks for this report.'),
      '#weight' => -30,
      '#optional' => FALSE,
      '#default_value' => $this->options['theme_report'],
      '#options' => ['' => ''] + $theme_checkboxes
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $plugin_uuid = $values->_entity->get('uuid')->getString();

    // Change active theme to the selected one to get a block list from container.
    $actual_current_theme = $this->theme_manager->getActiveTheme();
    $theme_initializer = \Drupal::service('theme.initialization');
    $this->theme_manager->setActiveTheme($theme_initializer->getActiveThemeByName($this->options['theme_report']));

    // Get all enabled blocks in this theme.
    $enabled_blocks = $this->entityManager->getListBuilder('block')->load();
    // Set the active theme back.
    $this->theme_manager->setActiveTheme($actual_current_theme);
    $regions = system_region_list($this->options['theme_report']);
    $report = [];
    foreach ($enabled_blocks as $block) {
      $this_plugin_uuid_array = explode(':', $block->getPlugin()->pluginId);
      $this_plugin_uuid = end($this_plugin_uuid_array);
      if ($plugin_uuid == $this_plugin_uuid) {
        $region_name = $regions[$block->getRegion()]->__toString();
        $link_render = Link::createFromRoute($region_name, 'block.admin_display_theme', ['theme' => $this->options['theme_report']])->toRenderable();
        $report[] = render($link_render);
      }
    }
    if (!empty($report)) {
      return ['#markup' => implode(', ', $report)];
    }
  }
}
