<?php

/**
 * This file is part of the PHP-FFmpeg-video-streaming package.
 *
 * (c) Amin Yazdanpanah <contact@aminyazdanpanah.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Streaming;

use FFMpeg\Exception\ExceptionInterface;
use FFMpeg\FFMpeg as BFFMpeg;
use FFMpeg\FFProbe;
use Psr\Log\LoggerInterface;
use Streaming\Exception\Exception;
use Streaming\Exception\InvalidArgumentException;
use Streaming\Exception\RuntimeException;

class FFMpeg
{
    /** @var BFFMpeg */
    protected $ffmpeg;

    /**
     * @param $ffmpeg
     */
    public function __construct(BFFMpeg $ffmpeg)
    {
        $this->ffmpeg = $ffmpeg;
    }

    /**
     * @param string $path
     * @param bool $is_tmp
     * @return Media
     */
    public function open(string $path, bool $is_tmp = false): Media
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("There is no file in this path: " . $path);
        }

        try {
            return new Media($this->ffmpeg->open($path), $path, $is_tmp);
        } catch (ExceptionInterface $e) {
            throw new RuntimeException(sprintf("There was an error opening this file: \n\n reason: \n %s", $e->getMessage()), $e->getCode(), $e);
        }
    }

    /**
     * @param string $url
     * @param string|null $save_to
     * @param string $method
     * @param $request_options
     * @return Media
     * @throws Exception
     */
    public function fromURL(string $url, string $save_to = null, string $method = "GET", array $request_options = []): Media
    {
        Helper::isURL($url);
        list($is_tmp, $save_to) = $this->isTmp($save_to);

        $file_manager = new FileManager($url, $method, $request_options);
        $file_manager->downloadFile($save_to);

        return $this->open($save_to, $is_tmp);
    }

    /**
     * @param array $config
     * @param string $bucket
     * @param string $key
     * @param string|null $save_to
     * @return Media
     * @throws Exception
     */
    public function fromS3(array $config, string $bucket, string $key, string $save_to = null): Media
    {
        list($is_tmp, $save_to) = $this->isTmp($save_to);

        $aws = new AWS($config);
        $aws->downloadFile($bucket, $key, $save_to);

        return $this->open($save_to, $is_tmp);
    }

    /**
     * @param array $config
     * @param string $bucket
     * @param string $name
     * @param string|null $save_to
     * @param bool $userProject
     * @return Media
     * @throws Exception
     */
    public function fromGCS(array $config, string $bucket, string $name, string $save_to = null, $userProject = false): Media
    {
        list($is_tmp, $save_to) = $this->isTmp($save_to);

        $google_cloud = new GoogleCloudStorage($config, $bucket, $userProject);
        $google_cloud->download($name, $save_to);

        return $this->open($save_to, $is_tmp);
    }

    /**
     * @param $path
     * @return array
     * @throws Exception
     */
    private function isTmp($path)
    {
        $is_tmp = false;

        if (null === $path) {
            $is_tmp = true;
            $path = FileManager::tmpFile();
        }

        return [$is_tmp, $path];
    }

    /**
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->ffmpeg, $method], $parameters);
    }

    /**
     * @param array $config
     * @param LoggerInterface $logger
     * @param FFProbe|null $probe
     * @return FFMpeg
     */
    public static function create($config = array(), LoggerInterface $logger = null, FFProbe $probe = null)
    {
        return new static(BFFMpeg::create($config, $logger, $probe));
    }
}