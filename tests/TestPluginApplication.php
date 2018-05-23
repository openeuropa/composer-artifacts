<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use Composer\Console\Application;
use Composer\Plugin\PluginInterface;
use OpenEuropa\ComposerArtifacts\Plugin;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Wraps Console application allowing to set the test plugin at runtime.
 */
class TestPluginApplication extends Application {

    /**
     * @var \Composer\Plugin\PluginInterface
     */
    private $testPlugin;

    /**
     * @var \Symfony\Component\Console\Output\BufferedOutput
     */
    private $output;

    /**
     * @var string
     */
    private $workingDir = '.';

    /**
     * TestPluginApplication constructor.
     */
    public function __construct() {
        parent::__construct();
        $this->setAutoExit(FALSE);
        $this->setCatchExceptions(FALSE);
        $this->testPlugin = new Plugin();
        $this->output = new BufferedOutput();
    }

    /**
     * {@inheritdoc}
     */
    public function getComposer($required = TRUE, $disablePlugins = NULL) {
        $composer = parent::getComposer($required, $disablePlugins);
        $composer->getPluginManager()->addPlugin($this->testPlugin);
        return $composer;
    }

    /**
     * @return \Symfony\Component\Console\Output\BufferedOutput
     */
    public function getOutput(): BufferedOutput {
        return $this->output;
    }

    /**
     * @param string $workingDir
     */
    public function setWorkingDir(string $workingDir) {
        $this->workingDir = $workingDir;
    }

    /**
     * @param string $input
     *
     * @return int
     * @throws \Exception
     */
    public function runCommand(string $input): int {
        $input = new StringInput($input.' --working-dir='.$this->workingDir);
        return $this->run($input, $this->output);
    }
}
