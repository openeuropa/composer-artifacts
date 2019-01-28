<?php

namespace OpenEuropa\ComposerArtifacts\Provider;

use Composer\Installer\PackageEvent;
use Composer\Package\Package;
use Composer\Plugin\PluginInterface;

/**
 * Class AbstractProvider.
 */
abstract class AbstractProvider implements AbstractProviderInterface
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var \Composer\Installer\PackageEvent
     */
    private $event;

    /**
     * @var \Composer\Package\Package
     */
    private $package;

    /**
     * @var \OpenEuropa\ComposerArtifacts\ComposerArtifactPluginInterface
     */
    private $plugin;

    /**
     * AbstractProvider constructor.
     *
     * @param \Composer\Package\Package $package
     * @param \Composer\Installer\PackageEvent $event
     * @param array $config
     * @param \Composer\Plugin\PluginInterface $plugin
     */
    public function __construct(Package $package, PackageEvent $event, array $config, PluginInterface $plugin)
    {
        $this->package = $package;
        $this->config = $config;
        $this->plugin = $plugin;
        $this->event = $event;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return $this->config;
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
    public function getMessage()
    {
        $message = '';

        switch ($this->getEvent()->getName()) {
            case 'pre-package-install':
            case 'post-package-install':
                $message = '  - Installing <info>%s</info> with artifact from <info>%s</info>.';

                break;
            case 'pre-package-update':
            case 'post-package-update':
                $message = '  - Updating <info>%s</info> with artifact from <info>%s</info>.';

                break;
        }

        return sprintf(
            $message,
            $this->package->getName(),
            $this->package->getDistUrl()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPackage()
    {
        return $this->package;
    }

    /**
     * {@inheritdoc}
     */
    public function getPlugin()
    {
        return $this->plugin;
    }

    /**
     * {@inheritdoc}
     */
    public function getPluginTokens()
    {
        list($vendorName, $projectName) = explode(
            '/',
            $this->getPackage()->getPrettyName(),
            2
        );

        return [
            '{vendor-name}' => $vendorName,
            '{project-name}' => $projectName,
            '{pretty-version}' => $this->getPackage()->getPrettyVersion(),
            '{version}' => $this->getPackage()->getVersion(),
            '{name}' => $this->getPackage()->getName(),
            '{stability}' => $this->getPackage()->getStability(),
            '{type}' => $this->getPackage()->getType(),
            '{checksum}' => $this->getPackage()->getDistSha1Checksum(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function updatePackageConfiguration()
    {
        // Disable downloading from source, to ensure the artifacts will be
        // used even if composer is invoked with the `--prefer-source` option.
        $this->getPackage()->setSourceType('');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function getInstallPath()
    {
        return $this->getEvent()->getComposer()->getInstallationManager()->getInstallPath($this->package);
    }
}
