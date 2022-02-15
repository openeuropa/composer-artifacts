<?php

namespace OpenEuropa\ComposerArtifacts\Tests\Composer1;

use Composer\Composer;
use Composer\DependencyResolver\DefaultPolicy;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Pool;
use Composer\DependencyResolver\Request;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Composer\Installer\PackageEvent;
use Composer\Plugin\PluginInterface;
use Composer\Repository\CompositeRepository;
use Composer\Repository\InstalledArrayRepository;
use OpenEuropa\ComposerArtifacts\Plugin;
use OpenEuropa\ComposerArtifacts\Tests\PluginTestBase;

/**
 * Tests that plugin works correctly with Composer 1.
 *
 * @coversDefaultClass \OpenEuropa\ComposerArtifacts\Plugin
 */
class PluginTest extends PluginTestBase
{
    /**
     * Test prePackageInstall.
     *
     * @covers ::prePackageInstall
     *
     * @dataProvider packageProvider
     *
     * @param array $input
     *   The input data.
     * @param array $output
     *   The output data.
     */
    public function testPrePackageInstall(array $input, array $output)
    {
        /** @var $event \Composer\Installer\PackageEvent */
        /** @var $plugin Plugin */
        /** @var $package \Composer\Package\Package */
        list($event, $plugin, $package) = array_values(
            $this->eventPopulate('install', $input, $output)
        );

        $plugin->prePackageInstall($event);

        $this->assertEquals($output['getDistUrl'], $package->getDistUrl());
    }

    /**
     * Test prePackageUpdate.
     *
     * @covers ::prePackageUpdate
     *
     * @dataProvider packageProvider
     *
     * @param array $input
     *   The input data.
     * @param array $output
     *   The output data.
     */
    public function testPrePackageUpdate(array $input, array $output)
    {
        /** @var $event \Composer\Installer\PackageEvent */
        /** @var $plugin Plugin */
        /** @var $package \Composer\Package\Package */
        list($event, $plugin, $package) = array_values(
            $this->eventPopulate('update', $input, $output)
        );

        $plugin->prePackageUpdate($event);

        foreach ($output as $key => $value) {
            $this->assertEquals($value, call_user_func([$package, $key]));
        }
    }

    /**
     * @param string $operationName
     * @param array $input
     *   The input data.
     *
     * @return object[]
     */
    private function eventPopulate(string $operationName, array $input)
    {
        $package = new RootPackage($input['name'], $input['version'], $input['prettyVersion']);
        $package->setExtra($input['extra']);
        $io = new NullIO();
        $composer = new Composer();
        $composer->setPackage($package);

        $plugin = new Plugin();
        $plugin->activate($composer, $io);

        $operation = 'install' === $operationName ?
        new InstallOperation($package) :
        new UpdateOperation($package, $package);

        if (version_compare(PluginInterface::PLUGIN_API_VERSION, '2.0', 'lt')) {
            return [
                'event' => new PackageEvent(
                    'test',
                    $composer,
                    $io,
                    false,
                    new DefaultPolicy(),
                    new Pool(),
                    new CompositeRepository([]),
                    new Request(),
                    [],
                    $operation
                ),
                'plugin' => $plugin,
                'package' => $package,
            ];
        }
        return [];
    }
}
