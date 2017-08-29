<?php
namespace Atticlab\Libface\Configs;

use Atticlab\Libface\Interfaces\Config;

class Kairos extends BaseConfig implements Config
{
    /**
     * Kairos Api url
     */
    const HOST_NAME = "http://api.kairos.com";

    /**
     * Used for check service availability
     * Kairos status url
     */
    const STATUS_URL = self::HOST_NAME . "/v2/";

    // @TODO: Link to documentation
    // @TODO: Instructions how to get application id
    /**
     * @var integer
     * @see  Link to documentation
     */
    public $application_id;

    // @TODO: Short description
    // @TODO: Format
    /**
     * @var string
     */
    public $application_key;

    // @TODO: Description!! Why to use it, link to documentation
    /**
     * @var string
     */
    public $gallery_name;

    /**
     * Validate configuration variables
     */
    public function validate()
    {
        parent::validate();
        $this->application_id = trim(strtolower($this->application_id));
        $length = strlen($this->application_id);
        if ($length != 8) {
            throw new \InvalidArgumentException('Invalid application ID [' . $this->application_id . ']');
        }

        $this->application_key = trim(strtolower($this->application_key));
        $length = strlen($this->application_key);
        if ($length != 32) {
            throw new \InvalidArgumentException('Invalid application KEY [' . $this->application_key . ']');
        }

        $this->gallery_name = trim($this->gallery_name);
        if (empty($this->gallery_name)) {
            throw new \InvalidArgumentException('Empty gallery name');
        }
    }
}