<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use Composer\Console\Application;
use Composer\IO\NullIO;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Wraps Console application allowing to set the test plugin at runtime.
 */
class TestPluginApplication extends Application
{
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
    public function __construct()
    {
        parent::__construct();
        $this->setAutoExit(false);
        $this->setCatchExceptions(false);
        $this->testPlugin = new TestPlugin();
        $this->output = new BufferedOutput();
        $this->io = new NullIO();
    }

    /**
     * {@inheritdoc}
     */
    public function getComposer($required = true, $disablePlugins = null)
    {
        $composer = parent::getComposer($required, $disablePlugins);
        $this->testPlugin->setPluginTokensAsArray([
            '{working-dir}' => dirname(
                $composer->getConfig()->getConfigSource()->getName()
            ),
        ]);
        $composer->getPluginManager()->addPlugin($this->testPlugin);

        return $composer;
    }

    /**
     * @return \Symfony\Component\Console\Output\BufferedOutput
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @param string $workingDir
     */
    public function setWorkingDir($workingDir)
    {
        $this->workingDir = $workingDir;
    }

    /**
     * @param string $input
     *
     * @return int
     * @throws \Exception
     */
    public function runCommand($input)
    {
        $input = new StringInput($input.' --working-dir='.$this->workingDir);

        return $this->run($input, $this->output);
    }
}
