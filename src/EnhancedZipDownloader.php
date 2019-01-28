<?php

/**
 * EUROPEAN UNION PUBLIC LICENCE v. 1.2
 * Raphaël Droz, 2019
 *
 * Retrieve a Gitlab artifact and install it in the project
 *
 * Environment:
 * - GITLAB_TOKEN : the GitLab authentication token. Optional.
 */

namespace OpenEuropa\ComposerArtifacts;

use Composer\Config;
use Composer\Cache;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Package\PackageInterface;
use Composer\Util\ProcessExecutor;
use Composer\Util\RemoteFilesystem;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;

/**
 * An improved Zip archive downloader with the following particularities:
 * - Do not forcefully clean-up the destination directory before any operation.
 * - Allow to preserve the temporary archive file if the PRESERVE_TEMP_FILES
 *   environment variable is set.
 */
class EnhancedZipDownloader extends \Composer\Downloader\ZipDownloader
{

    public function __construct(IOInterface $io, Config $config, EventDispatcher $eventDispatcher = null, Cache $cache = null, ProcessExecutor $process = null, RemoteFilesystem $rfs = null)
    {
        parent::__construct($io, $config, $eventDispatcher, $cache, $process, $rfs);
    }

    public function download(PackageInterface $package, $path, $output = true)
    {
        // die('reached!');
        $temporaryDir = $this->config->get('vendor-dir').'/composer/'.substr(md5(uniqid('', true)), 0, 8);
        if (! is_dir($temporaryDir)) {
            mkdir($temporaryDir, 0700);
        }

        $url = $package->getDistUrls();
        $tmpfile = $temporaryDir . '/artifact-' . $build['id'] . '.zip';

        $fileName = $this->doDownload2(null, $tmpfile, $url);
        var_dump($fileName);
        $this->io->debug(sprintf('Downloaded artifacts tarball at %s (%d bytes) and going to extract to %s', $tmpfile, filesize($tmpfile), $path));

        $this->extract($tmpfile, $path);
        $this->io->debug('Artifact extracted in ' . $path);

        if (!getenv('PRESERVE_TEMP_FILES')) {
            $this->filesystem->emptyDirectory($temporaryDir);
        }
    }

    protected function doDownload2(PackageInterface $package, $path, $url)
    {
        $preFileDownloadEvent = new PreFileDownloadEvent(PluginEvents::PRE_FILE_DOWNLOAD, $this->rfs, $url);
        if ($this->eventDispatcher) {
            $this->eventDispatcher->dispatch($preFileDownloadEvent->getName(), $preFileDownloadEvent);
        }
        $rfs = $preFileDownloadEvent->getRemoteFilesystem();
        $copy = $rfs->copy(parse_url($url, PHP_URL_HOST),
                           $url,
                           $path,
                           $progress = false,
                           $options = ['redirects' => 5]);

        if (!$copy) {
            throw new \Exception(sprintf("Can't download artifact to %s Error during API call of %s", $path, $url));
        }
        if (!file_exists($path)) { // Don't know why RemoteFilesystem::copy() does not create `$path` itself (bug?)
            file_put_contents($path, $copy);
        }
        return $path;
    }
}
