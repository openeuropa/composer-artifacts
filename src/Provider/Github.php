<?php

namespace OpenEuropa\ComposerArtifacts\Provider;

/**
 * Class Github.
 */
class Github extends AbstractProvider
{
    /**
     * {@inheritdoc}
     */
    public function updatePackageConfiguration()
    {
        parent::updatePackageConfiguration();

        $tokens = $this->getPluginTokens();
        $config = $this->getConfig();

        $this->getPackage()->setDistUrl(
            \strtr(
                $config['dist']['url'],
                $tokens
            )
        );
        $this->getPackage()->setDistType(
            \strtr(
                $config['dist']['type'],
                $tokens
            )
        );
    }
}
