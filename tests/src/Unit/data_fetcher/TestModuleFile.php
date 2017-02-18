<?php
/**
 * @file
 * Extends ModuleFile class to enable unit testing.
 */

namespace Drupal\Tests\migrate_util\Unit\data_fetcher;

use Drupal\migrate_util\Plugin\migrate_plus\data_fetcher\ModuleFile;
use org\bovigo\vfs\vfsStream;

/**
 * Confusingly named class name is as per the Drupal unit testing docs at:
 *
 * https://www.drupal.org/docs/8/phpunit/unit-testing-more-complicated-drupal-classes
 */
class TestModuleFile extends ModuleFile {

  /**
   * Override parent::moduleExists($module_name).
   *
   * So we don't use the Drupal function to lookup if the module is exists and
   * is enabled.
   *
   * @param string $module_name
   *   Name of a Drupal module.
   *
   * @return bool
   *   Boolean indicating if a module exists and is enabled.
   */
  protected function moduleExists($module_name) {
    if (in_array($module_name, ModuleFileTest::$missingModules)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Override parent::modulePath($module_name).
   *
   * So we don't use the Drupal function to get a module path.
   *
   * @param string $module_name
   *   Name of a Drupal module.
   *
   * @return string
   *   Path to where the module is installed.
   */
  protected function modulePath($module_name) {
    return $module_name;
  }

  /**
   * Override parent::setActiveFilePath()
   *
   * So we use the virtual file system path.
   */
  protected function setActiveFilePath() {
    $file_path = implode(DIRECTORY_SEPARATOR, array(
      ModuleFileTest::MODULES_BASE_DIRECTORY,
      $this->modulePath($this->getActiveModuleName()),
      ModuleFile::DATA_DIRECTORY_NAME,
      $this->getActiveFileName(),
    ));

    $this->activeFilePath = vfsStream::url($file_path);
  }

}