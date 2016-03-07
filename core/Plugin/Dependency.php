<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugin;

use Composer\Semver\VersionParser;
use Piwik\Common;
use Piwik\Plugin\Manager as PluginManager;
use Piwik\Version;

/**
 *
 */
class Dependency
{
    private $piwikVersion;

    public function __construct()
    {
        $this->setPiwikVersion(Version::VERSION);
    }

    public function getMissingDependencies($requires)
    {
        $missingRequirements = array();

        if (empty($requires)) {
            return $missingRequirements;
        }

        foreach ($requires as $name => $requiredVersion) {
            $currentVersion  = $this->getCurrentVersion($name);
            $missingVersions = $this->getMissingVersions($currentVersion, $requiredVersion);

            if (!empty($missingVersions)) {
                $missingRequirements[] = array(
                    'requirement'     => $name,
                    'actualVersion'   => $currentVersion,
                    'requiredVersion' => $requiredVersion,
                    'causedBy'        => implode(', ', $missingVersions)
                );
            }
        }

        return $missingRequirements;
    }

    public function getMissingVersions($currentVersion, $requiredVersion)
    {
        $currentVersion = trim($currentVersion);

        $missingVersions = array();

        if (empty($currentVersion)) {
            if (!empty($requiredVersion)) {
                $missingVersions[] = (string) $requiredVersion;
            }

            return $missingVersions;
        }

        $version = new VersionParser();
        $constraintsExisting = $version->parseConstraints($currentVersion);

        $requiredVersions = explode(',', (string) $requiredVersion);

        foreach ($requiredVersions as $required) {
            $required = trim($required);

            if (empty($required)) {
                continue;
            }

            $constraintRequired = $version->parseConstraints($required);

            if (!$constraintRequired->matches($constraintsExisting)) {
                $missingVersions[] = $required;
            }
        }

        return $missingVersions;
    }

    public function setPiwikVersion($piwikVersion)
    {
        $this->piwikVersion = $piwikVersion;
    }

    public function hasDependencyToDisabledPlugin($requires)
    {
        if (empty($requires)) {
            return false;
        }

        foreach ($requires as $name => $requiredVersion) {
            $nameLower = strtolower($name);
            $isPluginRequire = !in_array($nameLower, array('piwik', 'php'));
            if ($isPluginRequire) {
                // we do not check version, only whether it's activated. Everything that is not piwik or php is assumed
                // a plugin so far.
                if (!PluginManager::getInstance()->isPluginActivated($name)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getCurrentVersion($name)
    {
        switch (strtolower($name)) {
            case 'piwik':
                return $this->piwikVersion;
            case 'php':
                return PHP_VERSION;
            default:
                try {
                    $pluginNames = PluginManager::getAllPluginsNames();

                    if (!in_array($name, $pluginNames) || !PluginManager::getInstance()->isPluginLoaded($name)) {
                        return '';
                    }

                    $plugin = PluginManager::getInstance()->loadPlugin(ucfirst($name));

                    if (!empty($plugin)) {
                        return $plugin->getVersion();
                    }
                } catch (\Exception $e) {
                }
        }

        return '';
    }
}
