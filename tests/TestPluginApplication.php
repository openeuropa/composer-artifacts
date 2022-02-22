<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use Composer\Composer;
use Composer\Console\Application;
use Composer\Package\RootPackage;
use OpenEuropa\ComposerArtifacts\Plugin;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Yaml\Yaml;

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
    public function getComposer($required = true, $disablePlugins = null, $disableScripts = null): ?Composer
    {
        $package = new RootPackage('openeuropa/main', '1.0.0', '1.0.0');
        $composer = parent::getComposer($required, $disablePlugins, $disableScripts);
        $composer->getPluginManager()->addPlugin(new Plugin(), false, $package);
        return $composer;
    }

    /**
     * Return the output
     *
     * @return \Symfony\Component\Console\Output\BufferedOutput
     */
    public function getOutput(): BufferedOutput
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
    public function setWorkingDir(string $workingDir)
    {
        $this->workingDir = $workingDir;
    }

    /**
     * @param string $input
     *
     * @return int
     * @throws \Exception
     */
    public function runCommand(string $input): int
    {
        $commandInput = new StringInput($input . ' --working-dir=' . $this->workingDir);
        $this->output = new BufferedOutput();

        return $this->run($commandInput, $this->output);
    }
}
