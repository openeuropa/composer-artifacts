<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use Composer\Util\Filesystem;
use OpenEuropa\ComposerArtifacts\Plugin;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Integration tests.
 */
class PluginIntegrationTest extends TestCase {

    /**
     * Fixtures root.
     */
    const FIXTURES = __DIR__ . '/fixtures';

    /**
     * {@inheritdoc}
     */
    protected function setUp() {
        $fs = new Filesystem();
        $fs->remove(self::FIXTURES . '/main/composer.lock');
        $fs->removeDirectory(self::FIXTURES . '/main/vendor');
    }

    /**
     * Test install command.
     */
    public function testInstall() {
        $input = new ArrayInput([
            'command' => 'install',
            '--working-dir' => self::FIXTURES . '/main'
        ]);
        $application = new TestPluginApplication(new Plugin());
        $application->setAutoExit(FALSE);

        $output = new BufferedOutput();
        $application->run($input, $output);

        $this->assertFileExists(self::FIXTURES.'/main/vendor/openeuropa/dependency-1/composer.json');
        $this->assertFileExists(self::FIXTURES.'/main/vendor/openeuropa/dependency-2/composer.json');
    }
}
