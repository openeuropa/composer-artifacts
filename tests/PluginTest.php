<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use Composer\Composer;
use Composer\DependencyResolver\DefaultPolicy;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Pool;
use Composer\DependencyResolver\Request;
use Composer\Installer\PackageEvents;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Composer\Installer\PackageEvent;
use Composer\Repository\CompositeRepository;
use OpenEuropa\ComposerArtifacts\Plugin;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Class PluginTest
 */
class PluginTest extends TestCase
{
    /**
     * Test Activate.
     *
     * @dataProvider packageProvider
     */
    public function testActivate($input, $output)
    {
        $package = new RootPackage($input['name'], $input['version'], $input['prettyVersion']);
        $package->setExtra($input['extra']);
        $io = new NullIO();
        $composer = new Composer();
        $composer->setPackage($package);

        $plugin = new Plugin();
        $plugin->activate($composer, $io);

        $config_keys = array_keys($plugin->getConfig());

        $this->assertEquals(array_map('strtolower', $config_keys), $config_keys);
    }

    /**
     * Test registered events.
     */
    public function testRegisteredEvents()
    {
        $events = [
            PackageEvents::PRE_PACKAGE_INSTALL => 'prePackageInstall',
            PackageEvents::PRE_PACKAGE_UPDATE => 'prePackageUpdate',
        ];

        $this->assertEquals(Plugin::getSubscribedEvents(), $events);
    }

    /**
     * @param $input
     * @param $output
     *
     * @return object[]
     */
    private function eventPopulate($operationName, $input, $output)
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

    /**
     * Test prePackageInstall.
     *
     * @dataProvider packageProvider
     */
    public function testPrePackageInstall($input, $output)
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
     * @dataProvider packageProvider
     */
    public function testPrePackageUpdate($input, $output)
    {
        /** @var $event \Composer\Installer\PackageEvent */
        /** @var $plugin Plugin */
        /** @var $package \Composer\Package\Package */
        list($event, $plugin, $package) = array_values(
            $this->eventPopulate('update', $input, $output)
        );

        $plugin->prePackageUpdate($event);

        $this->assertEquals($output['getDistUrl'], $package->getDistUrl());
    }

    /**
     * PHPUnit provider.
     *
     * @return mixed[]
     */
    public function packageProvider()
    {
        return Yaml::parseFile(__DIR__.'/fixtures/packageProvider.yml');
    }
}
