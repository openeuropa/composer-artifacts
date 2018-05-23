<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use Composer\Util\Filesystem;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests.
 */
class PluginIntegrationTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $fs = new Filesystem();
        $fs->remove($this->path('/main/composer.lock'));
        $fs->removeDirectory($this->path('/main/vendor'));
    }

    /**
     * Test install command.
     */
    public function testInstall()
    {
        $application = new TestPluginApplication();
        $application->setWorkingDir($this->path('/main'));
        $application->runCommand('install');

        $this->assertFileExists($this->path('/main/vendor/openeuropa/dependency-1/composer.json'));
        $this->assertFileExists($this->path('/main/vendor/openeuropa/dependency-1/artifact.txt'));
        $this->assertFileExists($this->path('/main/vendor/openeuropa/dependency-2/composer.json'));
    }

    /**
     * Test install command with prefer source.
     */
    public function testInstallPreferSource()
    {
        $application = new TestPluginApplication();
        $application->setWorkingDir($this->path('/main'));
        $application->runCommand('install --prefer-source');

        $this->assertFileExists($this->path('/main/vendor/openeuropa/dependency-1/composer.json'));
        $this->assertFileNotExists($this->path('/main/vendor/openeuropa/dependency-1/artifact.txt'));
        $this->assertFileExists($this->path('/main/vendor/openeuropa/dependency-2/composer.json'));
    }

    /**
     * Test install command with plugins disabled.
     */
    public function testInstallWithoutPlugins()
    {
        $application = new TestPluginApplication();
        $application->setWorkingDir($this->path('/main'));
        $application->runCommand('install --no-plugins');

        $this->assertFileExists($this->path('/main/vendor/openeuropa/dependency-1/composer.json'));
        $this->assertFileNotExists($this->path('/main/vendor/openeuropa/dependency-1/artifact.txt'));
        $this->assertFileExists($this->path('/main/vendor/openeuropa/dependency-2/composer.json'));
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function path($path)
    {
        return __DIR__.'/fixtures'.$path;
    }
}
