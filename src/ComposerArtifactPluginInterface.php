<?php

namespace OpenEuropa\ComposerArtifacts;

use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Plugin\PluginInterface;

/**
 * Interface ComposerArtifactPluginInterface.
 */
interface ComposerArtifactPluginInterface extends PluginInterface, EventSubscriberInterface
{
    /**
     * Get the IO.
     *
     * @return \Composer\IO\IOInterface
     *   The IO.
     */
    public function getIo();

    /**
     * Get the config.
     *
     * @return array
     */
    public function getConfig();
}
