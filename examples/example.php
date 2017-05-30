<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require_once('../vendor/autoload.php');

$logger = new Logger('frec-lib');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// Configure Library
try {
    $recognition = new \Atticlab\Libface\Recognition($logger);
    $recognition->enableKairos('8053a393', 'f0385fae65661043c9ac66d1df3b2804', 'users');
} catch (\Atticlab\Libface\Exception $e) {
    $code = $e->getCode();
    $message = $e->getMessage();
    switch ($code) {
        case \Atticlab\Libface\Exception::INVALID_CONFIG:
            $logger->error('Configuration error', [$code, $message]);
            break;

        default:
            $logger->emerg('Unknown Library error', [$code, $message]);
            break;
    }
} catch (\Exception $e) {
    $logger->emergency('Unexpected exception', [get_class($e), $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine()]);
}

// Recognize image
$img_valid = file_get_contents('./images/good_img.txt');
$img_no_face = file_get_contents('./images/no_face.txt');
$img_many_faces = file_get_contents('./images/many_faces.txt');
$img_null = null;

try {
    $result = $recognition->checkServicesAvailability(); // Test valid image
    var_dump($result);

//    $result = $recognition->recognize(\Atticlab\Libface\Recognition\Kairos::ID, $img_null); // no image
//    var_dump($result);

//    $result = $recognition->create($img_null);
//    var_dump($result);

//    $result = $recognition->recognize(\Atticlab\Libface\Recognition\Kairos::ID, $img_many_faces); // many faces
//    var_dump($result);

//    $result = $recognition->create($img_many_faces);
//    var_dump($result);

//    $result = $recognition->recognize(\Atticlab\Libface\Recognition\Kairos::ID, $img_no_face); // no faces
//    var_dump($result);

//    $result = $recognition->create($img_no_face);
//    var_dump($result);

    $result = $recognition->recognize(\Atticlab\Libface\Recognition\Kairos::ID, $img_valid); // valid image
    var_dump($result);

    $result = $recognition->create($img_valid);
    var_dump($result);

} catch (\Atticlab\Libface\Exception $e) {
    $code = $e->getCode();
    $message = $e->getMessage();
    switch ($code) {
        case \Atticlab\Libface\Exception::EMPTY_IMAGE_DATA:
            $logger->error('No image is provided. Send image encoded with base64', [$code, $message]);
            break;

        case \Atticlab\Libface\Exception::INVALID_IMAGE_ENCODING:
            $logger->error('Invalid image encoding. Send image encoded with base64', [$code, $message]);
            break;

        case \Atticlab\Libface\Exception::INVALID_IMAGE:
            $logger->error('Invalid image type. JPG or PNG supported only', [$code, $message]);
            break;

        case \Atticlab\Libface\Exception::UNKNOWN_SERVICE:
            $logger->error('Invalid service id', [$code, $message]);
            break;

        case \Atticlab\Libface\Exception::MANY_FACES_FOUND:
            $logger->error('Two or more faces found on the image', [$code, $message]);
            break;

        case \Atticlab\Libface\Exception::NO_FACES_FOUND:
            $logger->error('No faces found on the image', [$code, $message]);
            break;

        case \Atticlab\Libface\Exception::BAD_SERVICE_RESPONSE:
            $logger->error('Unexpected response from service', [$code, $message]);
            break;

        case \Atticlab\Libface\Exception::TRANSPORT_ERROR:
            $logger->error('Error while send http request', [$code, $message]);
            break;

        default:
            $logger->emerg('Unknown Recognition error', [$code, $message]);
            break;
    }
}