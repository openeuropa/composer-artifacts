<?php

namespace OpenEuropa\ComposerArtifacts\Provider;

/**
 * Interface AbstractProviderInterface
 */
interface AbstractProviderInterface
{
    /**
    * Update a package properties.
    */
    public function updatePackageConfiguration();

    /**
     * Returns tokens from the package.
     *
     * @return string[]
     *   An array of tokens and values.
     */
    public function getPluginTokens();
}
