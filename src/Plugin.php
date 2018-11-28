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
use OpenEuropa\ComposerArtifacts\Provider\AbstractProviderInterface;

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
     * @return string[]
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

        $extra['artifacts'] = array_map(
            function ($data) {
                return $data + ['provider' => 'github'];
            },
            $extra['artifacts']
        );

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
     *
     * @throws \Exception
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
     *
     * @throws \Exception
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
     * Custom callback that update a package properties.
     *
     * @param \Composer\Package\Package $package
     *   The package.
     *
     * @throws \Exception
     */
    private function updatePackageConfiguration(Package $package)
    {
        // Disable downloading from source, to ensure the artifacts will be
        // used even if composer is invoked with the `--prefer-source` option.
        $package->setSourceType(null);
        $provider = $this->getProvider($package);

        $provider->updatePackageConfiguration();
    }

    /**
     * Get a provider.
     *
     * @param \Composer\Package\Package $package
     *
     * @return mixed
     * @throws \Exception
     */
    private function getProvider(Package $package)
    {
        $config = $this->getConfig()[$package->getName()];

        $candidates = [
            'OpenEuropa\ComposerArtifacts\Provider\\' . \ucfirst($config['provider']),
            $config['provider'],
        ];

        foreach ($candidates as $provider) {
            if (!class_exists($provider)) {
                continue;
            }

            if (!in_array(AbstractProviderInterface::class, class_implements($provider), true)) {
                continue;
            }

            return new $provider($package, $config, $this);
        }

        // @todo: be more verbose here.
        throw new \Exception('No provider found.');
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
