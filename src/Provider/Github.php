<?php

namespace OpenEuropa\ComposerArtifacts\Provider;

/**
 * Class Github
 */
class Github extends AbstractProvider
{
    /**
     * {@inheritdoc}
     */
    public function updatePackageConfiguration()
    {
        $tokens = $this->getPluginTokens();

        $this->package->setDistUrl(
            \strtr(
                $this->config['dist']['url'],
                $tokens
            )
        );
        $this->package->setDistType(
            \strtr(
                $this->config['dist']['type'],
                $tokens
            )
        );
    }
}
