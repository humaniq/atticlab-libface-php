<?php

namespace Atticlab\Libface\Interfaces;

/**
 * Interface Recognition
 * @package Atticlab\Libface\Interfaces
 */
interface Recognition
{
    /**
     * Return server ID, from defined contant
     * @return integer
     */
    public function getServiceID();

    /**
     * Generate human readable recognition service name
     * @param int $status_code
     * @return array $service_status
     */
    public function getServiceName();

    /**
     * Check service availability
     * @return array $service_status
     */
    public function checkServiceAvailability();

    /**
     * Build Psr7 http request
     * @param string $image_base64
     * @return \GuzzleHttp\Psr7\Request
     */
    public function prepareRecognitionRequest($image_base64);

    /**
     * Get existed face id by image
     * @param string $image_base64
     * @return string || null
     */
    public function getFaceID($image_base64);

    /**
     * Create face id by image
     * @param string $image_base64
     * @return string || null
     */
    public function createFaceID($image_base64);

    /**
     * Process HTTP response from face API after recognition
     * @param \GuzzleHttp\Psr7\Response $response
     * @return mixed
     */
    public function processRecognitionHttpResponse(\GuzzleHttp\Psr7\Response $response);

    /**
     * Build Psr7 http request
     * @param string $image_base64
     * @return \GuzzleHttp\Psr7\Request
     */
    public function prepareCreateRequest($image_base64);

    /**
     * Process HTTP response from face API after create
     * @param \GuzzleHttp\Psr7\Response $response
     * @return mixed
     */
    public function processCreateHttpResponse(\GuzzleHttp\Psr7\Response $response);
}