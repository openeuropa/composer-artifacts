<?php

namespace OpenEuropa\ComposerArtifacts;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PostFileDownloadEvent;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Repository\PathRepository;
use Composer\Plugin\PrePoolCreateEvent;
use Composer\Script\ScriptEvents;

/**
 * Composer artifacts plugin.
 *
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * Artifacts configuration.
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
            PluginEvents::PRE_FILE_DOWNLOAD => 'preFileDownload',
        ];
    }

    /**
     * Custom event handler to change configuration for artifacts.
     *
     * @param \Composer\Plugin\PreFileDownloadEvent $event
     *   The event.
     */
    public function preFileDownload(PreFileDownloadEvent $event)
    {
        $package = $event->getContext();
        if ($package instanceof PackageInterface && \array_key_exists($package->getName(), $this->getConfig())) {
            $event->setProcessedUrl($this->getPackageDistUrl($package));
            $package->setDistType($this->getPackageDistType($package));
        }
    }

    /**
     * Custom callback that returns tokens from the package.
     *
     * @param \Composer\Package\PackageInterface $package
     *   The package.
     *
     * @return string[]
     *   An array of tokens and values.
     */
    private function getPluginTokens(PackageInterface $package)
    {
        [$vendorName, $projectName] = \explode(
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
            '{pretty-name}' => $package->getName(),
            '{stability}' => $package->getStability(),
            '{type}' => $package->getType(),
            '{checksum}' => $package->getDistSha1Checksum(),
        ];
    }

    /**
     * @param \Composer\Package\PackageInterface $package
     *
     * @return string
     */
    private function getPackageDistUrl(PackageInterface $package)
    {
        $tokens = $this->getPluginTokens($package);
        $package_config = $this->getConfig()[$package->getName()];
        return strtr($package_config['dist']['url'], $tokens);
    }

    /**
     * @param \Composer\Package\PackageInterface $package
     *
     * @return string
     */
    private function getPackageDistType(PackageInterface $package)
    {
        $tokens = $this->getPluginTokens($package);
        $package_config = $this->getConfig()[$package->getName()];
        return strtr($package_config['dist']['type'], $tokens);
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
