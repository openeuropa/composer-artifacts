<?php

namespace OpenEuropa\ComposerArtifacts\Tests;

use OpenEuropa\ComposerArtifacts\Plugin;

class TestPlugin extends Plugin
{
    /**
     * @param array $tokens
     */
    public function setPluginTokensAsArray(array $tokens)
    {
        $this->tokens = array_merge((array) $this->tokens, $tokens);
    }

    /**
     * @return string[]
     */
    public function getPluginTokens()
    {
        return $this->tokens;
    }
}
