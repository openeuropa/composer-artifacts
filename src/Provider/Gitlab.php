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
use Composer\Plugin\PluginInterface;
use OpenEuropa\ComposerArtifacts\EnhancedZipDownloader;

/**
 * Class Gitlab
 */
class Gitlab extends AbstractProvider
{

    const PRE_PACKAGE_EVENTS  = ['pre-package-install', 'pre-package-update'];
    const POST_PACKAGE_EVENTS = ['post-package-install', 'post-package-update'];

    /**
     * Gitlab API URL of project builds collection.
     */
    const BUILD_URL_SUFFIX =  'jobs?scope[]=success';

    /**
     * Set user and password to the GitLab API URL so that
     * \Composer\Util\RemoteFilesystem correctly set the
     * PRIVATE-TOKEN: XXX header.
     * @see https://github.com/composer/composer/blob/master/src/Composer/Util/RemoteFilesystem.php#L823
     */
    public function getProjectApiUrl()
    {
        /**
         * Url looks like https://gitlab.com/api/v4/projects/foo%2Fbar%2Fbaz
         * Use `composer -vvv` to display.
         * @var \Composer\Repository\Vcs\GitLab
         */
        $url = $this->getPackage()->getRepository()->getDriver()->getApiUrl();

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

        $in      = isset($config['in'])    ? $config['in']    : false;
        $ref     = isset($config['ref'])   ? $config['ref']   : preg_replace('/^dev-/', '', $this->getPackage()->getPrettyVersion());
        $job     = isset($config['job'])   ? $config['job']   : getenv('PIPELINE_JOB');
        $stage   = isset($config['stage']) ? $config['stage'] : getenv('PIPELINE_STAGE');
        $tag     = isset($config['tag'])   ? $config['tag']   : getenv('GITLAB_TAG');

        if (!$job && !$stage) {
            throw new \Exception('At least one specific job or stage must be supplied.');
        }

        if (isset($config['in'])) {
            if (! array_intersect($config['events'], self::POST_PACKAGE_EVENTS)) {
                throw new \Exception("Custom destination directory only makes sense"
                                     . " when extracting artifact on-top of the package rather than replacing it.");
            }
        }

        $project_api_url = $this->getProjectApiUrl();

        $all_builds = $this->getBuilds($project_api_url . '/' . static::BUILD_URL_SUFFIX);
        $build = self::filterBuilds($all_builds, $stage, $job, $ref, $tag);

        // Gitlab API URL to download artifact for a given build
        $project_artifacts_url = $project_api_url . '/jobs/' . $build['id'] . '/artifacts';

        $this->getPlugin()->getIo()->write(sprintf(
            "<info>Latest build [%s]\n- ref: %s\n- stage: %s\n- name: %s\n- at: %s\n"
            . "- runner: %d -> %s\n- triggered by: %s</info>",
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
        if (in_array($this->getEvent()->getName(), self::PRE_PACKAGE_EVENTS, true)) {
            /**
             * Use Composer internal downloading mechanism by setting a dist URL
             */
            $this->getPackage()->setDistUrl($project_artifacts_url);
            $this->getPackage()->setDistType('zip');
            return;
        }

        /**
         * At this point, the event is one of POST_PACKAGE_EVENTS.
         * Setting setDistUrl() without manually running a Downloader is useless: package
         * has already been downloaded and extracted (source of dist). Prepare for custom download.
         * Note: Since we initialize the downloader by hand and call download() manually, relying on
         * $this->getPackage()->setTargetDir() is not an option.
         */

        $this->getPackage()->setExtra([
            'buildId'      => $build['id'],
            'artifactsUrl' => $project_artifacts_url
        ]);

        $dm = $this->getEvent()->getComposer()->getDownloadManager();
        $path = $this->getAbsoluteInstallPath($in);
        if (version_compare(PluginInterface::PLUGIN_API_VERSION, '2.0.0') >= 0) {
            $this->builtInDownload($dm, $project_artifacts_url, $path);
        } else {
            $this->customDownload($dm, $project_artifacts_url, $path);
        }
    }

    /**
     * Retrieve latest CI builds as JSON
     * @param  string $url Gitlab API root URL
     * @return array
     * @throws \Exception
     */
    public function getBuilds($url)
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
            throw new \Exception('Unreadable Gitlab API response.');
        }
        if (!$results) {
            throw new \Exception("No build found for the project at $url.");
        }

        return $results;
    }

    /**
     * Filter builds listing and extracts successful ones matching
     * a set of criterias.
     */
    private static function filterBuilds($results, $stage, $name, $ref, $tag)
    {
        foreach ($results as $item) {
            if ($item['status'] === 'success'
                && (!$stage || $item['stage'] === $stage)
                && (!$name || $item['name'] === $name)
                && (!$ref || $item['ref'] === $ref)
                && (!$tag || $item['tag'] === $tag)) {
                return $item;
            }
        }
        $debug = json_encode(array_filter(compact("stage", "name", "ref", "tag")));
        throw new \Exception("No successful build found for project: $debug.");
    }

    /**
     * When user request to extract artifacts nested within package directory tree
     * instead of top-level, we want to ensure this directory already exist.
     * Should we allow to extract artifacts *outside* package directory?
     */
    private function getAbsoluteInstallPath($subdirectory = false)
    {
        if ($subdirectory) {
            $abs_subdirectory = realpath($this->getInstallPath() . '/' . $subdirectory);
            if (!$abs_subdirectory || !is_dir($abs_subdirectory) || !is_writable($abs_subdirectory)) {
                throw new \Exception("Directory {$abs_subdirectory} is not an existing writable directory.");
            }
            if (strpos($abs_subdirectory, getcwd() . '/' . $this->getInstallPath()) === false) {
                throw new \Exception('Artifacts destination directory must be inside the package directory.');
            }
            return $abs_subdirectory;
        }

        if (! ($path = realpath($this->getInstallPath()))) {
            throw new \Exception(sprintf('Could not find destination directory (cwd=%s, install-path=%s)', getcwd(), $this->getInstallPath()));
        }

        return $path;
    }

    /**
     * A mean to call our custom downloader.
     */
    protected function customDownload(DownloadManager $dm, $url, $path)
    {
        $downloader = new EnhancedZipDownloader($this->getPlugin()->getIo(), $this->getEvent()->getComposer()->getConfig());
        $p = $this->getPackage();
        // dm->setDownloader('zip+artifacts', $downloader)->setDistUrl($url)->setDistType('zip+artifacts')->setSourceType('git+artifacts');
        // $downloader = $dm->getDownloader($p->getDistType());
        $this->getPlugin()->getIo()->write(sprintf(
            "<info>Download artifacts using %s into %s",
            get_class($downloader),
            $path
        ));

        $downloader->download($p, $path);
    }

    /**
     * While looking promising at first glance, the built-in Zipdownloader (+ extractor) forcefully
     * clean-up the destination directory. In POST_PACKAGE_* events where artifacts complement the
     * project, this is useless. Kept here until this is patched upstream or a workaround is found.
     * @see https://github.com/composer/composer/blob/master/src/Composer/Downloader/ZipDownloader.php#L187
     * @see https://github.com/composer/composer/issues/7929
     */
    private function builtInDownload(DownloadManager $dm, $url, $path)
    {
        $downloader = $dm->getDownloader('zip');
        $this->getPlugin()->getIo()->write(sprintf(
            "<info>Download artifacts using %s into %s",
            get_class($downloader),
            $path
        ));
        $p = $this->getPackage();
        $p->setDistUrl($url);
        $p->setSourceType('');
        $p->setDistType('zip');

        $downloader->download($p, $path);
    }
}
