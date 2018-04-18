<?php

namespace Drupal\block_panelizer_usage\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Link;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * A handler to provide a custom field to be used in listing of custom blocks.
 * This view field prints a list of blocks as they're used in site regions.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("block_panelizer_usage__block_regions")
 */
class block_panelizer_usage__block_regions extends FieldPluginBase {

  public $regions;
  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityManager = \Drupal::service('entity.manager');
    $this->theme_manager = \Drupal::service('theme.manager');
    $this->theme_handler = \Drupal::service('theme_handler');
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    // Set cache tags for this view. See @block_panelizer_usage_entity_presave().
    $view->element['#cache']['tags'][] = BLOCK_PANELIZER_USAGE_CACHE_TAG_BLOCK_REGIONS;
    $this->regions = system_region_list($this->options['theme_report']);
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

    // Prepare the report for blocks that match the current block.
    $report = [];
    foreach ($this->getEnabledBlocks() as $block) {
      $this_plugin_uuid_array = explode(':', $block->getPlugin()->pluginId);
      $this_plugin_uuid = end($this_plugin_uuid_array);
      if ($plugin_uuid == $this_plugin_uuid) {
        $region_name = $this->regions[$block->getRegion()]->__toString();
        $link_render = Link::createFromRoute($region_name, 'block.admin_display_theme', ['theme' => $this->options['theme_report']])->toRenderable();
        $report[] = render($link_render);
      }
    }

    if (!empty($report)) {
      return ['#markup' => implode(', ', $report)];
    }
  }

  /**
   * Returns the array of enabled blocks from the theme set in the view options.
   *
   * @return array
   */
  protected function getEnabledBlocks() {

    // Change active theme to the selected one to get a block list from container.
    $actual_current_theme = $this->theme_manager->getActiveTheme();
    $theme_initializer = \Drupal::service('theme.initialization');
    $this->theme_manager->setActiveTheme($theme_initializer->getActiveThemeByName($this->options['theme_report']));
    // Get all enabled blocks in this theme.
    $enabled_blocks = $this->entityManager->getListBuilder('block')->load();
    // Set the active theme back.
    $this->theme_manager->setActiveTheme($actual_current_theme);

    return $enabled_blocks;
  }
}
