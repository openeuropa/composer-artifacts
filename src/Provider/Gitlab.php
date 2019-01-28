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
use OpenEuropa\ComposerArtifacts\Utils;
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
    public static function urlInsertGitlabToken($url) {
        if (getenv('GITLAB_TOKEN')) {
            $parsed = parse_url($url);
            $parsed['user'] = getenv('GITLAB_TOKEN');
            $parsed['pass'] = 'private-token';
            $url = Utils::http_build_url($parsed);
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
        switch ($this->getEvent()->getName()) {
            case 'pre-package-install':
            case 'pre-package-update':
                $this->getPackage()->setDistUrl($project_artifacts_url);
                $this->getPackage()->setDistType('zip');
                return;
        }

        /**
         * Use Composer internal downloading mechanism by setting a dist URL
         */
        $this->getPackage()->setDistUrl($project_artifacts_url);
        // setTargetDir($in); // ToDo

        /**
         * Note that default Zip downloader (and extractor) can't be made to overwrite existing files.
         * While it fits most situations, an alternative is provided and used is the `overwrite` key is used.
         * @see https://github.com/composer/composer/blob/master/src/Composer/Downloader/ZipDownloader.php#L187
         */
        // ToDo: deal with it using "multiple dist URLs"
        if (!$overwrite) {
            $this->getPlugin()->getIo()->write("<info>Download tarball using default ZipDownloader");
            $this->getPackage()->setDistType('zip'); // ToDo: test
        }
        else {
            $this->getPlugin()->getIo()->write("<info>Download tarball using EnhancedZipDownloader");
            $dm = $this->getEvent()->getComposer()->getDownloadManager();
            $downloader = new EnhancedZipDownloader($this->getPlugin()->getIo(),
                                                    $this->getEvent()->getComposer()->getConfig());

            $dm->setDownloader('ezip', $downloader);
            $this->getPackage()->setDistType('ezip');
        }
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
        $downloader = new RemoteFilesystem($this->getPlugin()->getIo(),
                                           $this->getEvent()->getComposer()->getConfig());
        $raw = $downloader->getContents(parse_url($url, PHP_URL_HOST),
                                              $url,
                                              $progress = false,
                                              $options = ['redirects' => 5]);

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
}
