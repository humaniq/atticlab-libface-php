<?php

namespace Atticlab\Libface;

/**
 * Class Response
 * @package Atticlab\Libface
 */
class Response
{
    /**
     * ID of service that recognize image
     * @var integer
     */
    public $service_id;

    /**
     * Face id that was returned by service after recognize image
     * @var string || null
     */
    public $face_id;


    /**
     * Response constructor.
     * @param $service_id
     * @param $face_id
     */
    public function __construct($service_id, $face_id)
    {
        if (empty($service_id)) {
            throw new \InvalidArgumentException('Empty face recognition service id');
        }

        $this->service_id = $service_id;
        $this->face_id = $face_id;
    }
}