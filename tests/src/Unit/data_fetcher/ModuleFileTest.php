<?php
/**
 * @file
 * PHPUnit tests for the ModuleFile Migrate Plus 'data fetcher' plugin.
 *
 * Before being able to run these, you need to have installed the development
 * dependencies:
 *
 * @code
 *   composer install --dev
 * @endcode
 *
 * Run all tests in the group:
 *
 * @code
 *   drupal8/core$ ../vendor/bin/phpunit --group=migrate_util
 * @endcode
 *
 * Run specific tests:
 *
 * @code
 *   drupal8/core$ ../vendor/bin/phpunit --verbose ../modules/custom/migrate_util/tests/src/Unit/data_fetcher/ModuleFileTest.php
 * @endcode
 *
 * @see https://www.drupal.org/docs/8/phpunit/running-phpunit-tests
 */

namespace Drupal\Tests\migrate_util\Unit\data_fetcher;

use Drupal\migrate_util\Plugin\migrate_plus\data_fetcher\ModuleFile;
use Drupal\Tests\migrate\Unit\MigrateTestCase;
use org\bovigo\vfs\vfsStream;

/**
 * @coversDefaultClass \Drupal\migrate_util\Plugin\migrate_plus\data_fetcher\ModuleFile
 *
 * @group migrate_util
 */
class ModuleFileTest extends MigrateTestCase {

  /**
   * Name of the virtual directory test modules will exist in.
   */
  const MODULES_BASE_DIRECTORY = 'modules';

  /**
   * Example migration configuration template.
   *
   * Specifies majority of properties needed for most tests. Each separate test
   * then modifies it to meet the criteria that's being tested.
   *
   * @var array
   */
  private $specificMigrationConfig = [
    'source' => 'url',
    'data_fetcher_plugin' => 'module_file',
    'data_parser_plugin' => 'json',
    'item_selector' => 0,
    'fields' => [
      [
        'name' => 'id',
        'label' => 'Row identifier',
        'selector' => 'id',
      ],
      [
        'name' => 'name',
        'label' => 'Persons name',
        'selector' => 'name',
      ],
    ],
    'ids' => [
      'id' => [
        'type' => 'integer'
      ],
    ],
  ];

  /**
   * The data fetcher plugin ID being tested.
   *
   * @var string
   */
  private $dataFetcherPluginId = 'module_file';

  /**
   * The data fetcher plugin definition.
   *
   * @var array
   */
  private $pluginDefinition = [
    'id' => 'module_file',
    'title' => 'Module File'
  ];

  /**
   * Notionally existing module names.
   *
   * @var array
   */
  private $modules = [
    'migrate_util_test_1',
    'migrate_util_test_2',
  ];

  /**
   * Notionally non-existing module names.
   *
   * @var array
   */
  public static $missingModules = ['migrate_util_test_missing_1'];

  /**
   * Notionally existing migration data file names.
   *
   * @var array
   */
  private $dataFiles = [
    'data_file_1.json',
    'data_file_2.json',
  ];

  /**
   * Some example JSON formatted data source file content.
   *
   * @var string
   */
  private $json = '[
    {"id": 1, "name": "Joe Bloggs"},
    {"id": 2, "name": "John Smith"},
    {"id": 3, "name": "Alex Sansom"}
  ]';

  /**
   * Notionally non-existing migration data file names.
   */
  private $missingDataFiles = ['non_existent_data.json'];

  /**
   * Define the virtual dir where we'll modules will be 'installed'.
   *
   * @var \org\bovigo\vfs\vfsStreamDirectory
   */
  private $modulesBaseDir;

  /**
   * Set up test environment.
   */
  public function setUp()
  {
    $this->modulesBaseDir = vfsStream::setup(self::MODULES_BASE_DIRECTORY);
  }

  /**
   * Test missing migration configuration keys.
   *
   * @expectedException \Drupal\migrate\MigrateException
   */
  public function testPluginMissingRequiredConfigKeys() {
    $migration_config = $this->specificMigrationConfig;

    // Missing configuration 'module_file_modules' and 'urls' key/values.

    $plugin = new ModuleFile(
      $migration_config,
      $this->dataFetcherPluginId,
      $this->pluginDefinition
    );
  }

  /**
   * Test migration configuration keys provided but empty.
   *
   * @expectedException \Drupal\migrate\MigrateException
   */
  public function testPluginEmptyRequiredConfigKeys() {
    $migration_config = $this->specificMigrationConfig;

    // Provide empty 'module_file_modules' and 'urls' key/values.
    $migration_config = $this->specificMigrationConfig + [
      'module_file_modules' => '',
      'urls' => [],
    ];

    $plugin = new ModuleFile(
      $migration_config,
      $this->dataFetcherPluginId,
      $this->pluginDefinition
    );
  }

  /**
   * Test valid migration config specifying one module and one 'URL' (file).
   */
  public function testPluginConfigOneModuleOneFile() {
    $migration_config = $this->specificMigrationConfig + [
      'module_file_modules' => $this->modules[0],
      'urls' => [
         $this->dataFiles[0],
       ],
    ];

    // Exception will be thrown for an invalid migration configuration.
    $plugin = new ModuleFile(
      $migration_config,
      $this->dataFetcherPluginId,
      $this->pluginDefinition
    );

    // Or, we'll get back an instance of the data fetcher plugin class.
    $this->assertInstanceOf(ModuleFile::class, $plugin);
  }

  /**
   * Test migration config specifying one module and one 'URL' (file), but the
   * module is not enabled/does not exist.
   *
   * @expectedException \Drupal\migrate\MigrateException
   */
  public function testPluginConfigModuleInvalid() {
    $file_name = $this->dataFiles[0];

    $migration_config = $this->specificMigrationConfig + [
      'module_file_modules' => self::$missingModules[0],
      'urls' => [
        $file_name,
      ],
    ];

    // Note that we're instantiating TestModuleFile, not just ModuleFile as we
    // need the overridden module existence/path related methods.
    $plugin = new TestModuleFile(
      $migration_config,
      $this->dataFetcherPluginId,
      $this->pluginDefinition
    );

    // Trigger trying to load source file from a non-existing module directory.
    $data = $plugin->getResponseContent($file_name);
  }

  /**
   * Test migration config specifying one module and one 'URL' (file), but the
   * specified file does not exist.
   *
   * @expectedException \Drupal\migrate\MigrateException
   */
  public function testPluginConfigFileInvalid() {
    $module_name = $this->modules[0];
    $missing_file_name = $this->missingDataFiles[0];

    $migration_config = $this->specificMigrationConfig + [
      'module_file_modules' => $module_name,
      'urls' => [
        $missing_file_name,
      ],
    ];

    $plugin = new TestModuleFile(
      $migration_config,
      $this->dataFetcherPluginId,
      $this->pluginDefinition
    );

    $tree = array(
      $module_name => array(
        ModuleFile::DATA_DIRECTORY_NAME => array(),
      ),
    );

    // Create the module virtual directory tree so that the only thing missing
    // is the file itself, which will trigger an exception.
    vfsStream::create($tree, $this->modulesBaseDir);

    // Trigger trying to load non-existent source file.
    $data = $plugin->getResponseContent($missing_file_name);
  }

  /**
   * Test migration config specifying one module and one 'URL' (file), but the
   * file begins with illegal char(s).
   *
   * @param string $prefix
   *   A(n illegal) file prefix to test.
   *
   * @dataProvider dataProviderFilePrefixInvalid
   *
   * @expectedException \Drupal\migrate\MigrateException
   */
  public function testPluginConfigFilePrefixInvalid($prefix) {
    $module_name = $this->modules[0];
    $illegal_file_name = $prefix;

    if (substr($prefix, mb_strlen($prefix) - 1, 1) !== '/') {
      $illegal_file_name .= '/';
    }

    $illegal_file_name .= $this->dataFiles[0];

    $migration_config = $this->specificMigrationConfig + [
      'module_file_modules' => $module_name,
      'urls' => [
        $illegal_file_name,
      ],
    ];

    $plugin = new ModuleFile(
      $migration_config,
      $this->dataFetcherPluginId,
      $this->pluginDefinition
    );

    $tree = array(
      $module_name => array(
        ModuleFile::DATA_DIRECTORY_NAME => array(),
      ),
    );

    vfsStream::create($tree, $this->modulesBaseDir);

    $data = $plugin->getResponseContent($illegal_file_name);
  }

  /**
   * Get the list of invalid file prefixes to test against.
   */
  public function dataProviderFilePrefixInvalid() {
    return [ModuleFile::getIllegalFilePrefixes()];
  }

  /**
   * Test migration config specifying one module and multiple different 'URL's
   * (files).
   */
  public function testPluginConfigOneModuleMultiDifferentFiles() {
    $migration_config = $this->specificMigrationConfig + [
      'module_file_modules' => $this->modules[0],
      'urls' => [
        $this->dataFiles[0],
        $this->dataFiles[1],
      ],
    ];

    $plugin = new ModuleFile(
      $migration_config,
      $this->dataFetcherPluginId,
      $this->pluginDefinition
    );

    $this->assertInstanceOf(ModuleFile::class, $plugin);
  }

  /**
   * Test migration config specifying multiple modules and multiple 'URL's
   * (files).
   */
  public function testPluginConfigMultiModuleMultiDifferentFiles() {
    $migration_config = $this->specificMigrationConfig + [
      'module_file_modules' => [
        $this->modules[0],
        $this->modules[1],
      ],
      'urls' => [
        $this->dataFiles[0],
        $this->dataFiles[1],
      ],
    ];

    $plugin = new ModuleFile(
      $migration_config,
      $this->dataFetcherPluginId,
      $this->pluginDefinition
    );

    $this->assertInstanceOf(ModuleFile::class, $plugin);
  }

  /**
   * Test migration config specifying multiple modules with only a single 'URL'
   * (file).
   *
   * @expectedException \Drupal\migrate\MigrateException
   */
  public function testPluginConfigMultiModuleOneFile() {
    $migration_config = $this->specificMigrationConfig + [
      'module_file_modules' => [
        $this->modules[0],
        $this->modules[1],
      ],
      'urls' => [
        $this->dataFiles[0],
      ],
    ];

    $plugin = new ModuleFile(
      $migration_config,
      $this->dataFetcherPluginId,
      $this->pluginDefinition
    );
  }

  /**
   * Test migration config specifying multiple modules and duplicated 'URL's
   * (files).
   *
   * This is illegal as we cannot differentiate the currently processed file
   * from any previous or future one (currently).
   *
   * @expectedException \Drupal\migrate\MigrateException
   */
  public function testPluginConfigMultiModuleDuplicateFiles() {
    $migration_config = $this->specificMigrationConfig + [
      'module_file_modules' => [
        $this->modules[0],
        $this->modules[1],
      ],
      'urls' => [
        $this->dataFiles[0],
        $this->dataFiles[0],
      ],
    ];

    $plugin = new ModuleFile(
      $migration_config,
      $this->dataFetcherPluginId,
      $this->pluginDefinition
    );
  }

  /**
   * Test, given a valid configuration, a source data file is actually fetched/
   * delivered.
   */
  public function testPluginFetchesFileGivenValidConfig() {
    $module_name = $this->modules[0];
    $file_name = $this->dataFiles[0];

    $migration_config = $this->specificMigrationConfig + [
      'module_file_modules' => $module_name,
      'urls' => [
        $file_name,
      ],
    ];

    $plugin = new TestModuleFile(
      $migration_config,
      $this->dataFetcherPluginId,
      $this->pluginDefinition
    );

    $tree = array(
      $module_name => array(
        ModuleFile::DATA_DIRECTORY_NAME => array(
          $file_name => $this->json,
        ),
      ),
    );

    vfsStream::create($tree, $this->modulesBaseDir);

    $data = $plugin->getResponseContent($file_name);

    // Compare fetched/loaded source file content with a known data structure,
    $file_JSON = json_decode($data, TRUE);
    $reference_JSON = json_decode($this->json, TRUE);

    $this->assertEquals($file_JSON, $reference_JSON);
  }

}