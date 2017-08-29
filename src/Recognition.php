<?php

namespace Atticlab\Libface;

use GuzzleHttp\Client as HTTP;
use GuzzleHttp\Promise;

class Recognition
{
    use \Atticlab\Libface\Traits\Logger;

    /**
     * @var \Atticlab\Libface\Recognition\Kairos
     */
    private $kairos;

    /**
     * @var \Atticlab\Libface\Recognition\VisionLabs
     */
    private $vision_labs;

    /**
     * @var \Atticlab\Libface\Recognition\FindFace
     */
    private $find_face;

    /**
     * @var \Atticlab\Libface\Interfaces\Recognition[]
     */
    private $services = [];

    const TIMEOUT = 10;

    /**
     * Recognition constructor.
     * @param \Psr\Log\LoggerInterface|null $logger Prs3 logger
     */
    public function __construct(\Psr\Log\LoggerInterface $logger = null)
    {
        if ($logger instanceof \Psr\Log\LoggerInterface) {
            $this->setLogger($logger);
        }
    }

    /**
     * Enable and configure Kairos API
     * @param string $application_id
     * @param string $application_key
     * @param string $gallery_name
     */
    public function enableKairos($application_id, $application_key, $gallery_name, $limit)
    {
        $config = new \Atticlab\Libface\Configs\Kairos();
        $config->application_id = $application_id;
        $config->application_key = $application_key;
        $config->gallery_name = $gallery_name;
        $config->limit = $limit;

        $this->kairos = new \Atticlab\Libface\Recognition\Kairos($config, $this->_logger);
        $service_id = $this->kairos->getServiceID();
        $this->services[$service_id] = &$this->kairos;
    }

    /**
     * Enable and configure VisionLabs API
     * @param string $token
     * @param string $descriptor_lists
     * @param string $person_lists
     */
    public function enableVisionLabs($token, $descriptor_lists, $person_lists, $limit)
    {
        $config = new \Atticlab\Libface\Configs\VisionLabs();
        $config->token = $token;
        $config->descriptor_lists = $descriptor_lists;
        $config->person_lists = $person_lists;
        $config->limit = $limit;

        $this->vision_labs = new \Atticlab\Libface\Recognition\VisionLabs($config, $this->_logger);
        $service_id = $this->vision_labs->getServiceID();
        $this->services[$service_id] = &$this->vision_labs;
    }

    /**
     * Enable and configure FindFace API
     * @param string $token
     * @param string $gallery_name
     * @param string $person_lists
     */
    public function enableFindFace($token, $gallery_name, $limit)
    {
        $config = new \Atticlab\Libface\Configs\FindFace();
        $config->token = $token;
        $config->gallery_name = $gallery_name;
        $config->limit = $limit;

        $this->find_face = new \Atticlab\Libface\Recognition\FindFace($config, $this->_logger);
        $service_id = $this->find_face->getServiceID();
        $this->services[$service_id] = &$this->find_face;
    }

//  //ADVANCED VERSION OF REALISATION
//    /**
//     * Recognize image with given service
//     * @param integer $service_id
//     * @param string  $img_base64
//     * @return \Atticlab\Libface\Response
//     * @throws \Atticlab\Libface\Exception
//     */
//    public function recognize($service_id, $img_base64)
//    {
//        $service_id = intval($service_id);
//        if (empty($this->services[$service_id])) {
//            throw new Exception(Exception::UNKNOWN_SERVICE);
//        }
//
//        // Validate image
//        $img = new Image($img_base64, $this->_logger);
//
//        // Recognize image
//        $service = $this->services[$service_id];
//
//        $request = $service->prepareRecognitionRequest($img->getImage());
//        $api_response = $this->executeHttpRequest($request);
//
//        $face_id = $service->processRecognitionHttpResponse($api_response);
//
//        return new Response($service_id, $face_id);
//    }
//
//    /**
//     * Get exist or register face id by all services
//     * @param string $img_base64
//     * @return array of \Atticlab\Libface\Response objects
//     * @throws \Atticlab\Libface\Exception
//     */
//    public function create($img_base64)
//    {
//        // Validate image
//        $img = new Image($img_base64, $this->_logger);
//
//        $responses = [];
//
//        foreach ($this->services as $service_id => $service) {
//            $request = $service->prepareCreateRequest($img->getImage());
//            $api_response = $this->executeHttpRequest($request);
//            $face_id = $service->processCreateHttpResponse($api_response);
//
//            $responses[$service_id] = new Response($service_id, $face_id);
//        }
//
//        return $responses;
//    }

    /**
     * Recognize image with given service
     * @param integer $service_id
     * @param string  $img_base64
     * @return \Atticlab\Libface\Response
     * @throws \Atticlab\Libface\Exception
     */
    public function recognize($service_id, $img_base64)
    {
        $this->ldebug('Start recognizing face id');
        $service_id = intval($service_id);
        if (empty($this->services[$service_id])) {
            $this->lerror('Service with id ' . $service_id . ' not configured');
            throw new Exception(Exception::UNKNOWN_SERVICE);
        }

        // Validate image
        $img = new Image($img_base64, $this->_logger);

        // Recognize image
        $service = $this->services[$service_id];
        $this->ldebug('Recognizing', ['service' => $service->getServiceName()]);
        $face_id = $service->getFaceID($img->getImage());

        return new Response($service_id, $face_id);
    }

    /**
     * Get exist or register face id by all services
     * @param string $img_base64
     * @return array of \Atticlab\Libface\Response objects
     * @throws \Atticlab\Libface\Exception
     */
    public function create($img_base64)
    {
        $this->ldebug('Start creating face id');
        // Validate image
        $img = new Image($img_base64, $this->_logger);

        $responses = [];

        foreach ($this->services as $service_id => $service) {
            $this->ldebug('Creating on service', ['service' => $service->getServiceName()]);
            $face_id = $service->createFaceID($img->getImage());
            $responses[$service_id] = new Response($service_id, $face_id);
        }

        return $responses;
    }

    /**
     * @param \GuzzleHttp\Psr7\Request $request
     * @return \GuzzleHttp\Psr7\Response
     * @throws \GuzzleHttp\Exception\RequestException
     * @throws \Exception
     */
    private function executeHttpRequest($request)
    {
        if (!($request instanceof \GuzzleHttp\Psr7\Request)) {
            $this->lerror('Trying to execute invalid request object');
            throw new Exception(Exception::INVALID_CONFIG);
        }

        $http = new HTTP();

        try {
            return $http->send($request, ['timeout' => self::TIMEOUT]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->lerror('Failed to executeHttpRequest request', ['message' => $e->getMessage()]);
            throw new Exception(Exception::TRANSPORT_ERROR);
        } catch (\Exception $e) {
            $this->lemergency('Unexpected exception',
                [get_class($e), $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine()]);
            throw new Exception(Exception::TRANSPORT_ERROR);
        }
    }


    /**
     * Async get exist or register face id by all services
     * @param string $img_base64
     * @return array of \Atticlab\Libface\Response objects
     * @throws \Atticlab\Libface\Exception
     */
    public function createAsync($img_base64)
    {
        $results = [];

        // Validate image
        $img = new Image($img_base64, $this->_logger);
        //$http = new HTTP(['http_errors' => false]);
        $http = new HTTP();
        $requests = [];

        foreach ($this->services as $service_id => $service) {
            //build multi curl guzzle request
            $request = $service->prepareRecognitionRequest($img->getImage());
            $requests[$service_id] = $http->sendAsync($request);
        }

        $responses = Promise\settle($requests)->wait();

        foreach ($this->services as $service_id => $service) {
            if (array_key_exists($service_id, $responses)) {
                $response = $responses[$service_id];

                if (!empty($response) && $response['state'] == 'fulfilled') {
                    $data = $response['value']->getBody()->getContents();
                    $results[$service_id] = $service->handleResponse($data);
                } else {
                    $this->lerror('Service error while try multi curl request', [
                        "service" => $service->getServiceName(),
                        "error"   => $response
                    ]);
                }
            }
        }

        return $results;
    }

    /**
     * Check availability of services
     * @return array
     */
    public function checkServicesAvailability()
    {
        $results = [];

        foreach ($this->services as $service_id => $service) {
            $results[$service_id] = $service->checkServiceAvailability();
        }

        return $results;
    }

    /**
     * Return ids of enabled services
     * @return array
     */
    public function getServicesIDs()
    {
        $ids = [];
        foreach ($this->services as $id => $service) {
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * Return services
     * @return array
     */
    public function getServices()
    {
        return $this->services;
    }

    public function getServiceLimitById($service_id) {
        return $this->services[$service_id]->getLimit();
    }

    public function getServiceNameById($service_id) {
        return $this->services[$service_id]->getServiceName();
    }

    /**
     * @param integer $service_id
     * @return null
     */
    public function removeServiceById($service_id)
    {
        $this->ldebug('Remove service by id');
        $service_id = intval($service_id);

        if (empty($this->services[$service_id])) {
            $this->lnotice('Trying to remove not existed service with id ' . $service_id);
            return;
        }

        unset($this->services[$service_id]);
    }
}