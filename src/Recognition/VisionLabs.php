<?php

namespace Atticlab\Libface\Recognition;

use Atticlab\Libface\Exception;
use Atticlab\Libface\Interfaces\Recognition;
use GuzzleHttp\Client as HTTP;
use GuzzleHttp\Psr7\Request;

/**
 * Class Kairos
 * @package App\Lib\Face\Recognition
 */
class VisionLabs extends RecognitionBase implements Recognition
{
    use \Atticlab\Libface\Traits\Logger;

    /**
     * @var \Atticlab\Libface\Configs\VisionLabs
     */
    private $config;
    private $person_id;

    /**
     * Api id should be unique
     */
    const ID = 3;
    const TIMEOUT = 10;

    /**
     * MIN accepted similarity from [0 to 1]
     */
    const MIN_SIMILARITY = 0.500;

    /**
     * VisionLab constructor.
     * @param \Atticlab\Libface\Configs\VisionLabs $config
     * @param \Psr\Log\LoggerInterface|null    $logger
     * @throws \Atticlab\Libface\Exception
     */
    public function __construct(\Atticlab\Libface\Configs\VisionLabs $config, \Psr\Log\LoggerInterface $logger = null)
    {
        if ($logger instanceof \Psr\Log\LoggerInterface) {
            $this->setLogger($logger);
        }

        $this->ldebug('Enabling service',
            [$this->getServiceName(), $config->token, $config->descriptor_lists, $config->person_lists]);

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

    public function getFaceID($image_base64)
    {
        $request = $this->prepareRecognitionRequest($image_base64);

        if (!($request instanceof \GuzzleHttp\Psr7\Request)) {
            $this->lerror('Trying to execute invalid request object');
            throw new Exception(Exception::INVALID_CONFIG);
        }

        $http = new HTTP(['http_errors' => false]);

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

        $http = new HTTP(['http_errors' => false]);

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
        $request = new Request('GET', \Atticlab\Libface\Configs\VisionLabs::STATUS_URL, []);

        $is_available = false;

        try {
            $http = new HTTP(['http_errors' => false]);
            $response = $http->send($request);

            $is_available = $response->getStatusCode() == 200;
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
        $url = rtrim(\Atticlab\Libface\Configs\VisionLabs::HOST_NAME, '/') . "/matching/search?list_id={$this->config->descriptor_lists}";
        $bin_image = base64_decode($image_base64);
        $f = finfo_open();
        $imgType = finfo_buffer($f, $bin_image, FILEINFO_MIME_TYPE);

        $headers = [
            'X-Auth-Token' => $this->config->token,
            'Content-type' => $imgType,
            'Content-length' => strlen($bin_image)
        ];

        $body = $bin_image;

        return new Request('POST', $url, $headers, $body);
    }

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
        $face_id = null;

        if (!empty($data['candidates'])) {

            if (count($data['candidates']) > 1) {
                usort($data['candidates'], function ($a, $b) {
                    return strcmp($b['similarity'], $a['similarity']);
                });
            }

            if ($data['candidates'][0]['similarity'] >= self::MIN_SIMILARITY) {
                $face_id = $data['candidates'][0]['person_id'];
            }

        }

        return $face_id;
    }

    /**
     * Build params for guzzle request on create new face id
     * @param string $image_base64
     * @return Request
     * @throws Exception
     */
    public function prepareCreateRequest($image_base64)
    {
        $url = rtrim(\Atticlab\Libface\Configs\VisionLabs::HOST_NAME, '/');
        $headers = null;
        $bin_image = base64_decode($image_base64);
        $f = finfo_open();
        $imgType = finfo_buffer($f, $bin_image, FILEINFO_MIME_TYPE);
        $body = $bin_image;

        $http = new HTTP(['http_errors' => false]);

        #####################################
        # Create descriptor

        $headers = [
            'X-Auth-Token' => $this->config->token,
            'Content-type' => $imgType,
            'Content-length' => strlen($bin_image),
        ];

        $requestDescriptor = new Request(
            'POST',
            $url . '/storage/descriptors',
            $headers,
            $body
        );

        try {
            $responseDescriptor = $http->send($requestDescriptor, ['timeout' => self::TIMEOUT]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->lerror('Failed to executeHttpRequest request', ['message' => $e->getMessage()]);
            throw new Exception(Exception::TRANSPORT_ERROR);
        } catch (\Exception $e) {
            $this->lemergency('Unexpected exception',
                [get_class($e), $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine()]);
            throw new Exception(Exception::TRANSPORT_ERROR);
        }

        $responseDescriptor = json_decode($responseDescriptor->getBody()->getContents(), true);


        if (!empty($responseDescriptor['faces'][0]['id'])) {
            $responseDescriptor = $responseDescriptor['faces'][0]['id'];
        } else {
            $responseDescriptor = null;
        }

        #####################################
        # Add descriptor to the list

        $headers = [
            'X-Auth-Token' => $this->config->token
        ];

        $requestAttach = new Request(
            'PATCH',
            $url . "/storage/descriptors/{$responseDescriptor}/linked_lists?list_id={$this->config->descriptor_lists}&do=attach",
            $headers
        );

        try {
            $responseAttach = $http->send($requestAttach, ['timeout' => self::TIMEOUT]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->lerror('Failed to executeHttpRequest request', ['message' => $e->getMessage()]);
            throw new Exception(Exception::TRANSPORT_ERROR);
        } catch (\Exception $e) {
            $this->lemergency('Unexpected exception',
                [get_class($e), $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine()]);
            throw new Exception(Exception::TRANSPORT_ERROR);
        }

        #####################################
        # Create new Person

        $requestCreatePerson  = new Request(
            'POST',
            $url . '/storage/persons',
            $headers
        );

        try {
            $responseCreatePerson = $http->send($requestCreatePerson, ['timeout' => self::TIMEOUT]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->lerror('Failed to executeHttpRequest request', ['message' => $e->getMessage()]);
            throw new Exception(Exception::TRANSPORT_ERROR);
        } catch (\Exception $e) {
            $this->lemergency('Unexpected exception',
                [get_class($e), $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine()]);
            throw new Exception(Exception::TRANSPORT_ERROR);
        }

        $responseCreatePerson = json_decode($responseCreatePerson->getBody()->getContents(), true);
        $this->person_id = $responseCreatePerson['person_id'];

        #####################################
        # Add descriptor in person

        $requestAttachDescriptor  = new Request(
            'PATCH',
            $url . "/storage/persons/{$responseCreatePerson['person_id']}/linked_descriptors?descriptor_id={$responseDescriptor}&do=attach",
            $headers
        );

        try {
           $responseAttachDescriptor =  $http->send($requestAttachDescriptor, ['timeout' => self::TIMEOUT]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->lerror('Failed to executeHttpRequest request', ['message' => $e->getMessage()]);
            throw new Exception(Exception::TRANSPORT_ERROR);
        } catch (\Exception $e) {
            $this->lemergency('Unexpected exception',
                [get_class($e), $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine()]);
            throw new Exception(Exception::TRANSPORT_ERROR);
        }

        #####################################
        # Add person to list
        $url = $url . "/storage/persons/{$responseCreatePerson['person_id']}/linked_lists?list_id={$this->config->person_lists}&do=attach";

        return new Request('PATCH', $url, $headers);
    }

    /**
     * Handle response of service.
     * @param \GuzzleHttp\Psr7\Response $response
     * @return \Atticlab\Libface\Response
     * @throws \Atticlab\Libface\Exception
     */
    public function processCreateHttpResponse(\GuzzleHttp\Psr7\Response $response)
    {
        //check existing of face id
        $created_face_id = $this->person_id;

        return $created_face_id;
    }

    /**
     * @param array $data - encoded response from service
     * @throws Exception
     */
    private function checkResponseErrors(array $data)
    {
        //check errors
        if (isset($data["error_code"])) {
            switch ($data["error_code"]) {
                case 11006:
                    ###########################################
                    # Error descriptor
                    $this->lerror('Error in VisionLab response. Error descriptor', [$data]);
                    throw new Exception(Exception::INVALID_CONFIG);
                    break;
                case 3002:
                    ###########################################
                    # Incorrect image size
                    $this->lerror('Error in VisionLab response. Incorrect image size', [$data]);
                    throw new Exception(Exception::INVALID_IMAGE);
                    break;
                case 10012:
                    ###########################################
                    # Token not found
                    $this->lerror('Error in VisionLab response. Token not found', [$data]);
                    throw new Exception(Exception::INVALID_CONFIG);
                    break;
                case 10003:
                case 10004:
                    ###########################################
                    # Person not found One or More
                    $this->lerror('Error in VisionLab response. Person not found One or More', [$data]);
                    $this->lemergency('We dont awaiting this kind of errors! Check config', [$data]);
                    throw new Exception(Exception::BAD_SERVICE_RESPONSE);
                    break;
                case 4003:
                    ###########################################
                    #  Picture does not have Faces
                    $this->lerror('Error in VisionLab response. Picture does not have Faces', [$data]);
                    throw new Exception(Exception::NO_FACES_FOUND);
                    break;
                case 12015:
                    ###########################################
                    # 2+ faces detect
                    $this->lerror('Error in VisionLab response. Many faces detect', [$data]);
                    throw new Exception(Exception::MANY_FACES_FOUND);
                    break;
                default:
                    $this->lerror('Unexpected error response from VisionLab', [$data]);
                    throw new Exception(Exception::BAD_SERVICE_RESPONSE);
            }
        }

    }
}
