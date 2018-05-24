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
        $fs->remove($this->path('/main/composer.json'));
        $fs->remove($this->path('/main/composer.lock'));
        $fs->removeDirectory($this->path('/main/vendor'));
    }

    /**
     * Test install command.
     *
     * @param string $type
     * @param string $extension
     *
     * @throws \Exception
     *
     * @dataProvider artifactProvider
     */
    public function testInstall($type, $extension)
    {
        $artifact = $this->path('/artifacts').'/dependency-1-{pretty-version}.'.$extension;
        $this->prepareComposerJson($artifact, $type);

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
        $artifact = $this->path('/artifacts').'/dependency-1-{pretty-version}.tar.gz';
        $this->prepareComposerJson($artifact, "tar");

        $application = new TestPluginApplication();
        $application->setWorkingDir($this->path('/main'));
        $application->runCommand('install --prefer-source');

        $this->assertFileExists($this->path('/main/vendor/openeuropa/dependency-1/composer.json'));
        $this->assertFileExists($this->path('/main/vendor/openeuropa/dependency-2/composer.json'));
        // The following test is failing, this is why we use ->markTestIncomplete().
        $this->markTestIncomplete('This test has missing assertion. (TODO)');
        $this->assertFileNotExists($this->path('/main/vendor/openeuropa/dependency-1/artifact.txt'));
    }

    /**
     * @return array
     */
    public function artifactProvider()
    {
        return [
            ["tar", "tar.gz"],
            ["zip", "zip"],
        ];
    }

    /**
     * Prepare main composer.json by replacing inline tokens with given values.
     *
     * @param string $artifact
     * @param string $type
     */
    private function prepareComposerJson($artifact, $type)
    {
        $content = file_get_contents($this->path('/main/composer.json.dist'));
        $replace = [
            "%ARTIFACT%" => $artifact,
            "%TYPE%" => $type,
        ];
        $content = str_replace(array_keys($replace), $replace, $content);
        file_put_contents($this->path('/main/composer.json'), $content);
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
