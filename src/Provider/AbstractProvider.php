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
    protected $config;

    /**
     * @var \Composer\Installer\PackageEvent
     */
    protected $event;

    /**
     * @var \Composer\Package\Package
     */
    protected $package;

    /**
     * @var \Composer\Plugin\PluginInterface
     */
    protected $plugin;

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

        return \sprintf(
            $message,
            $this->package->getName(),
            $this->package->getDistUrl()
        );
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
    public function setEvent(PackageEvent $event)
    {
        $this->event = $event;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function updatePackageConfiguration()
    {
        // Disable downloading from source, to ensure the artifacts will be
        // used even if composer is invoked with the `--prefer-source` option.
        $this->package->setSourceType('');
    }

    /**
     * {@inheritdoc}
     */
    protected function getInstallPath()
    {
        return $this->getEvent()->getComposer()->getInstallationManager()->getInstallPath($this->package);
    }
}
