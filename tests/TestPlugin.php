<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use Composer\Composer;
use Composer\Package\Package;
use OpenEuropa\ComposerArtifacts\Plugin;

/**
 * Class TestPlugin
 */
class TestPlugin extends Plugin
{
    /**
     * Composer instance.
     *
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * TestPlugin constructor.
     *
     * @param Composer $composer
     */
    public function __construct(Composer $composer)
    {
        $this->composer = $composer;
    }

    /**
     * @param \Composer\Package\Package $package
     *
     * @return array
     */
    protected function getPluginTokens(Package $package)
    {
        $configSource = $this->composer->getConfig()->getConfigSource()->getName();

        return parent::getPluginTokens($package) + [
          '{working-dir}' => dirname($configSource),
        ];
    }
}
