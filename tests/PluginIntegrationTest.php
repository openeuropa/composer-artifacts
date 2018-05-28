<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use Composer\Util\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

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
        // Cleanup leftovers from previous test execution.
        $fs = new Filesystem();
        $fs->remove($this->path('/main/composer.json'));
        $fs->remove($this->path('/main/composer.lock'));
        $fs->removeDirectory($this->path('/main/vendor'));
    }

    /**
     * Test a given Composer command on given composer.json file.
     *
     * @param string $composer
     *      Content of composer.json file.
     * @param string $command
     *      Composer command to be tested.
     * @param array  $assert
     *      Assert a list of existing or non-existing files.
     *
     * @throws \Exception
     *
     * @dataProvider composerDataProvider
     */
    public function testComposerCommands($composer, $command, array $assert)
    {
        $application = new TestPluginApplication();
        $application->setWorkingDir($this->path('/main'));

        $this->writeComposerJson($this->path('/main'), $composer);
        $application->runCommand($command);

        foreach ($assert['existing'] as $file) {
            $this->assertFileExists($this->path($file));
        }

        foreach ($assert['non-existing'] as $file) {
            $this->assertFileNotExists($this->path($file));
        }
    }

    /**
     * @return array
     */
    public function composerDataProvider()
    {
        return Yaml::parseFile(__DIR__.'/fixtures/composerProvider.yml');
    }

    /**
     * Get fixture path.
     *
     * @param string $path
     *      Fixture path, relative to fixture path root.
     *
     * @return string
     *      Full given fixture path.
     */
    private function path($path)
    {
        return __DIR__.'/fixtures'.$path;
    }

    /**
     * Write given composer.json at given path.
     *
     * @param string $path
     *      Directory where to write composer.json file.
     * @param $content
     *      Content of composer.json
     */
    private function writeComposerJson($path, $content)
    {
        $replace = [
            'file:///artifacts' => 'file://'.$this->path('/artifacts'),
        ];

        file_put_contents(
            $path.'/composer.json',
            str_replace(array_keys($replace), $replace, $content)
        );
    }
}
