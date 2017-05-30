<?php

namespace Atticlab\Libface\Recognition;

use Atticlab\Libface\Exception;
use Atticlab\Libface\Interfaces\Recognition;
use Atticlab\Libface\Response;
use GuzzleHttp\Client as HTTP;
use GuzzleHttp\Psr7\Request;

/**
 * Class Kairos
 * @package App\Lib\Face\Recognition
 */
class Kairos implements Recognition
{
    use \Atticlab\Libface\Traits\Logger;

    /**
     * @var \Atticlab\Libface\Configs\Kairos
     */
    private $config;

    /**
     * Api id should be unique
     */
    const ID = 2;
    const TIMEOUT = 10;
    /**
     * Kairos constructor.
     * @param \Atticlab\Libface\Configs\Kairos $config
     * @param \Psr\Log\LoggerInterface|null    $logger
     * @throws \Atticlab\Libface\Exception
     */
    public function __construct(\Atticlab\Libface\Configs\Kairos $config, \Psr\Log\LoggerInterface $logger = null)
    {
        if ($logger instanceof \Psr\Log\LoggerInterface) {
            $this->setLogger($logger);
        }

        $this->ldebug('Enabling service',
            [$this->getServiceName(), $config->application_id, $config->application_key, $config->gallery_name]);

        try {
            $config->validate();
        } catch (\Exception $e) {
            $this->lerror('Failed to validate config',
                ['service' => $this->getServiceName(), 'message' => $e->getMessage()]);
            throw new Exception(Exception::INVALID_CONFIG);
        }

        $this->config = $config;
    }

    /**
     * Return service id
     * @return int
     */
    public function getServiceID()
    {
        return self::ID;
    }

    /**
     * Generate human readable service name
     * @return string
     */
    public function getServiceName()
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    public function getFaceID($image_base64)
    {
        $request = $this->prepareRecognitionRequest($image_base64);

        if (!($request instanceof \GuzzleHttp\Psr7\Request)) {
            $this->lerror('Trying to execute invalid request object');
            throw new Exception(Exception::INVALID_CONFIG);
        }

        $http = new HTTP();

        try {
            $response = $http->send($request, ['timeout' => self::TIMEOUT]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->lerror('Failed to executeHttpRequest request', ['message' => $e->getMessage()]);
            throw new Exception(Exception::TRANSPORT_ERROR);
        } catch (\Exception $e) {
            $this->lemergency('Unexpected exception',
                [get_class($e), $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine()]);
            throw new Exception(Exception::TRANSPORT_ERROR);
        }

        return $this->processRecognitionHttpResponse($response);
    }

    public function createFaceID($image_base64)
    {
        //check existing face id at first
        $request = $this->prepareRecognitionRequest($image_base64);

        if (!($request instanceof \GuzzleHttp\Psr7\Request)) {
            $this->lerror('Trying to execute invalid request object');
            throw new Exception(Exception::INVALID_CONFIG);
        }

        $http = new HTTP();

        try {
            $response = $http->send($request, ['timeout' => self::TIMEOUT]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->lerror('Failed to executeHttpRequest request', ['message' => $e->getMessage()]);
            throw new Exception(Exception::TRANSPORT_ERROR);
        } catch (\Exception $e) {
            $this->lemergency('Unexpected exception',
                [get_class($e), $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine()]);
            throw new Exception(Exception::TRANSPORT_ERROR);
        }

        $face_id = $this->processRecognitionHttpResponse($response);

        if (empty($face_id)) {
            //create if not exist
            $request = $this->prepareCreateRequest($image_base64);

            if (!($request instanceof \GuzzleHttp\Psr7\Request)) {
                $this->lerror('Trying to execute invalid request object');
                throw new Exception(Exception::INVALID_CONFIG);
            }

            $http = new HTTP();

            try {
                $response = $http->send($request, ['timeout' => self::TIMEOUT]);
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $this->lerror('Failed to executeHttpRequest request', ['message' => $e->getMessage()]);
                throw new Exception(Exception::TRANSPORT_ERROR);
            } catch (\Exception $e) {
                $this->lemergency('Unexpected exception',
                    [get_class($e), $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine()]);
                throw new Exception(Exception::TRANSPORT_ERROR);
            }

            $face_id = $this->processCreateHttpResponse($response);
        }

        return $face_id;
    }

    /**
     * Check if service is available
     * @return bool
     */
    public function checkServiceAvailability()
    {
        $request = new Request('GET', \Atticlab\Libface\Configs\Kairos::STATUS_URL, []);

        $is_available = false;

        try {
            $http = new HTTP(['http_errors' => false]);
            $response = $http->send($request);
            //api must return 403 code for not auth requests
            $is_available = $response->getStatusCode() == 403;
        } catch (\Exception $e) {
            $this->lerror('Error while try to check service availability status', [
                'service' => $this->getServiceName(),
                get_class($e),
                $e->getCode(),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ]);
        }

        return $is_available;
    }

    /**
     * Build params for guzzle request on recognition existed face id
     * @param $image_base64 string
     * @return \GuzzleHttp\Psr7\Request
     */
    public function prepareRecognitionRequest($image_base64)
    {
        $url = rtrim(\Atticlab\Libface\Configs\Kairos::HOST_NAME, '/') . '/recognize';

        $headers = [
            'Content-Type' => 'application/json',
            'app_id'       => $this->config->application_id,
            'app_key'      => $this->config->application_key
        ];

        $body = json_encode([
            "image"        => $image_base64,
            "gallery_name" => $this->config->gallery_name,
        ]);

        return new Request('POST', $url, $headers, $body);
    }

    // @TODO: Where is link for documentation?
    /**
     * Handle response of service.
     * @param \GuzzleHttp\Psr7\Response $response
     * @return \Atticlab\Libface\Response
     * @throws \Atticlab\Libface\Exception
     */
    public function processRecognitionHttpResponse(\GuzzleHttp\Psr7\Response $response)
    {
        $data = $response->getBody()->getContents();

        if (empty($data)) {
            $this->lerror('Empty response', ['service' => $this->getServiceName()]);
            throw new Exception(Exception::BAD_SERVICE_RESPONSE);
        }

        $data = json_decode($data, true);

        if (json_last_error() != JSON_ERROR_NONE) {
            $this->lerror('Response is not json string', ['service' => $this->getServiceName()]);
            throw new Exception(Exception::BAD_SERVICE_RESPONSE);
        }

        //will throw Exception or nothing
        $this->checkResponseErrors($data);

        //check existing of face id
        $existed_face_id = null;

        if (empty($data['images'])) {
            $this->lerror('Unexpected empty images response from Kairos', [$data]);
            throw new Exception(Exception::BAD_SERVICE_RESPONSE);
        }

        if (sizeof($data['images']) > 1) {
            throw new Exception(Exception::MANY_FACES_FOUND);
        }

        if (!empty($data['images'][0]["transaction"]['subject_id'])) {
            $existed_face_id = $data['images'][0]["transaction"]['subject_id'];
        }

        return $existed_face_id;
    }

    /**
     * Build params for guzzle request on create new face id
     * @param $image_base64 string
     * @return \GuzzleHttp\Psr7\Request
     * @see https://www.kairos.com/docs/api/#post-enroll
     */
    public function prepareCreateRequest($image_base64)
    {
        //generate new face id
        $face_id = sha1(microtime() . uniqid(__CLASS__, true));

        $url = rtrim(\Atticlab\Libface\Configs\Kairos::HOST_NAME, '/') . '/enroll';

        $request_params = [
            "image"        => $image_base64,
            "gallery_name" => $this->config->gallery_name,
            "subject_id"   => $face_id,
        ];

        $request = json_encode($request_params);

        $headers = [
            'Content-Type'   => 'application/json',
            'Content-Length' => strlen($request),
            'app_id'         => $this->config->application_id,
            'app_key'        => $this->config->application_key
        ];

        $body = $request;

        return new Request('POST', $url, $headers, $body);
    }

    /**
     * Handle response of service.
     * @param \GuzzleHttp\Psr7\Response $response
     * @return \Atticlab\Libface\Response
     * @throws \Atticlab\Libface\Exception
     */
    public function processCreateHttpResponse(\GuzzleHttp\Psr7\Response $response)
    {
        $data = $response->getBody()->getContents();

        if (empty($data)) {
            $this->lerror('Empty response', ['service' => $this->getServiceName()]);
            throw new Exception(Exception::BAD_SERVICE_RESPONSE);
        }

        $data = json_decode($data, true);

        if (json_last_error() != JSON_ERROR_NONE) {
            $this->lerror('Response is not json string', ['service' => $this->getServiceName()]);
            throw new Exception(Exception::BAD_SERVICE_RESPONSE);
        }

        //will throw Exception or nothing
        $this->checkResponseErrors($data);

        //check existing of face id
        $created_face_id = null;

        if (empty($data['images'])) {
            $this->lerror('Unexpected empty images response from Kairos', [$data]);
            throw new Exception(Exception::BAD_SERVICE_RESPONSE);
        }

        if (sizeof($data['images']) > 1) {
            throw new Exception(Exception::MANY_FACES_FOUND);
        }

        if (!empty($data['images'][0]["transaction"]['subject_id'])) {
            $created_face_id = $data['images'][0]["transaction"]['subject_id'];
        }

        return $created_face_id;
    }

    /**
     * @param array $data - encoded response from service
     * @throws Exception
     */
    private function checkResponseErrors(array $data) {
        //check errors
        if (isset($data["Errors"][0]['ErrCode'])) {
            switch ($data["Errors"][0]['ErrCode']) {
                case 3003:
                    ###########################################
                    # Invalid authentication parameters
                    # The app_id or app_key were not valid.
                    $this->lerror('Error in Kairos response. Invalid app key or app id for Kairos', [$data]);
                    throw new Exception(Exception::INVALID_CONFIG);
                    break;
                case 5000:
                    ###########################################
                    # An invalid image was sent must be jpg or png format
                    # We only accept images in JPG and PNG format currently.
                    $this->lerror('Error in Kairos response. Unsupported type of image', [$data]);
                    throw new Exception(Exception::INVALID_IMAGE);
                    break;
                case 5002:
                    ###########################################
                    # If the picture does not have Faces return Error
                    $this->lerror('Error in Kairos response. No faces was found', [$data]);
                    throw new Exception(Exception::NO_FACES_FOUND);
                    break;
                case 5004:
                    ###########################################
                    # If gallery not found return Error
                    $this->lerror('Error in Kairos response. Gallery was not found', [$data]);
                    throw new Exception(Exception::INVALID_CONFIG);
                    break;
                case 5010:
                    ###########################################
                    # If the picture hav many faces return Error
                    $this->lerror('Error in Kairos response. Many faces was found', [$data]);
                    throw new Exception(Exception::MANY_FACES_FOUND);
                    break;
                default:
                    $this->lerror('Unexpected error response from Kairos', [$data]);
                    throw new Exception(Exception::BAD_SERVICE_RESPONSE);
            }
        }
    }
}
