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

namespace OpenEuropa\ComposerArtifacts\Tests;

use Composer\Installer\PackageEvents;
use Composer\Util\RemoteFilesystem;
use Composer\Downloader\DownloadManager;
use Composer\Plugin\PluginInterface;
use OpenEuropa\ComposerArtifacts\EnhancedZipDownloader;
use OpenEuropa\ComposerArtifacts\Provider\Gitlab;

/**
 * Class GitlabMock
 */
class GitlabMock extends Gitlab
{
    /**
     * Emulating GitLab URL on a filesystem is not that easy.
     * When hitting /jobs (which returns JSON of latest jobs), return /jobs.txt
     * otherwise returns the requested artifact stored under the /jobs directory.
     */
    const BUILD_URL_SUFFIX = 'jobs.txt';
        
    /**
     * When GitLab URL on a filesystem, a PathRepository is automatically
     * chosen by composer instead of a \Composer\Repository\Vcs\GitLab.
     */
    public function getProjectApiUrl()
    {
        return '../artifacts/gitlab/api/v4/projects/' . urlencode($this->getPackage()->getName());
    }

    public function getBuilds($path)
    {
        $path = realpath(getcwd() . '/' . $path);
        return json_decode(file_get_contents($path), true);
    }
}
