<?php

namespace OpenEuropa\ComposerArtifacts;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Repository\PathRepository;
use Composer\Plugin\PrePoolCreateEvent;

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
     * @var string[]
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
     * @return array
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
        $this->config = $this->ensureLowerCaseKeys($extra['artifacts']);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        if (version_compare(PluginInterface::PLUGIN_API_VERSION, '2.0', 'lt')) {
            // Events for Composer 1.
            return [
                PackageEvents::PRE_PACKAGE_INSTALL => 'prePackageInstall',
                PackageEvents::PRE_PACKAGE_UPDATE => 'prePackageUpdate',
            ];
        }
        // Events for Composer 2.
        return [
            PluginEvents::PRE_POOL_CREATE => 'prePoolCreate',
        ];
    }

    /**
     * Custom pre-pool-create event callback that update the package properties.
     *
     * @param \Composer\Plugin\PrePoolCreateEvent $event
     *   The event.
     */
    public function prePoolCreate(PrePoolCreateEvent $event)
    {
        $packages = $event->getPackages();
        foreach ($packages as $package) {
            $this->installPackage($package);
        }
    }

    /**
     * Custom pre-package install event callback that update the package
     * properties upon 'composer install' command.
     *
     * @param \Composer\Installer\PackageEvent $event
     *   The event.
     */
    public function prePackageInstall(PackageEvent $event)
    {
        /** @var \Composer\DependencyResolver\Operation\InstallOperation $operation */
        $operation = $event->getOperation();

        /** @var Package $package */
        $package = $operation->getPackage();
        $this->installPackage($package);
    }

    /**
     * Custom pre-package update event callback that update the package
     * properties upon 'composer update' command.
     *
     * @param \Composer\Installer\PackageEvent $event
     *   The event.
     */
    public function prePackageUpdate(PackageEvent $event)
    {
        /** @var \Composer\DependencyResolver\Operation\UpdateOperation $operation */
        $operation = $event->getOperation();

        /** @var Package $package */
        $package = $operation->getTargetPackage();
        $this->installPackage($package);
    }

    /**
     * Installs package if it is defined in the "artifacts" configuration.
     *
     * @param \Composer\Package\PackageInterface $package
     */
    protected function installPackage(PackageInterface $package)
    {
        if (\array_key_exists($package->getName(), $this->getConfig())) {
            $this->updatePackageConfiguration($package);

            $this->io->write(\sprintf(
                '  - Installing <info>%s</info> with artifact from <info>%s</info>.',
                $package->getName(),
                $package->getDistUrl()
            ));
        }
    }

    /**
     * Custom callback that returns tokens from the package.
     *
     * @param \Composer\Package\Package $package
     *   The package.
     *
     * @return string[]
     *   An array of tokens and values.
     */
    private function getPluginTokens(Package $package)
    {
        list($vendorName, $projectName) = \explode(
            '/',
            $package->getPrettyName(),
            2
        );

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
    private function updatePackageConfiguration(Package $package)
    {
        // Disable downloading from source, to ensure the artifacts will be
        // used even if composer is invoked with the `--prefer-source` option.
        $package->setSourceType(null);

        $tokens = $this->getPluginTokens($package);
        $package_config = $this->getConfig()[$package->getName()];

        // The path repository sets some path-related options like "relative"
        // which are meaningful only in that specific context. When changing
        // the repository type, these options are carried over as transport options,
        // which causes errors with downloaders that use remote file systems.
        // Remove said option when the repository type is changed.
        if ($package->getRepository() instanceof PathRepository && $package_config['dist']['type'] !== 'path') {
            $transportOptions = $package->getTransportOptions();
            unset($transportOptions['relative']);
            $package->setTransportOptions($transportOptions);
        }

        $package->setDistUrl(strtr($package_config['dist']['url'], $tokens));
        $package->setDistType(strtr($package_config['dist']['type'], $tokens));
    }

    /**
     * Ensure that array keys are in lowercase.
     *
     * @param string[] $array
     *   The input array.
     *
     * @return string[]
     *   The output array.
     */
    private function ensureLowerCaseKeys(array $array)
    {
        return \array_combine(
            \array_map(
                '\strtolower',
                \array_keys($array)
            ),
            $array
        );
    }

    /**
     * {@inheritdoc}
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
        // Method is required for Composer 2.
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
        // Method is required for Composer 2.
    }
}
