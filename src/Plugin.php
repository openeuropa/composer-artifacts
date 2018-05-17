<?php

namespace OpenEuropa\ComposerArtifacts;

use Composer\Composer;
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
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->io = $io;

        $extra = $composer->getPackage()->getExtra() + ['artifacts' => []];

        // Make sure that package name are in lowercase.
        $this->config = array_combine(
            array_map(
                function ($name) {
                    return strtolower($name);
                },
                array_keys($extra['artifacts'])
            ),
            $extra['artifacts']
        );
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

        if (array_key_exists($package->getName(), $this->config)) {
            self::setArtifactDist($package);
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
        if (array_key_exists($package->getName(), $this->config)) {
            $this->setArtifactDist($package);
        }
    }

    /**
     * Custom callback that update a package properties.
     *
     * @param \Composer\Package\Package $package
     *   The package.
     */
    private function setArtifactDist(Package $package)
    {
        $this->io->writeError(
            "  - Installing artifact of <info>".$package->getName()."</info> instead of regular package."
        );
        $tokens = $this->getPackageTokens($package);

        $distUrl = strtr($this->config[$package->getName()]['dist']['url'], $tokens);
        $distType = strtr($this->config[$package->getName()]['dist']['type'], $tokens);

        $package->setDistUrl($distUrl);
        $package->setDistType($distType);
    }

    /**
     * Get tokens from a package.
     *
     * @param \Composer\Package\Package $package
     *
     * @return string[]
     *   The list of tokens and their associated values.
     */
    private function getPackageTokens(Package $package)
    {
        $tokens = [
          'version' => $package->getVersion(),
          'name' => $package->getName(),
          'stability' => $package->getStability(),
          'type' => $package->getType(),
          'checksum' => $package->getDistSha1Checksum(),
        ];

        foreach ($tokens as $name => $value) {
            unset($tokens[$name]);
            $mappings['{'.$name.'}'] = $value;
        }

        return $tokens;
    }
}
