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
        $fs = new Filesystem();
        $fs->remove($this->path('/main/composer.json'));
        $fs->remove($this->path('/main/composer.lock'));
        $fs->removeDirectory($this->path('/main/vendor'));
    }

    /**
     * Test different composer.json files against different commands.
     *
     * @param array $input
     *   The input data.
     * @param array $output
     *   The output data.
     * @throws \Exception
     * @dataProvider composerProvider
     */
    public function testInstall(array $input, array $output)
    {
        $application = new TestPluginApplication();
        $application->setWorkingDir($this->path('/main'));

        $this->prepareComposerJson($input['file'], $input['artifact']);
        $application->runCommand($input['command']);

        $output += [
            'existing' => [],
            'non-existing' => [],
        ];

        foreach ($output['existing'] as $file) {
            $this->assertFileExists($this->path('/main').$file);
        }

        foreach ($output['non-existing'] as $file) {
            $this->assertFileNotExists($this->path('/main').$file);
        }
    }

    /**
     * @return array
     */
    public function composerProvider()
    {
        return Yaml::parseFile(__DIR__.'/fixtures/composerProvider.yml');
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

    /**
     * @param string $source
     * @param string $artifact
     */
    private function prepareComposerJson($source, $artifact)
    {
        $replace = [
            $artifact => 'file://'.$this->path('/artifacts').'/'.$artifact,
        ];

        file_put_contents(
            $this->path('/main/composer.json'),
            str_replace(
                array_keys($replace),
                $replace,
                file_get_contents(
                    'file://'.$this->path('/main').'/'.$source
                )
            )
        );
    }
}
