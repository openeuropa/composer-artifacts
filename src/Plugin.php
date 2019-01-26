<?php

namespace OpenEuropa\ComposerArtifacts;

use Composer\Composer;
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
    public static function getSubscribedEvents()
    {
        // More event could be added here.
        return [
            PackageEvents::PRE_PACKAGE_INSTALL => ['eventDispatcher', PackageEvents::PRE_PACKAGE_INSTALL],
            PackageEvents::PRE_PACKAGE_UPDATE => ['eventDispatcher', PackageEvents::PRE_PACKAGE_UPDATE],
            PackageEvents::POST_PACKAGE_INSTALL => ['eventDispatcher', PackageEvents::POST_PACKAGE_INSTALL],
            PackageEvents::POST_PACKAGE_UPDATE => ['eventDispatcher', PackageEvents::POST_PACKAGE_UPDATE],
        ];
    }

    /**
     * @param \Composer\Installer\PackageEvent $event
     *
     * @throws \Exception
     */
    public function eventDispatcher(PackageEvent $event)
    {
        /** @var \Composer\DependencyResolver\Operation\OperationInterface $operation */
        $operation = $event->getOperation();

        switch ($event->getName()) {
            case 'post-package-update':
            case 'pre-package-update':
                /** @var Package $package */
                $package = $operation->getTargetPackage();
                break;
            default:
                /** @var Package $package */
                $package = $operation->getPackage();
                break;
        }

        if (!isset($this->getConfig()[$package->getName()])) {
            return;
        }

        // Disable downloading from source, to ensure the artifacts will be
        // used even if composer is invoked with the `--prefer-source` option.
        $package->setSourceType('');

        /** @var AbstractProviderInterface $provider */
        $provider = $this->getProvider($package)
            ->setEvent($event);

        $provider->updatePackageConfiguration();

        $this->io->write(
            $provider->getMessage()
        );
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
