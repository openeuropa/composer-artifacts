<?php

namespace OpenEuropa\ComposerArtifacts\Provider;

use Composer\Installer\PackageEvent;
use Composer\Package\Package;
use Composer\Plugin\PluginInterface;

/**
 * Class AbstractProvider
 */
abstract class AbstractProvider implements AbstractProviderInterface
{
    /**
     * @var \Composer\Package\Package
     */
    protected $package;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var \Composer\Plugin\PluginInterface
     */
    protected $plugin;

    /**
     * @var \Composer\Installer\PackageEvent
     */
    protected $event;

    /**
     * AbstractProvider constructor.
     *
     * @param \Composer\Package\Package $package
     * @param array $config
     * @param \Composer\Plugin\PluginInterface $plugin
     */
    public function __construct(Package $package, array $config, PluginInterface $plugin)
    {
        $this->package = $package;
        $this->config = $config;
        $this->plugin = $plugin;
    }

    /**
     * {@inheritdoc}
     */
    public function getPluginTokens()
    {
        list($vendorName, $projectName) = \explode(
            '/',
            $this->package->getPrettyName(),
            2
        );

        return [
            '{vendor-name}' => $vendorName,
            '{project-name}' => $projectName,
            '{pretty-version}' => $this->package->getPrettyVersion(),
            '{version}' => $this->package->getVersion(),
            '{name}' => $this->package->getName(),
            '{stability}' => $this->package->getStability(),
            '{type}' => $this->package->getType(),
            '{checksum}' => $this->package->getDistSha1Checksum(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    abstract public function updatePackageConfiguration();

    /**
     * {@inheritdoc}
     */
    public function setEvent(PackageEvent $event)
    {
        $this->event = $event;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * {@inheritdoc}
     */
    protected function getInstallPath()
    {
        return $this->getEvent()->getComposer()->getInstallationManager()->getInstallPath($this->package);
    }
}
