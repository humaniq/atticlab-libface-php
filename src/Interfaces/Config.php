<?php

namespace Atticlab\Libface\Interfaces;

/**
 * Interface ConfigInterface
 * @package App\Lib\Face\Configs
 */
interface Config
{
    /**
     * Validation for the configuration
     * @return mixed
     */
    public function validate();
}