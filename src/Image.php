<?php

namespace Atticlab\Libface;

use \Atticlab\Libface\Exception;

class Image
{
    use \Atticlab\Libface\Traits\Logger;

    /**
     * @var string base64 image
     */
    private $image;

    /**
     * @var string base64 optimized image
     */
    private $optimized;

    const OPTIMAL_WIDTH = 416;
    const OPTIMAL_HEIGHT = 416;

    /**
     * Image constructor.
     * @param string $base64
     * @param \Psr\Log\LoggerInterface|null $logger
     * @throws \Atticlab\Libface\Exception
     */
    public function __construct($base64, \Psr\Log\LoggerInterface $logger = null)
    {
        if ($logger instanceof \Psr\Log\LoggerInterface) {
            $this->setLogger($logger);
        }

        $base64 = trim($base64);
        $base64 = preg_replace('#data:image/[^;]+;base64,#', '', $base64);

        if (empty($base64)) {
            $this->lerror('Empty base64 string');
            throw new Exception(Exception::EMPTY_IMAGE_DATA);
        }

        $size = strlen($base64);
        $this->ldebug('Got image', ['base64size' => $size]);
        if ($size < 4) {
            throw new Exception(Exception::INVALID_IMAGE_ENCODING, 'Data length is too short');
        }

        // Check is cheaper for decoding
        $this->ldebug('Validating Base64');
        if (!preg_match('`^[a-zA-Z0-9+/]+={0,2}$`', $base64)) {
            $this->lerror('encoded image Invalid base64 chars', ['base64size' => $size]);
            throw new Exception(Exception::INVALID_IMAGE_ENCODING, 'Invalid base64 chars');
        }

        $this->image = $base64;
        $this->optimized = base64_encode($this->optimizeImage());
    }

    public function getImage()
    {
        return $this->optimized;
    }

    /**
     * @param $binary - image in binary
     * @return string
     * @throws \Atticlab\Libface\Exception
     */
    private function optimizeImage()
    {
        // try to decode image into binary
        $binary = @base64_decode($this->image);
        if (empty($binary)) {
            $this->lerror('Empty data after decoding');
            throw new Exception(Exception::INVALID_IMAGE_ENCODING, 'Nothing was decoded');
        }

        $length = strlen($binary);
        $this->ldebug('Decoded binary', ['binSize' => $length]);

        $image_data = getimagesizefromstring($binary);

        if (empty($image_data['mime']) || empty($image_data['0']) || empty($image_data['1'])) {
            throw new Exception(Exception::INVALID_IMAGE_ENCODING, 'Can not get image mime type');
        }

        if (empty($image_data['0'])) {
            throw new Exception(Exception::INVALID_IMAGE_ENCODING, 'Can not get image width');
        }

        if (empty($image_data['1'])) {
            throw new Exception(Exception::INVALID_IMAGE_ENCODING, 'Can not get image height');
        }

        $width = $image_data['0'];
        $height = $image_data['1'];

        $this->ldebug('Got image info', ['mime' => $image_data['mime'], 'width' => $width, 'height' => $height]);

        switch ($image_data['mime']) {
            case 'image/jpeg':
            case 'image/png':
            case 'image/jpg':
                break;
            default:
                $this->lerror('Unsupported image type', ['image_type' => $image_data['mime']]);
                throw new Exception(Exception::INVALID_IMAGE, 'Unsupported image type');
        }

        if ($width > self::OPTIMAL_WIDTH && $height > self::OPTIMAL_HEIGHT) {
            $this->ldebug('Resizing image');
            //need to resize image
            if ($width > $height) {
                $ratio = $width / $height;
                $resize_height = self::OPTIMAL_HEIGHT;
                $resize_width = round(self::OPTIMAL_HEIGHT * $ratio);
            } else {
                $ratio = $height / $width;
                $resize_width = self::OPTIMAL_WIDTH;
                $resize_height = round(self::OPTIMAL_WIDTH * $ratio);
            }

            $original_image = imagecreatefromstring($binary);
            $resize_image = imagecreatetruecolor($width, $height);

            imagecopyresampled($resize_image, $original_image, 0, 0, 0, 0, $resize_width, $resize_height, $width,
                $height);

            $path = tempnam(sys_get_temp_dir(), 'optimized');
            imagejpeg($resize_image, $path);
            $binary = file_get_contents($path);
            unlink($path);
        }

        return $binary;
    }
}