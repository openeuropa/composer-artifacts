<?php

/**
 * EUROPEAN UNION PUBLIC LICENCE v. 1.2
 * Raphaël Droz, 2019
 *
 * Retrieve a Gitlab artifact and install it in the project
 *
 * Environment:
 * - GITLAB_TOKEN : the GitLab authentication token. Optional.
 * - GITLAB_HOST : The URL of the GitLab instance. Default to https://gitlab.com
 * - PRESERVE_TEMP_FILES : Whether to preserve downloaded tarball. Default to false.
 */

namespace OpenEuropa\ComposerArtifacts\Provider;

use Composer\Installer\PackageEvents;

/**
 * Class Gitlab
 */
class Gitlab extends AbstractProvider
{

    /**
     * Gitlab API URL to download artifact for a given build
     */
    const TMP_SUBDIRECTORY = 'storage';

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

    public function comment($str)
    {
        $this->getPlugin()->getIo()->write('<comment>' . $str . '</comment>');
    }
    public function info($str)
    {
        $this->getPlugin()->getIo()->write('<info>' . $str . '</info>');
    }
    public function error($str)
    {
        $this->getPlugin()->getIo()->writeError($str);
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
     * {@inheritdoc}
     */
    public function updatePackageConfiguration()
    {
        parent::updatePackageConfiguration();
        $config = $this->getConfig();
        $base_path = $this->getAbsoluteInstallPath();

        $token   = getenv('GITLAB_TOKEN');
        $url     = getenv('GITLAB_HOST') ?: ($config['gitlab_host'] ?? 'https://gitlab.com');

        // Repository driver is Vcs\GitLab, Url looks like https://gitlab.com/api/v4/projects/foo%2Fbar%2Fbaz
        $project_api_url = $this->getPackage()->getRepository()->getDriver()->getApiUrl();
        $project_jobs_url = $project_api_url . '/' . $this->getBuildsUrlSuffix();

        $ref     = $config['ref']   ?? preg_replace('/^dev-/', '', $this->getPackage()->getPrettyVersion());
        $job     = $config['job']   ?? getenv('PIPELINE_JOB');
        $stage   = $config['stage'] ?? getenv('PIPELINE_STAGE');
        $tag     = $config['tag']   ?? getenv('GITLAB_TAG');
        $in      = isset($config['in']) ? realpath($config['in']) : $base_path;

        if (!$job && !$stage) {
            throw new \Exception('At least one specific job or stage must be supplied.');
        }

        if (!is_dir($in) || !is_writable($in)) {
            throw new \Exception("Invalid destination directory {$in}, must be a valid path.");
        } elseif (strpos($in, $base_path) === false) {
            throw new \Exception('Destination directory must exist below the project directory.');
        }

        $this->comment($url);
        $build = $this->getLatestSuccessfulBuild(
            $project_jobs_url,
            $token,
            $stage,
            $job,
            $ref,
            $tag
        );

        $this->info(sprintf(
            "Latest build [%s]\n- ref: %s\n- stage: %s\n- name: %s\n- at: %s\n- runner: %d -> %s\n- triggered by: %s",
            $build['id'],
            $build['ref'],
            $build['stage'],
            $build['name'],
            $build['created_at'],
            $build['runner']['id'],
            $build['runner']['description'],
            $build['user']['username']
        ));

        // Gitlab API URL to download artifact for a given build
        $project_artifacts_url = $project_api_url . '/' . 'jobs/' . $build['id'] . '/artifacts';
        $tmpdir  = $base_path . '/' . self::TMP_SUBDIRECTORY;
        $tmpfile = $base_path . '/' . self::TMP_SUBDIRECTORY . '/artifact-' . $build['id'] . '.zip';

        try {
            if (! is_dir($tmpdir)) {
                mkdir($tmpdir, 0700);
            }
            $zip = fopen($tmpfile, 'wb');
            $this->api($project_artifacts_url, $token, $zip);
            fclose($zip);
        } catch (\Exception $error) {
            $this->error('Can\'t download artifact to '.$tmpfile);
            $this->error($error->getMessage());
            fclose($zip);
            return;
        }
        $this->info(sprintf('Downloaded artifacts tarball at %s (%d bytes)', $tmpfile, filesize($tmpfile)));

        $this->installArtifact($tmpfile, $in);
        if (! getenv('PRESERVE_TEMP_FILES')) {
            if (is_writable($tmpfile)) {
                unlink($tmpfile);
            }
            if (is_dir($tmpdir)) {
                rmdir($tmpdir);
            }
        }
    }

    /**
     * Build a curl handle capable to make Gitlab API request
     * @param  string $url
     * @param  string $token Gitlab API Tokan
     * @return array
     * @throws \Exception
     */
    private function api($url, $token, $file = null)
    {
        $h = curl_init();
        if (null === $file) {
            curl_setopt($h, CURLOPT_RETURNTRANSFER, true);
        } else {
            curl_setopt($h, CURLOPT_FILE, $file);
        }
        curl_setopt($h, CURLOPT_URL, $url);
        curl_setopt($h, CURLOPT_FOLLOWLOCATION, true);
        if ($token) {
            curl_setopt($h, CURLOPT_HTTPHEADER, [
                sprintf("PRIVATE-TOKEN: %s", $token)
            ]);
        }

        $raw = curl_exec($h);
        if (curl_getinfo($h, CURLINFO_HTTP_CODE) - 200 > 100) {
            throw new \Exception(sprintf(
                "Error during API call of %s\n[%d] -> %s",
                $url,
                curl_getinfo($h, CURLINFO_HTTP_CODE),
                $raw
            ));
        }
        curl_close($h);

        if (null === ($result = json_decode($raw, true))) {
            throw new \Exception("Unreadable Gitlab API response.");
        }
        return $result;
    }

    /**
     * Retrieve latest successful build information
     * @param  string $url      Gitlab API root URL
     * @param  string $token    Gitlab API Token
     * @return array
     * @throws \Exception
     */
    private function getLatestSuccessfulBuild($url, $token, $stage, $name, $ref, $tag)
    {
        $results = $this->api($url, $token);
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
     * Install artifact in the specified folder
     * @param  string $path
     * @param  string $in
     * @throws \Exception
     */
    private function installArtifact($path, $in)
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) === true) {
            $zip->extractTo($in);
            $zip->close();
            $this->info('Artifact extracted in : '.$in);
        } else {
            throw new \Exception(sprintf(
                'Can\'t extract ZIP file "%s" in "%s"',
                basename($path),
                $inc
            ));
        }
    }
}
