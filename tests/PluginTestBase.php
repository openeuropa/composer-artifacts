<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use OpenEuropa\ComposerArtifacts\Plugin;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Base class for plugin tests.
 */
abstract class PluginTestBase extends TestCase
{
    /**
     * Test Activate.
     *
     * @covers ::activate
     *
     * @dataProvider packageProvider
     *
     * @param array $input
     *   The input data.
     */
    public function testActivate(array $input)
    {
        $package = new RootPackage($input['name'], $input['version'], $input['prettyVersion']);
        $package->setExtra($input['extra']);
        $io = new NullIO();
        $composer = new Composer();
        $composer->setPackage($package);

        $plugin = new Plugin();
        $plugin->activate($composer, $io);

        $configKeys = array_keys($plugin->getConfig());

        $this->assertEquals(array_map('strtolower', $configKeys), $configKeys);
    }

    /**
     * PHPUnit provider.
     *
     * @return array
     */
    public function packageProvider(): array
    {
        return Yaml::parseFile(__DIR__ . '/fixtures/packageProvider.yml');
    }
}
