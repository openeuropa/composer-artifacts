<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
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
        $fs->mkdir(__DIR__ . '/fixtures/main');
    }

    /**
     * @afterClass
     */
    public static function setUpAfterClass()
    {
        $fs = new Filesystem();
        // $fs->remove(__DIR__ . '/fixtures/main');
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $fs = new Filesystem();
        $fs->remove(glob($this->path('/main/*')));
    }

    /**
     * Test multiple Composer commands on multiple composer.json file.
     *
     * The resulting files and directories (composer.lock and vendor) are not
     * cleaned after each command on purpose.
     *
     * @param string $composer
     *   Content of composer.json file.
     * @param string|array $commands
     *   Composer command to be tested.
     * @param array $assert
     *   Assert a list of existing or non-existing files.
     *
     * @throws \Exception
     *
     * @dataProvider composerDataProvider
     */
    public function testComposerCommands($composer, $commands, array $assert = array())
    {
        $assert += [
            'show' => [],
            'non-show' => [],
            'existing' => [],
            'non-existing' => [],
        ];

        if (is_string($commands)) {
            $commands = [$commands];
        }
        $commands[] = 'show';

        $application = new TestPluginApplication();
        if (file_exists($this->path($composer))) {
            $composer = file_get_contents($this->path($composer));
        }
        $application->setWorkingDir($this->path('/main'));
        $this->writeComposerJson($this->path('/main'), $composer);

        // Run all commands
        foreach ($commands as $command) {
            $application->runCommand($command);
            print($application->getOutput()->fetch());
        }

        $output = $application->getOutput()->fetch();

        foreach ($assert['existing'] as $file) {
            $this->assertFileExists($this->path($file));
        }
        foreach ($assert['non-existing'] as $file) {
            $this->assertFileNotExists($this->path($file));
        }
        foreach ($assert['show'] as $show) {
            $this->assertContains($show, $output);
        }
        foreach ($assert['non-show'] as $nonshow) {
            $this->assertNotContains($nonshow, $output);
        }
    }

    /**
     * @return array
     */
    public function composerDataProvider()
    {
        return array_slice(Yaml::parseFile($this->path('/composerProvider.yml')), 0, 1);
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
            'file:///artifacts' => 'file://' . $this->path('/artifacts'),
        ];

        file_put_contents(
            $path . '/composer.json',
            str_replace(array_keys($replace), $replace, $content)
        );
    }
}
