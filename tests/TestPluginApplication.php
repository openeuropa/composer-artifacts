<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use Composer\Console\Application;
use Composer\IO\NullIO;
use OpenEuropa\ComposerArtifacts\Plugin;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Wraps Console application allowing to set the test plugin at runtime.
 */
class TestPluginApplication extends Application
{
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

        $this->output = new BufferedOutput();
    }

    /**
     * {@inheritdoc}
     */
    public function getComposer($required = true, $disablePlugins = null)
    {
        $composer = parent::getComposer($required, $disablePlugins);
        $composer->getPluginManager()->addPlugin(new Plugin());

        return $composer;
    }

    /**
     * Return the output
     *
     * @return \Symfony\Component\Console\Output\BufferedOutput
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Clean the output
     */
    public function cleanOutput()
    {
        $this->output = new BufferedOutput();
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
        $commandInput = new StringInput($input . ' --working-dir=' . $this->workingDir);
        $this->output = new BufferedOutput();

        return $this->run($commandInput, $this->output);
    }
}
