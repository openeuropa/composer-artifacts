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

namespace OpenEuropa\ComposerArtifacts\Provider;

use Composer\Installer\PackageEvents;
use Composer\Util\RemoteFilesystem;
use Composer\Downloader\DownloadManager;
use OpenEuropa\ComposerArtifacts\EnhancedZipDownloader;

/**
 * Class Gitlab
 */
class Gitlab extends AbstractProvider
{

    /**
     * Gitlab API URL of project builds collection.
     *
     * Emulating GitLab URL on a filesystem is not that easy.
     * When hitting /job (which returns JSON of latest jobs), return /jobs.txt
     * otherwise returns the requested artifact stored under the /jobs directory.
     */
    private function getBuildsUrlSuffix()
    {
        if (getenv('PHPUNIT_TEST') == 1) {
            return 'jobs.txt';
        } else {
            return '/jobs?scope[]=success';
        }
    }

    private function getAbsoluteInstallPath()
    {
        $path = $this->getInstallPath();
        /* The project directory may not (yet) exists. As such, relying on realpath() isn't adequate.
           If the leading component of the project path exists in $CWD, assume it is. */
        if (file_exists(preg_replace('!/.*!', '', $path))) {
            return realpath($path) ? : (getcwd() . '/' . $path);
        } else {
            return realpath($path) ? : (getcwd() . '/' . $path);
        }
    }

    /**
     * Set user and password to the GitLab API URL so that
     * \Composer\Util\RemoteFilesystem correctly set the
     * PRIVATE-TOKEN: XXX header.
     * @see https://github.com/composer/composer/blob/master/src/Composer/Util/RemoteFilesystem.php#L823
     */
    public static function urlInsertGitlabToken($url)
    {
        if (getenv('GITLAB_TOKEN')) {
            $parsed = parse_url($url);
            $parsed['user'] = getenv('GITLAB_TOKEN');
            $parsed['pass'] = 'private-token';
            $url = \http_build_url($parsed);
        }
        return $url;
    }

    /**
     * {@inheritdoc}
     */
    public function updatePackageConfiguration()
    {
        parent::updatePackageConfiguration();
        $config = $this->getConfig();

        $in      = false;
        $ref     = isset($config['ref'])   ? $config['ref']   : preg_replace('/^dev-/', '', $this->getPackage()->getPrettyVersion());
        $job     = isset($config['job'])   ? $config['job']   : getenv('PIPELINE_JOB');
        $stage   = isset($config['stage']) ? $config['stage'] : getenv('PIPELINE_STAGE');
        $tag     = isset($config['tag'])   ? $config['tag']   : getenv('GITLAB_TAG');
        $overwrite = (isset($config['overwrite']) && $config['overwrite']);

        if (!$job && !$stage) {
            throw new \Exception('At least one specific job or stage must be supplied.');
        }

        if (isset($config['in'])) {
            $in = realpath($config['in']);
            if (!is_dir($in) || !is_writable($in)) {
                throw new \Exception("Invalid destination directory {$in}, must be a valid path.");
            } elseif (strpos($in, $this->getAbsoluteInstallPath()) === false) {
                throw new \Exception('Destination directory must exist below the project directory.');
            }
        }

        // Repository driver is Vcs\GitLab, Url looks like https://gitlab.com/api/v4/projects/foo%2Fbar%2Fbaz
        // Use `composer -vvv` to show it.
        $project_api_url = $this->getPackage()->getRepository()->getDriver()->getApiUrl();
        $project_api_url = self::urlInsertGitlabToken($project_api_url);
        $project_jobs_url = $project_api_url . '/' . $this->getBuildsUrlSuffix();

        $build = $this->getLatestSuccessfulBuild(
            $project_jobs_url,
            $stage,
            $job,
            $ref,
            $tag
        );

        // Gitlab API URL to download artifact for a given build
        $project_artifacts_url = $project_api_url . '/' . 'jobs/' . $build['id'] . '/artifacts';

        $this->getPlugin()->getIo()->write(sprintf(
            "<info>Latest build [%s]\n- ref: %s\n- stage: %s\n- name: %s\n- at: %s\n- runner: %d -> %s\n- triggered by: %s</info>",
            $build['id'],
            $build['ref'],
            $build['stage'],
            $build['name'],
            $build['created_at'],
            $build['runner']['id'],
            $build['runner']['description'],
            $build['user']['username']
        ));

        /**
         * If artifact download was bound on PRE_PACKAGE_* events, that means the artifacts
         * are inteded to be downloaded before the project itself.
         * Assume they must be * represents everything needed and replace the project tarball.
         *
         * In this case:
         * - We rely upon Composer built-in downloader and just substitute the dittribution URL.
         * - Composer is able to download a private tarball because $project_artifacts_url contains the API credentials,
         * - Since the project was not extracted, not directory hierarchy exists yet. The `in` parameter allowing
         *   to extract in a custom subdirectory is thus ignored.
         */
        if (in_array($this->getEvent()->getName(), ['pre-package-install', 'pre-package-update'], true)) {
            /**
             * Use Composer internal downloading mechanism by setting a dist URL
             */
            $this->getPackage()->setDistUrl($project_artifacts_url);
            $this->getPackage()->setDistType('zip');
            return;
        }

        /**
         * Now in POST_PACKAGE_* events.
         * Setting setDistUrl() without manually running a Downloader would be useless because package
         * has already been downloaded and extracted (source of dist). Prepare for custom download.
         */

        // setTargetDir($in); // ToDo
        $this->getPackage()->setExtra([
            'buildId'      => $build['id'],
            'artifactsUrl' => $project_artifacts_url
        ]);

        $dm = $this->getEvent()->getComposer()->getDownloadManager();
        // $this->builtInDownload($dm, $project_artifacts_url);
        $this->customDownload($dm, $project_artifacts_url);
    }

    /**
     * Retrieve latest successful build information
     * @param  string $url      Gitlab API root URL
     * @return array
     * @throws \Exception
     */
    private function getLatestSuccessfulBuild($url, $stage, $name, $ref, $tag)
    {
        /**
         * @var \Composer\Util\RemoteFilesystem
         */
        $downloader = new RemoteFilesystem(
            $this->getPlugin()->getIo(),
            $this->getEvent()->getComposer()->getConfig()
        );
        $raw = $downloader->getContents(
            parse_url($url, PHP_URL_HOST),
            $url,
            $progress = false,
            $options = ['redirects' => 5]
        );

        if (null === ($results = json_decode($raw, true))) {
            throw new \Exception("Unreadable Gitlab API response.");
        }
        if (!$results) {
            throw new \Exception('No successful build found for the project.');
        }
        foreach ($results as $item) {
            if ($item['status'] === 'success'
                && (!$stage || $item['stage'] === $stage)
                && (!$name || $item['name'] === $name)
                && (!$ref || $item['ref'] === $ref)
                && (!$tag || $item['tag'] === $tag)) {
                return $item;
            }
        }
        throw new \Exception('No successful build found for the project.');
    }

    /**
     * A mean to call our custom downloader.
     */
    private function customDownload(DownloadManager $dm, $url)
    {
        $downloader = new EnhancedZipDownloader($this->getPlugin()->getIo(), $this->getEvent()->getComposer()->getConfig());
        $p = $this->getPackage();
        /* dm->setDownloader('zip+artifacts', $downloader);
           $p->setDistUrl($url);
           $p->setDistType('zip+artifacts');
           $p->setSourceType('git+artifacts');
           $downloader = $dm->getDownloader($p->getDistType()); */
        $this->getPlugin()->getIo()->write(sprintf(
            "<info>Download artifacts using %s into %s",
            get_class($downloader),
            $this->getAbsoluteInstallPath()
        ));

        $downloader->download($p, $this->getAbsoluteInstallPath());
    }

    /**
     * While looking promising at first glance, the built-in Zipdownloader (+ extractor) forcefully
     * clean-up the destination directory. In POST_PACKAGE_* events where artifacts complement the
     * project, this is useless. Kept here until this is patched upstream or a workaround is found.
     * @see https://github.com/composer/composer/blob/master/src/Composer/Downloader/ZipDownloader.php#L187
     */
    private function builtInDownload(DownloadManager $dm, $url)
    {
        $downloader = $dm->getDownloader('zip');
        $this->getPlugin()->getIo()->write(sprintf(
            "<info>Download artifacts using %s into %s",
            get_class($downloader),
            $this->getAbsoluteInstallPath()
        ));
        $p = $this->getPackage();
        $p->setDistUrl($url);
        $p->setSourceType('');
        $p->setDistType('zip');
        $downloader->download($p, $this->getAbsoluteInstallPath());
    }
}
