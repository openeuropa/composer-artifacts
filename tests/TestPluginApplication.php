<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use Composer\Console\Application;
use Composer\Plugin\PluginInterface;

/**
 * Wraps Console application allowing to set the test plugin at runtime.
 */
class TestPluginApplication extends Application {

    /**
     * Test plugin.
     *
     * @var \Composer\Plugin\PluginInterface
     */
    private $testPlugin;

    /**
     * TestPluginApplication constructor.
     *
     * @param $testPlugin
     */
    public function __construct(PluginInterface $testPlugin) {
        parent::__construct();
        $this->testPlugin = $testPlugin;
    }

    /**
     * {@inheritdoc}
     */
    public function getComposer($required = TRUE, $disablePlugins = NULL) {
        $composer = parent::getComposer($required, $disablePlugins);
        $composer->getPluginManager()->addPlugin($this->testPlugin);
        return $composer;
    }

}
