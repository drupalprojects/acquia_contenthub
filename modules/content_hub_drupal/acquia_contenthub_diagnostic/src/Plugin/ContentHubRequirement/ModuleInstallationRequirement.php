<?php

namespace Drupal\acquia_contenthub_diagnostic\Plugin\ContentHubRequirement;

use Drupal\acquia_contenthub_diagnostic\ContentHubRequirementBase;

/**
 * Defines a module installation requirement.
 *
 * @ContentHubRequirement(
 *   id = "module_installation",
 *   title = @Translation("Module installation"),
 * )
 */
class ModuleInstallationRequirement extends ContentHubRequirementBase {

  const MODULE_PACKAGE_NAME = 'acquia/content-hub-d8';

  /**
   * The path of the Composer lock file.
   *
   * @var string
   */
  protected $lockFileFilename;

  /**
   * An array of fully qualified names of installed Composer packages.
   *
   * @var string[]
   */
  protected $installedPackages;

  /**
   * {@inheritdoc}
   */
  public function verify() {
    try {
      if ($this->isModuleInstalledViaComposer()) {
        return REQUIREMENT_OK;
      }

      $this->setValue($this->t('Content Hub module not installed via Composer'));
      return REQUIREMENT_ERROR;
    }
    catch (\Exception $e) {
      $this->setValue($this->t('Unable to determine whether or not Content Hub module is installed via Composer'));
      $this->setDescription($e->getMessage());
      return REQUIREMENT_WARNING;
    }
  }

  /**
   * Determines whether or not the module is installed via Composer.
   *
   * @return bool
   *   Returns TRUE if the module is installed via Composer or FALSE if not.
   */
  protected function isModuleInstalledViaComposer() {
    $this->findLockFile();
    $this->parseLockFile();
    return $this->isModuleInInstalledPackages();
  }

  /**
   * Finds the Composer lock file.
   *
   * @throws \Exception
   *   If the lock file cannot be found.
   */
  protected function findLockFile() {
    /** @var \Composer\Autoload\ClassLoader $autoloader */
    $autoloader = require DRUPAL_ROOT . '/autoload.php';
    $prefixes = $autoloader->getPrefixes();
    $first_prefix = reset($prefixes)[0];
    $directory = preg_replace('@' . preg_quote(DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) . '.*@', DIRECTORY_SEPARATOR, $first_prefix);
    $this->lockFileFilename = $directory . 'composer.lock';

    if (!file_exists($this->lockFileFilename)) {
      throw new \Exception($this->t('could not find composer.lock file'));
    }
  }

  /**
   * Parses the lock file to get the list of installed packages.
   *
   * @throws \Exception
   *   If parsing fails at any point.
   */
  protected function parseLockFile() {
    $contents = @file_get_contents($this->lockFileFilename);

    if ($contents === FALSE) {
      throw new \Exception($this->t('could not read composer.lock file'));
    }

    $json = json_decode($contents, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception($this->t('could not parse composer.lock file'));
    }

    if (!isset($json['packages'])) {
      throw new \Exception($this->t('could not determine installed packages'));
    }

    $this->installedPackages = array_column($json['packages'], 'name');
  }

  /**
   * Determines whether or not the module is in the installed packages list.
   *
   * @return bool
   *   Returns TRUE if the module is in the list of installed packages or FALSE
   *   if not.
   */
  protected function isModuleInInstalledPackages() {
    return array_search(static::MODULE_PACKAGE_NAME, $this->installedPackages) !== FALSE;
  }

}
