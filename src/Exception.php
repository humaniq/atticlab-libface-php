<?php
/**
 * Created by PhpStorm.
 * User: 7
 * Date: 29.05.2017
 * Time: 12:42
 */

namespace Atticlab\Libface;


class Exception extends \Exception
{
    const INVALID_CONFIG = 1000;
    const EMPTY_IMAGE_DATA = 1001;
    const INVALID_IMAGE_ENCODING = 1002;
    const INVALID_IMAGE = 1003;
    const UNKNOWN_SERVICE = 1004;
    const NO_FACES_FOUND = 1005;
    const MANY_FACES_FOUND = 1006;
    const BAD_SERVICE_RESPONSE = 5000;
    const TRANSPORT_ERROR = 6000;

    private $_errors = [
        self::INVALID_CONFIG => 'API is not configured properly',
        self::EMPTY_IMAGE_DATA => 'Got empty image, expecting image data at base64 encoding',
        self::INVALID_IMAGE_ENCODING => 'Failed to decode image. Only base64 is accepted',
        self::INVALID_IMAGE => 'Image has unsupported type or too big/too small size',
        self::UNKNOWN_SERVICE => 'Service ID is invalid or unconfigured',
        self::NO_FACES_FOUND => 'No faces was found on image',
        self::MANY_FACES_FOUND => 'Two or more faces was found on image',

        self::BAD_SERVICE_RESPONSE => 'Unexpected response from service',

        self::TRANSPORT_ERROR => 'Http request error',
    ];

    public function __construct($err_code = null, $err_details = null)
    {
        if (empty($err_code) || !array_key_exists($err_code, $this->_errors)) {
            return parent::__construct('Unexpected exception code', $err_code);
        }

        $err_message = $this->_errors[$err_code];
        if(!empty($err_details)){
            $err_message.=' ['.trim($err_details).']';
        }

        return parent::__construct($err_message, $err_code);
    }
}