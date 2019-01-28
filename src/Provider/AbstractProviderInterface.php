<?php

namespace OpenEuropa\ComposerArtifacts\Provider;

use Composer\Installer\PackageEvent;

/**
 * Interface AbstractProviderInterface.
 */
interface AbstractProviderInterface
{
    /**
     * Returns the config.
     *
     * @return array
     */
    public function getConfig();

    /**
     * Get the event.
     *
     * @return packageEvent
     *   The event
     */
    public function getEvent();

    /**
     * Get the message to display when a particular event is triggered.
     *
     * @return string
     *   The message properly formatted
     */
    public function getMessage();

    /**
     * Get the package.
     *
     * @return \Composer\Package\PackageInterface
     *   The package
     */
    public function getPackage();

    /**
     * Get the plugin.
     *
     * @return \OpenEuropa\ComposerArtifacts\ComposerArtifactPluginInterface
     */
    public function getPlugin();

    /**
     * Returns tokens from the package.
     *
     * @return string[]
     *   An array of tokens and values
     */
    public function getPluginTokens();

    /**
     * Update a package properties.
     *
     * return AbstractProviderInterface
     *   Return itself.
     */
    public function updatePackageConfiguration();
}
