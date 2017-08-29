<?php
namespace Atticlab\Libface\Configs;

use Atticlab\Libface\Interfaces\Config;

class FindFace extends BaseConfig implements Config
{
    /**
     * Lambdal Api url
     */
    const HOST_NAME = "https://api.findface.pro/v1";

    /**
     * Used for check service availability
     * Lambdal status url
     */
    const STATUS_URL = self::HOST_NAME . '/faces';

    /**
     * @var string
     * @see get token on https://findface.pro/en/
     */
    public $token;

    /**
     * @var string
     * @see https://api.findface.pro/v1/docs/v1/methods-facenapi-saas-handlers-saasgalleryhandler-post.html
     */
    public $gallery_name;

    /**
     * Validate configuration variables
     */
    public function validate()
    {
        parent::validate();
        $this->token = trim($this->token);

        $length = strlen($this->token);
        if ($length != 32) {
            throw new \InvalidArgumentException('Invalid mashape key [' . $this->token . ']');
        }

        $this->gallery_name = trim($this->gallery_name);
        if (empty($this->gallery_name)) {
            throw new \InvalidArgumentException('Empty gallery name');
        }
    }
}