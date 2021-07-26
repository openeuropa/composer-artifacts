<?php

namespace OpenEuropa\ComposerArtifacts\Tests\Composer2;

use Composer\Composer;
use Composer\DependencyResolver\Request;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PrePoolCreateEvent;
use OpenEuropa\ComposerArtifacts\Plugin;
use OpenEuropa\ComposerArtifacts\Tests\PluginTestBase;

/**
 * Class PluginTest for Composer 2
 *
 * @coversDefaultClass \OpenEuropa\ComposerArtifacts\Plugin
 */
class PluginTest extends PluginTestBase
{
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
                [],
                [],
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
