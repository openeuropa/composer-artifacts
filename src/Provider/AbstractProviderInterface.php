<?php

namespace OpenEuropa\ComposerArtifacts\Provider;

use Composer\Installer\PackageEvent;

/**
 * Interface AbstractProviderInterface
 */
interface AbstractProviderInterface
{
    /**
    * Update a package properties.
    */
    public function updatePackageConfiguration();

    /**
     * Returns tokens from the package.
     *
     * @return string[]
     *   An array of tokens and values.
     */
    public function getPluginTokens();

    /**
     * Set an event.
     *
     * @param PackageEvent $event
     *   The event.
     *
     * @return AbstractProviderInterface
     *   Return itself.
     */
    public function setEvent(PackageEvent $event);

    /**
     * Get the event.
     *
     * @return PackageEvent
     *   The event.
     */
    public function getEvent();
}
