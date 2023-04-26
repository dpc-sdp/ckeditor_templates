<?php

namespace Drupal\ckeditor_templates;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * CkeditorTemplate plugin manager.
 */
class CkeditorTemplatePluginManager extends DefaultPluginManager {

  /**
   * Constructs CkeditorTemplatePluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/CkeditorTemplate',
      $namespaces,
      $module_handler,
      \Drupal\ckeditor_templates\CkeditorTemplateInterface::class,
      \Drupal\ckeditor_templates\Annotation\CkeditorTemplate::class
    );
    $this->alterInfo('ckeditor_template_info');
    $this->setCacheBackend($cache_backend, 'ckeditor_template_plugins');
  }

  /**
   * Get all available templates.
   *
   * @return CkeditorTemplateInterface[]
   */
  public function getTemplates() {
    $templates = [];
    $template_definitions = $this->getDefinitions();
    uasort($template_definitions, [\Drupal\Component\Utility\SortArray::class, 'sortByWeightElement']);

    foreach ($template_definitions as $id => $definition) {
      $templates[$id] = $this->createInstance($id);
    }

    return $templates;
  }

}
