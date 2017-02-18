<?php
/**
 * @file
 * Provides the ability for migrations to retrieve data files from within a
 * module's 'migration_data' directory.
 *
 * The migrate_plus 'file' data fetcher only deals with absolute paths to
 * files on disk. This data fetcher enables you to be able to put files in a
 * module sub-directory named 'migration_data' and have files loaded from there.
 *
 * To use this plugin requires:
 *
 * a) A 'module_file_modules' migration configuration key specifying the
 * module(s) containing a 'migration_data' directory to load source data files
 * from.
 *
 * b) A 'urls' migration configuration key specifying file(s) relative to the
 * 'migration_data' directory. This key is a requirement of of the parent 'file'
 * plugin.
 *
 * If only one module name is provided, all files will be loaded from within
 * that module. Where more than one module name is provided, each file listed
 * will be paired with the module at the same index and the file loaded from
 * the resulting module/file path.
 */

namespace Drupal\migrate_util\Plugin\migrate_plus\data_fetcher;

use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher\File;

/**
 * @DataFetcher(
 *   id = "module_file",
 *   title = @Translation("Module File")
 * )
 */
class ModuleFile extends File {

  /**
   * Name of the folder we'll look to load data from in a module.
   *
   * This is relative to the module base directory. Eg. For module 'foo' we will
   * look for the 'foo/migration_data' directory.
   */
  const DATA_DIRECTORY_NAME = 'migration_data';

  /**
   * Given file index from 'urls' configuration.
   *
   * @var int
   */
  private $activeFileUrlIndex = 0;

  /**
   * Given file 'URL' from configuration.
   *
   * @var string
   */
  private $activeFileName = '';

  /**
   * Name of the module to load data from within.
   *
   * @var string
   */
  private $activeModuleName = '';

  /**
   * Absolute path to a source data file in a module 'migration_data' directory.
   *
   * @var string
   */
  protected $activeFilePath = '';

  /**
   * List the prefixes that files are not allowed to begin with.
   *
   * @var array
   */
  private static $illegalFilePrefixes = array('..', '/', './', '~', '-');

  /**
   * List the migration configuration keys needed by the plugin.
   */
  private $requiredConfigKeys = array('module_file_modules', 'urls');

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->validatePluginConfig();
  }

  /**
   * Validate plugin config has required values etc.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  private function validatePluginConfig() {
    // Ensure that we've got the required migration configuration key(s).
    foreach ($this->requiredConfigKeys as $key) {
      if (empty($this->configuration[$key])) {
        throw new MigrateException(sprintf("Missing '%s' configuration key/value", $key));
      }
    }

    // Ensure that we'll be dealing with an array of module names from now on,
    // even if there is ony a single item provided in migration configuration.
    if (!is_array($this->configuration['module_file_modules'])) {
      $this->configuration['module_file_modules'] = array(
        $this->configuration['module_file_modules']
      );
    }

    // If there's more than one module name provided, we need to ensure that
    // we've got the same amount of module names as file names, otherwise we
    // won't be able to prefix the file name correctly. If there's only one
    // module name provided, we'll be using that to prefix all files.
    $module_count = count($this->configuration['module_file_modules']);
    $url_count = count($this->configuration['urls']);

    if ($module_count > 1) {
      if ($module_count != $url_count) {
        throw new MigrateException('Number of modules should match the number of urls when more than one module name provided');
      }
    }

    // Unfortunately, enforce unique data file names, due to inability to be
    // able to differentiate files with the same name, in this plugin. Eg.
    //
    // modules/custom/foo/migration_data/file.json
    // modules/custom/bar/migration_data/file.json
    //
    // Would be configured in the migration configuration as:
    //
    // module_file_modules:
    //   - foo
    //   - bar
    //
    // urls:
    //   - file.json
    //   - file.json
    //
    // We cannot know at the time of execution which 'file.json' file we're
    // about to fetch.
    //
    // Workarounds for this are:
    //
    // a) Namespace the file in a 'migration_data' sub-directory named after the
    // module, for example:
    //
    // modules/custom/foo/migration_data/foo/file.json
    // modules/custom/bar/migration_data/bar/file.json
    //
    // Would then be configured as:
    //
    // urls:
    //   - foo/file.json
    //   - bar/file.json
    //
    // b) Make file names unique, with a suffix, or a timestamp, etc.
    //
    // urls:
    //   - file_1.json
    //   - file_2.json
    //
    // If we were passed in the current URL item position from the data parser
    // (its 'activeUrl' property), to getResponseContent(), we could then use
    // it to find the corresponding module name to prefix the file. But we can't
    // currently get that value, and hence this situation.
    if ($url_count > 1) {
      if ($url_count != count(array_unique($this->configuration['urls']))) {
        throw new MigrateException(sprintf("Duplicate file names. Work around this by moving the file to a sub-directory of '%s' named after the module, or, by adding a suffix to the file name", self::DATA_DIRECTORY_NAME));
      }
    }
  }

  /**
   * Set value indicating the given file 'URL's array index.
   */
  private function setActiveFileUrlIndex() {
    $index = 0;

    if($urls_index = array_search($this->getActiveFileName(), $this->configuration['urls'])) {
      $index = $urls_index;
    }

    $this->activeFileUrlIndex = $index;
  }

  /**
   * Set value indicating the given file 'URL'.
   *
   * @param string $file_name
   *   Name of the file we're currently dealing with.
   */
  private function setActiveFileName($file_name) {
    $this->activeFileName = $file_name;
  }

  /**
   * Set value indicating name of the module to use 'migration_data' directory.
   */
  private function setActiveModuleName() {
    $index = 0;

    if (count($this->configuration['module_file_modules']) > 1) {
      $index = $this->getActiveFileUrlIndex();
    }

    $this->activeModuleName = $this->configuration['module_file_modules'][$index];
  }

  /**
   * Calculate absolute path to a file in a module 'migration data' directory.
   */
  protected function setActiveFilePath() {
    $this->activeFilePath = implode(DIRECTORY_SEPARATOR, array(
      DRUPAL_ROOT,
      $this->modulePath($this->getActiveModuleName()),
      self::DATA_DIRECTORY_NAME,
      $this->getActiveFileName()
    ));
  }

  /**
   * Return the position the current 'URL' occupies in the list of 'URL's.
   *
   * @return int
   */
  private function getActiveFileUrlIndex() {
    return $this->activeFileUrlIndex;
  }

  /**
   * Return the name of the data file being fetched.
   *
   * @return string
   */
  public function getActiveFileName() {
    return $this->activeFileName;
  }

  /**
   * Return name of the module we'll be loading data from.
   *
   * @return string
   */
  protected function getActiveModuleName() {
    return $this->activeModuleName;
  }

  /**
   * Return the path to the source file.
   *
   * @return string
   */
  protected function getActiveFilePath() {
    return $this->activeFilePath;
  }

  /**
   * Return list of prefixes 'url's cannot begin with.
   *
   * @return array
   */
  public static function getIllegalFilePrefixes() {
    return self::$illegalFilePrefixes;
  }

  /**
   * Ensure the file path is valid (the file exists).
   *
   * @throws \Drupal\migrate\MigrateException
   */
  private function validateActiveFilePath() {
    $file_path = $this->getActiveFilePath();

    if (!file_exists($file_path)) {
      throw new MigrateException(sprintf('Data source file path %s does not exist', $file_path));
    }
  }

  /**
   * Ensure that the active module name is actually an enabled module.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  private function validateModuleName() {
    $module_name = $this->getActiveModuleName();

    if (!$this->moduleExists($module_name)) {
      throw new MigrateException(sprintf('Invalid module name provided: %s', $module_name));
    }
  }

  /**
   * Prevent attempting to load files outside of the 'migration_data' folder.
   *
   * Throw an exception when trying to use a path value that is not just in a
   * straight up 'filename.json' or 'sub-directory/filename.xml' type format.
   *
   * @param string $path
   *   Path to source data file that will be attempted to be loaded.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  private function validateFilePrefix($path) {
    foreach (self::$illegalFilePrefixes as $prefix) {
      if (substr($path, 0, mb_strlen($prefix)) === $prefix) {
        throw new MigrateException(sprintf('Illegal file path prefix detected: %s', $prefix));
      }
    }
  }

  /**
   * Indicate if a named module is present and enabled, or not.
   *
   * @param string $module_name
   *   Name of a Drupal module.
   *
   * @return bool
   *   TRUE if named module exists, and is enabled, otherwise FALSE.
   */
  protected function moduleExists($module_name) {
    return \Drupal::moduleHandler()->moduleExists($module_name);
  }

  /**
   * Get the directory path to a module.
   *
   * @param string $module_name
   *   Name of a Drupal module.
   *
   * @return string
   *   Path to the named module, or an empty string.
   */
  protected function modulePath($module_name) {
    return drupal_get_path('module', $module_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getResponseContent($url) {
    $this->determineModuleFilePath($url);

    return parent::getResponseContent($this->getActiveFilePath());
  }

  /**
   * Ensure we're in a position to find a file in a module 'migration_data' dir.
   *
   * @param $file_name
   *   The currently 'to be loaded' file URL from migration configuration.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  protected function determineModuleFilePath($file_name) {
    $this->validateFilePrefix($file_name);
    $this->setActiveFileName($file_name);
    $this->setActiveFileUrlIndex();
    $this->setActiveModuleName();
    $this->validateModuleName();
    $this->setActiveFilePath();
    $this->validateActiveFilePath();
  }

}
