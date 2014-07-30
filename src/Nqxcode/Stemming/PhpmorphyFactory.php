<?php namespace Nqxcode\Stemming;

use \phpMorphy;

class PhpmorphyFactory
{
    public function newInstance($directory, $language, $options)
    {
        return new phpMorphy($directory, $language, $options);
    }
} 