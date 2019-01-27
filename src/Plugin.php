<?php

namespace OpenEuropa\ComposerArtifacts;

use Composer\Composer;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use OpenEuropa\ComposerArtifacts\Provider\AbstractProviderInterface;

/**
 * Class Plugin
 *
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
class Plugin implements ComposerArtifactPluginInterface
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
                return $data + [
                        'provider' => 'github',
                        'events' => [
                            'pre-package-install',
                            'pre-package-update',
                        ]
                    ];
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
     * @return \Composer\IO\IOInterface
     */
    public function getIo()
    {
        return $this->io;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        $refl = new \ReflectionClass(PackageEvents::class);

        $events = [];

        foreach ($refl->getConstants() as $event) {
            $events[$event] = 'eventDispatcher';
        }

        return $events;
    }

    /**
     * Dispatch an event.
     *
     * @param \Composer\Installer\PackageEvent $event
     *   The event.
     *
     * @throws \Exception
     */
    public function eventDispatcher(PackageEvent $event)
    {
        /** @var \Composer\DependencyResolver\Operation\OperationInterface $operation */
        $operation = $event->getOperation();
        $eventName = $event->getName();
        $config = $this->getConfig();

        switch ($eventName) {
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

        if (!isset($config[$package->getName()])) {
            return;
        }

        $packageConfig = $config[$package->getName()];

        if (!\in_array($eventName, $packageConfig['events'], true)) {
            return;
        }

        /** @var AbstractProviderInterface $provider */
        $provider = $this->getProvider(
            $package,
            $event,
            $packageConfig
        );

        $provider->updatePackageConfiguration();

        $this->io->write(
            $provider->getMessage()
        );
    }

    /**
     * Get a provider.
     *
     * @param \Composer\Package\Package $package
     *   The package.
     * @param \Composer\Installer\PackageEvent $event
     *   The event.
     * @param $config
     *   The config.
     *
     * @return AbstractProviderInterface
     *   The provider
     *
     * @throws \Exception
     */
    private function getProvider(Package $package, PackageEvent $event, $config)
    {
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

            return new $provider($package, $event, $config, $this);
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
