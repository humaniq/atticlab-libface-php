<?php
namespace Atticlab\Libface\Configs;
use Atticlab\Libface\Interfaces\Config;

abstract class BaseConfig implements Config
{
    /**
     * @var int
     * Indicates maximum responses count before alert
     */
    public $limit;

    /**
     * Validate configuration variables
     */
    public function validate() {
        if (!is_int($this->limit)) {
            throw new \InvalidArgumentException('Invalid limit [' . $this->limit . ']');
        }
    }
}