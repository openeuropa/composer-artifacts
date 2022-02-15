<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use Composer\Plugin\PluginInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use PHPUnit\Runner\Version;

/**
 * Integration tests.
 */
class PluginIntegrationTest extends TestCase
{
    /**
     * @beforeClass
     */
    public static function setUpBeforeClass(): void
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
        $fs->remove(__DIR__ . '/fixtures/main');
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
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
    public function testComposerCommands(string $composer, $commands, array $assert = array())
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
        $application->setWorkingDir($this->path('/main'));
        $this->writeComposerJson($this->path('/main'), $composer);

        // Run all commands
        foreach ($commands as $command) {
            $application->runCommand($command);
        }

        $output = $application->getOutput()->fetch();

        foreach ($assert['existing'] as $file) {
            $this->assertFileExists($this->path($file));
        }
        foreach ($assert['non-existing'] as $file) {
            if (version_compare(Version::id(), '9.5', 'lt')) {
                $this->assertFileNotExists($this->path($file));
            } else {
                $this->assertFileDoesNotExist($this->path($file));
            }
        }
        foreach ($assert['show'] as $show) {
            $this->assertStringContainsString($show, $output);
        }
        foreach ($assert['non-show'] as $nonshow) {
            $this->assertStringNotContainsString($nonshow, $output);
        }
    }

    /**
     * @return array
     */
    public function composerDataProvider(): array
    {
        return Yaml::parseFile($this->path('/composerProvider.yml'));
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
    private function path(string $path)
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
    private function writeComposerJson(string $path, $content)
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
