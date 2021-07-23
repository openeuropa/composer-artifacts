<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use Composer\Composer;
use Composer\DependencyResolver\Request;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PrePoolCreateEvent;
use OpenEuropa\ComposerArtifacts\Plugin;

/**
 * Class PluginComposer2Test for Composer 2
 *
 * @coversDefaultClass \OpenEuropa\ComposerArtifacts\Plugin
 */
class PluginComposer2Test extends PluginTestBase
{
    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        if (version_compare(PluginInterface::PLUGIN_API_VERSION, '2.0', 'lt')) {
            $this->markTestSkipped('Test has not to be run on Composer 1.');
        }
        parent::setUp();
    }

    /**
     * Test prePoolCreate.
     *
     * @covers ::prePoolCreate
     *
     * @dataProvider packageProvider
     *
     * @param array $input
     *   The input data.
     * @param array $output
     *   The output data.
     */
    public function testPrePoolCreate($input, $output)
    {
        list($event, $plugin, $package) = array_values(
            $this->prePoolCreateEventPopulate($input)
        );

        $plugin->prePoolCreate($event);

        foreach ($output as $key => $value) {
            $this->assertEquals($value, call_user_func([$package, $key]));
        }
    }

    /**
     * Prepares variables to run event.
     *
     * @param array $input
     *   The input data.
     *
     * @return array
     */
    private function prePoolCreateEventPopulate($input)
    {
        $package = new RootPackage($input['name'], $input['version'], $input['prettyVersion']);
        $package->setExtra($input['extra']);
        $io = new NullIO();
        $composer = new Composer();
        $composer->setPackage($package);

        $plugin = new Plugin();
        $plugin->activate($composer, $io);

        return [
            'event' => new PrePoolCreateEvent(
                PluginEvents::PRE_POOL_CREATE,
                [],
                new Request(),
                ['dev'],
                ['dev'],
                [],
                [],
                [$package],
                []
            ),
            'plugin' => $plugin,
            'package' => $package,
        ];
    }
}
