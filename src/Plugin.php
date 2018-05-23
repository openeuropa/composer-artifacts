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
     * Current working directory.
     *
     * @var string
     */
    private $workingDir;

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

        // Make sure that package names are in lowercase.
        $this->config = array_combine(
            array_map(
                'strtolower',
                array_keys($extra['artifacts'])
            ),
            $extra['artifacts']
        );

        // Set working directory so that we can provide it as a token.
        $path = $composer->getConfig()->getConfigSource()->getName();
        $this->workingDir = dirname($path);
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
     * Custom callback that update a package properties.
     *
     * @param \Composer\Package\Package $package
     *   The package.
     */
    private function setArtifactDist(Package $package)
    {
        $tokens = $this->getPackageTokens($package);
        $config = $this->getConfig();

        $distUrl = strtr($config[$package->getName()]['dist']['url'], $tokens);
        $distType = strtr($config[$package->getName()]['dist']['type'], $tokens);

        $package->setDistUrl($distUrl);
        $package->setDistType($distType);

        $this->io->writeError(sprintf(
            'Installing <info>%s</info> artifact from <info>%s</info>.',
            $package->getName(),
            $package->getDistUrl()
        ));
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
        return [
            '{pretty-version}' => $package->getPrettyVersion(),
            '{version}' => $package->getVersion(),
            '{name}' => $package->getName(),
            '{stability}' => $package->getStability(),
            '{type}' => $package->getType(),
            '{checksum}' => $package->getDistSha1Checksum(),
            '{working-dir}' => $this->workingDir,
        ];
    }
}
