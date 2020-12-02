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
use Composer\Repository\PathRepository;

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
        return [
            PackageEvents::PRE_PACKAGE_INSTALL => 'prePackageInstall',
            PackageEvents::PRE_PACKAGE_UPDATE => 'prePackageUpdate',
        ];
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

        if (\array_key_exists($package->getName(), $this->getConfig())) {
            $this->updatePackageConfiguration($package);

            $this->io->write(\sprintf(
                '  - Updating <info>%s</info> with artifact from <info>%s</info>.',
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
}
