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
     * @beforeClass
     */
    public static function setUpBeforeClass()
    {
        $fs = new Filesystem();
        $fs->remove(__DIR__ . '/fixtures' . '/main/composer.json');
        $fs->remove(__DIR__ . '/fixtures' . '/main/composer.lock');
        $fs->removeDirectory(__DIR__ . '/fixtures' . '/main/vendor');
    }

    /**
     * @afterClass
     */
    public static function setUpAfterClass()
    {
        $fs = new Filesystem();
        $fs->remove(__DIR__ . '/fixtures' . '/main/composer.json');
        $fs->remove(__DIR__ . '/fixtures' . '/main/composer.lock');
        $fs->removeDirectory(__DIR__ . '/fixtures' . '/main/vendor');
    }

    /**
     * Test multiple Composer commands on multiple composer.json file.
     *
     * The resulting files and directories (composer.lock and vendor) are not
     * cleaned after each command on purpose.
     *
     * @param string $composer
     *   Content of composer.json file.
     * @param string $command
     *   Composer command to be tested.
     * @param array $assert
     *   Assert a list of existing or non-existing files.
     *
     * @throws \Exception
     *
     * @dataProvider composerDataProvider
     */
    public function testComposerCommands($composer, $command, array $assert = array())
    {
        $this->writeComposerJson($this->path('/main'), $composer);

        $application = new TestPluginApplication();
        $application->setWorkingDir($this->path('/main'));
        $application->runCommand($command);
        $application->getOutput()->fetch();

        $assert += [
            'show' => [],
            'non-show' => [],
            'existing' => [],
            'non-existing' => [],
        ];

        foreach ($assert['existing'] as $file) {
            $this->assertFileExists($this->path($file));
        }
        foreach ($assert['non-existing'] as $file) {
            $this->assertFileNotExists($this->path($file));
        }

        $application->runCommand('show');
        $output = $application->getOutput()->fetch();
        
        foreach ($assert['show'] as $show) {
            $this->assertContains(
                $show,
                $output
            );
        }
        foreach ($assert['non-show'] as $nonshow) {
            $this->assertNotContains(
                $nonshow,
                $output
            );
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
        return __DIR__ . '/fixtures' . $path;
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
