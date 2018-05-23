<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use OpenEuropa\ComposerArtifacts\Plugin;

/**
 * Class TestPlugin
 */
class TestPlugin extends Plugin
{
    /**
     * @param array $tokens
     */
    public function setPluginTokensAsArray(array $tokens)
    {
        $this->tokens = array_merge((array) $this->tokens, $tokens);
    }
}
