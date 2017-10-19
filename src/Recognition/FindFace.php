<?php

namespace Atticlab\Libface\Recognition;

use Atticlab\Libface\Exception;
use Atticlab\Libface\Interfaces\Recognition;
use Atticlab\Libface\Response;
use GuzzleHttp\Client as HTTP;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\MultipartStream;

/**
 * Class FindFace
 * @package App\Lib\Face\Recognition
 */
class FindFace extends RecognitionBase implements Recognition
{
    use \Atticlab\Libface\Traits\Logger;

    /**
     * Api id should be unique
     */
    const ID = 4;
    const TIMEOUT = 30;

    /**
     * MIN accepted similarity from [0 to 1]
     */
    const MIN_SIMILARITY = 0.93;

    /**
     * FindFace constructor.
     * @param \Atticlab\Libface\Configs\FindFace $config
     * @param \Psr\Log\LoggerInterface|null    $logger
     * @throws \Atticlab\Libface\Exception
     */
    public function __construct(\Atticlab\Libface\Configs\FindFace $config, \Psr\Log\LoggerInterface $logger = null)
    {
        if ($logger instanceof \Psr\Log\LoggerInterface) {
            $this->setLogger($logger);
        }

        $this->ldebug('Enabling service',
            [$this->getServiceName(), $config->token, $config->gallery_name]);

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

    public function getFaceID($image_base64, $gallery = null)
    {
        $request = $this->prepareRecognitionRequest($image_base64, $gallery);

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

    public function createFaceID($image_base64, $gallery = null)
    {
        //check existing face id at first
        $request = $this->prepareRecognitionRequest($image_base64,$gallery);

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
            $request = $this->prepareCreateRequest($image_base64,$gallery);

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
        $request = new Request('GET', \Atticlab\Libface\Configs\FindFace::STATUS_URL, []);

        $is_available = false;

        try {
            $http = new HTTP(['http_errors' => false]);
            $response = $http->send($request);
            //api must return 401 code for not auth requests
            $is_available = $response->getStatusCode() == 401;
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
    public function prepareRecognitionRequest($image_base64, $gallery = null)
    {
        if (empty($gallery)) {
            $gallery = $this->config->gallery_name;
        }
        $this->ensureGalleryExists($gallery);
        $url = rtrim(\Atticlab\Libface\Configs\FindFace::HOST_NAME, '/') . '/faces/gallery/' . $gallery . '/identify/';

        // try to decode image into binary
        $binary = @base64_decode($image_base64);
        if (empty($binary)) {
            $this->lerror('Empty image data after decoding');
            throw new Exception(Exception::EMPTY_IMAGE_DATA);
        }

        $image = imagecreatefromstring($binary);

        $path = tempnam(sys_get_temp_dir(), 'tmp');
        imagejpeg($image, $path);

        $multipart = new MultipartStream([
            [
                'name' => 'photo',
                'contents' => fopen($path, 'r')
            ],
        ]);

        unlink($path);

        $headers = [
            'Content-Type'  => 'multipart/form-data; boundary=' . $multipart->getBoundary(),
            'Authorization' => 'Token ' . $this->config->token
        ];

        return new Request('POST', $url, $headers, $multipart);
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

        if ($response->getStatusCode() != 200) {
            $this->checkResponseErrors($data);
        }

        //check existing of face id
        $existed_face_id = null;

        if (empty($data['results'])) {
            $this->lerror('Unexpected response from FindFace [data->results]', [$data]);
            throw new Exception(Exception::BAD_SERVICE_RESPONSE);
        }

        if (sizeof($data['results']) > 1) {
            throw new Exception(Exception::MANY_FACES_FOUND);
        }

        $data['results'] = array_values($data['results']);

        if (!empty($data['results'][0][0]['confidence']) && $data['results'][0][0]['confidence'] >= self::MIN_SIMILARITY) {
            $existed_face_id = $data['results'][0][0]['face']['id'];
        }

        return $existed_face_id;
    }

    /**
     * Build params for guzzle request on create new face id
     * @param $image_base64 string
     * @return \GuzzleHttp\Psr7\Request
     * @see https://www.FindFace.com/docs/api/#post-enroll
     */
    public function prepareCreateRequest($image_base64, $gallery = null)
    {
        if (empty($gallery)) {
            $gallery = $this->config->gallery_name;
        }
        $this->ensureGalleryExists($gallery);

        $url = rtrim(\Atticlab\Libface\Configs\FindFace::HOST_NAME, '/') . '/face/?galleries=' . $gallery;

        // try to decode image into binary
        $binary = @base64_decode($image_base64);
        if (empty($binary)) {
            $this->lerror('Empty image data after decoding');
            throw new Exception(Exception::EMPTY_IMAGE_DATA);
        }

        $image = imagecreatefromstring($binary);

        $path = tempnam(sys_get_temp_dir(), 'tmp');
        imagejpeg($image, $path);

        $multipart = new MultipartStream([
            [
                'name' => 'photo',
                'contents' => fopen($path, 'r')
            ],
        ]);

        unlink($path);

        $headers = [
            'Content-Type'  => 'multipart/form-data; boundary=' . $multipart->getBoundary(),
            'Authorization' => 'Token ' . $this->config->token
        ];

        return new Request('POST', $url, $headers, $multipart);
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

        if ($response->getStatusCode() != 200) {
            //will throw Exception or nothing
            $this->checkResponseErrors($data);
        }

        //check existing of face id
        $created_face_id = null;

        if (empty($data['results'])) {
            $this->lerror('Unexpected response from FindFace [data->results]', [$data]);
            throw new Exception(Exception::BAD_SERVICE_RESPONSE);
        }

        if (sizeof($data['results']) > 1) {
            throw new Exception(Exception::MANY_FACES_FOUND);
        }

        $data['results'] = array_values($data['results']);

        if (empty($data['results'][0]['id'])) {
            $this->lerror('Unexpected response from FindFace [data->results[0]]', [$data]);
            throw new Exception(Exception::BAD_SERVICE_RESPONSE);
        }

        $created_face_id = $data['results'][0]['id'];

        return $created_face_id;
    }

    /**
     * @param array $data - encoded response from service
     * @throws Exception
     */
    private function checkResponseErrors(array $data) {
        //check errors
        if (isset($data["code"])) {
            switch ($data["code"]) {
                case "AUTH_FAILED":
                    ###########################################
                    # Invalid authentication parameters
                    # The app_id or app_key were not valid.
                    $this->lerror('Error in FindFace response. Invalid token for FindFace', [$data]);
                    throw new Exception(Exception::INVALID_CONFIG);
                    break;
                case "BAD_IMAGE":
                    ###########################################
                    # An invalid image was sent must be jpg or png format
                    # We only accept images in JPG and PNG format currently.
                    $this->lerror('Error in FindFace response. Unsupported type of image', [$data]);
                    throw new Exception(Exception::INVALID_IMAGE);
                    break;
                case "NO_FACES":
                    ###########################################
                    # If the picture does not have Faces return Error
                    $this->lerror('Error in FindFace response. No faces was found', [$data]);
                    throw new Exception(Exception::NO_FACES_FOUND);
                    break;
                case "BAD_PARAM":
                    if (isset($data["code"])) {
                        switch ($data["code"]) {
                            case "galleries":
                                ###########################################
                                # Invalid authentication parameters
                                # The app_id or app_key were not valid.
                                $this->lerror('Error in FindFace response. Invalid gallery for FindFace', [$data]);
                                throw new Exception(Exception::INVALID_CONFIG);
                                break;
                            case "photo":
                                ###########################################
                                # Invalid authentication parameters
                                # The app_id or app_key were not valid.
                                $this->lerror('Error in FindFace response. Invalid photo for FindFace', [$data]);
                                throw new Exception(Exception::INVALID_IMAGE);
                                break;
                        }
                    }
                default:
            }
        }

        $this->lerror('Unexpected error response from FindFace', [$data]);
        throw new Exception(Exception::BAD_SERVICE_RESPONSE);
    }

    public function ensureGalleryExists($gallery) {
        if (empty($gallery)) {
            return;
        }
        $url = rtrim(\Atticlab\Libface\Configs\FindFace::HOST_NAME, '/') . '/galleries/' . $gallery;
        $headers = [
            'Authorization' => 'Token ' . $this->config->token
        ];

        $request = new Request('POST', $url, $headers);

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

    }
}
