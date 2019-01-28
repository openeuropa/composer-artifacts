<?php

namespace OpenEuropa\ComposerArtifacts;

use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Plugin\PluginInterface;

/**
 * Interface ComposerArtifactPluginInterface.
 */
interface ComposerArtifactPluginInterface extends PluginInterface, EventSubscriberInterface
{
    /**
     * Dispatch an event.
     *
     * @param \Composer\Installer\PackageEvent $event
     *   The event
     *
     * @throws \Exception
     */
    public function eventDispatcher(PackageEvent $event);

    /**
     * Get the config.
     *
     * @return array
     *   The plugin configuration. In composer.json, the 'artifacts' section in
     *   'extra'.
     */
    public function getConfig();

    /**
     * Get the IO.
     *
     * @return \Composer\IO\IOInterface
     *   The IO
     */
    public function getIo();
}
