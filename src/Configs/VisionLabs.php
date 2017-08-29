<?php
namespace Atticlab\Libface\Configs;

use Atticlab\Libface\Interfaces\Config;

class VisionLabs extends BaseConfig implements Config
{
    /**
     * VisionLabs Api url
     */
    const HOST_NAME = "https://luna.faceis.ru/1";

    /**
     * Used for check service availability
     * VisionLabs status url
     */
    const STATUS_URL = self::HOST_NAME . "/version";

    // @TODO: Link to documentation
    // @TODO: Instructions how to get application id
    /**
     * @var string
     * @see  Link to documentation
     */
    public $token;

    // @TODO: Short description
    // @TODO: Format
    /**
     * @var string
     */
    public $descriptor_lists;

    // @TODO: Description!! Why to use it, link to documentation
    /**
     * @var string
     */
    public $person_lists;

    /**
     * Validate configuration variables
     */
    public function validate()
    {
        parent::validate();
        $this->token = trim(strtolower($this->token));
        $length = strlen($this->token);
        if ($length != 36) {
            throw new \InvalidArgumentException('Invalid TOKEN [' . $this->token . ']');
        }

        $this->descriptor_lists = trim(strtolower($this->descriptor_lists));
        $length = strlen($this->descriptor_lists);
        if ($length != 36) {
            throw new \InvalidArgumentException('Invalid descriptor LIST [' . $this->descriptor_lists . ']');
        }

        $this->person_lists = trim($this->person_lists);
        $length = strlen($this->person_lists);
        if ($length != 36) {
            throw new \InvalidArgumentException('Invalid person LIST [' . $this->person_lists . ']');
        }
    }
}