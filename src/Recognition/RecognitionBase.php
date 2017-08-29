<?php

namespace Atticlab\Libface\Recognition;

/**
 * Class RecognitionBase
 * @package App\Lib\Face\Recognition
 */
class RecognitionBase
{
    protected $config;

    public function getLimit() {
        return $this->config->limit;
    }

    /**
     * Generate human readable service name
     * @return string
     */
    public function getServiceName()
    {
        return (new \ReflectionClass($this))->getShortName();
    }
}
