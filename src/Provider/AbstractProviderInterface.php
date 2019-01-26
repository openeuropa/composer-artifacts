<?php

namespace OpenEuropa\ComposerArtifacts\Provider;

use Composer\Installer\PackageEvent;

/**
 * Interface AbstractProviderInterface.
 */
interface AbstractProviderInterface
{
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
     * Returns tokens from the package.
     *
     * @return string[]
     *   An array of tokens and values
     */
    public function getPluginTokens();

    /**
     * Set an event.
     *
     * @param packageEvent $event
     *   The event
     *
     * @return abstractProviderInterface
     *   Return itself
     */
    public function setEvent(PackageEvent $event);

    /**
     * Update a package properties.
     */
    public function updatePackageConfiguration();
}
