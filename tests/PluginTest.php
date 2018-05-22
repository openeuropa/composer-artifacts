<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use OpenEuropa\ComposerArtifacts\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Class PluginTest
 */
class PluginTest extends TestCase
{

    /**
     * Test artifact.
     */
    public function testSetArtifactDist()
    {
        $package = new RootPackage('test/test', '1.0.0', '1.0.0');
        $package->setExtra([
            'artifacts' => [
                'test/test' => [
                    'dist' => [
                        'url' => '{version}.tar.gz',
                        'type' => 'tar',
                    ]
                ]
            ]
        ]);
        $io = new NullIO();
        $composer = new Composer();
        $composer->setPackage($package);

        $plugin = new Plugin();
        $plugin->activate($composer, $io);

        $this->invokeMethod($plugin, 'setArtifactDist', [$package]);
        $this->assertEquals('1.0.0.tar.gz', $package->getDistUrl());
    }

    /**
     * Call protected/private method of a class.
     *
     * @param $object
     * @param $methodName
     * @param array      $parameters
     *
     * @return mixed
     * @throws \ReflectionException
     */
    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
