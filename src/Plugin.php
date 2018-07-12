<?php

namespace OpenEuropa\ComposerArtifacts;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Plugin\PluginInterface;

/**
 * Class Plugin
 *
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * Holds the artifacts configuration.
     *
     * @var mixed[]
     */
    private $config;

    /**
     * The composer input/output.
     *
     * @var IOInterface
     */
    private $io;

    /**
     * Get the configuration.
     *
     * @return mixed[]
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->io = $io;
        $extra = $composer->getPackage()->getExtra() + ['artifacts' => []];
        $this->config = $this->ensureLowerCase($extra['artifacts']);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            PackageEvents::PRE_PACKAGE_INSTALL => 'prePackageInstall',
            PackageEvents::PRE_PACKAGE_UPDATE => 'prePackageUpdate',
        ];
    }

    /**
     * Pre package install callback.
     *
     * @param \Composer\Installer\PackageEvent $event
     *   The event.
     */
    public function prePackageInstall(PackageEvent $event)
    {
        /** @var Package $package */
        $package = $event->getOperation()->getPackage();

        if (array_key_exists($package->getName(), $this->getConfig())) {
            $this->setArtifactDist($package);
        }
    }

    /**
     * Pre package update callback.
     *
     * @param \Composer\Installer\PackageEvent $event
     *   The event.
     */
    public function prePackageUpdate(PackageEvent $event)
    {
        /** @var Package $package */
        $package = $event->getOperation()->getInitialPackage();

        if (array_key_exists($package->getName(), $this->getConfig())) {
            $this->setArtifactDist($package);
        }
    }

    /**
     * Set the plugin tokens from the package.
     *
     * @param \Composer\Package\Package $package
     *
     * @return array
     */
    private function getPluginTokens(Package $package)
    {
        list($vendorName, $projectName) = explode('/', $package->getPrettyName());

        return [
            '{vendor-name}' => $vendorName,
            '{project-name}' => $projectName,
            '{pretty-version}' => $package->getPrettyVersion(),
            '{version}' => $package->getVersion(),
            '{name}' => $package->getName(),
            '{stability}' => $package->getStability(),
            '{type}' => $package->getType(),
            '{checksum}' => $package->getDistSha1Checksum(),
        ];
    }

    /**
     * Custom callback that update a package properties.
     *
     * @param \Composer\Package\Package $package
     *   The package.
     */
    private function setArtifactDist(Package $package)
    {
        $tokens = $this->getPluginTokens($package);
        $config = $this->getConfig();

        $distUrl = strtr($config[$package->getName()]['dist']['url'], $tokens);
        $distType = strtr($config[$package->getName()]['dist']['type'], $tokens);

        $package->setDistUrl($distUrl);
        $package->setDistType($distType);

        // Disable downloading from source, to ensure the artifacts will be
        // used even if composer is invoked with the `--prefer-source` option.
        $package->setSourceType(null);

        $this->io->writeError(sprintf(
            '  - Installing <info>%s</info> artifact from <info>%s</info>.',
            $package->getName(),
            $package->getDistUrl()
        ));
    }

    /**
     * Make sure that package names are in lowercase.
     *
     * @param array $array
     *
     * @return array
     */
    private function ensureLowerCase(array $array)
    {
        return array_combine(
            array_map(
                'strtolower',
                array_keys($array)
            ),
            $array
        );
    }
}
