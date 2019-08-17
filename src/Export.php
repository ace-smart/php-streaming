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

use Streaming\Exception\Exception;
use Streaming\Filters\Filter;
use Streaming\Traits\Formats;

abstract class Export
{
    use Formats;

    /** @var object */
    protected $media;

    /** @var Filter */
    protected $filter;

    /** @var array */
    protected $path_info;

    /** @var string */
    protected $mediaInfoBinary = 'mediainfo';

    /**
     * Export constructor.
     * @param Media $media
     */
    public function __construct(Media $media)
    {
        $this->media = $media;
        $this->path_info = pathinfo($media->getPath());
    }

    /**
     * @param string $path
     * @param bool $analyse
     * @return mixed
     * @throws Exception
     */
    public function save(string $path = null, $analyse = true)
    {
        $path = $this->getPath($path);

        $this->setFilter();

        $this->media->addFilter(
            $this->getFilter()
        );

        $this->media->save(
            $this->getFormat(),
            $path
        );

        $response = ($analyse) ? (new StreamingAnalytics($this, $this->mediaInfoBinary))->analyse() : $this;

        if ($this->media->isTmp()) {
            $this->deleteOriginalFile();
        }

        return $response;
    }

    /**
     * @return Filter
     */
    abstract protected function getFilter(): Filter;

    /**
     * @return mixed
     */
    abstract protected function setFilter();

    /**
     * @param $path
     * @return string
     * @throws Exception
     */
    private function getPath($path): string
    {
        if (null !== $path) {
            $this->path_info = pathinfo($path);
        }

        if (null === $path && $this->media->isTmp()) {
            $this->deleteOriginalFile();
            throw new Exception("You need to specify a path. It is not possible to save to the tmp directory");
        }

        $dirname = str_replace("\\", "/", $this->path_info["dirname"]);
        $filename = substr($this->path_info["filename"], -50);

        Helper::makeDir($dirname);

        if ($this instanceof DASH) {
            $path = $dirname . "/" . $filename . ".mpd";
        } elseif ($this instanceof HLS) {
            $representations = $this->getRepresentations();
            $path = $dirname . "/" . $filename . "_" . end($representations)->getHeight() . "p.m3u8";
            ExportHLSPlaylist::savePlayList($dirname . DIRECTORY_SEPARATOR . $filename . ".m3u8", $this->getRepresentations(), $filename);
        }

        return $path;
    }

    /**
     * @param string $url
     * @param string $name
     * @param string|null $path
     * @param string $method
     * @param array $headers
     * @param array $options
     * @param bool $analyse
     * @return mixed
     * @throws Exception
     */
    public function saveToCloud(
        string $url,
        string $name,
        string $path = null,
        string $method = 'GET',
        array $headers = [],
        array $options = [],
        bool $analyse = true
    )
    {
        list($results, $tmp_dir) = $this->saveToTemporaryFolder($path, $analyse);
        sleep(1);

        $file_manager = new FileManager($url, $method, $options);
        $file_manager->uploadDirectory($tmp_dir, $name, $headers);

        $this->moveTmpFolder($path, $tmp_dir);

        return $results;
    }

    /**
     * @param array $config
     * @param string $dest
     * @param string|null $path
     * @param bool $analyse
     * @return mixed
     * @throws Exception
     */
    public function saveToS3(array $config, string $dest, string $path = null, bool $analyse =true)
    {
        list($results, $tmp_dir) = $this->saveToTemporaryFolder($path, $analyse);
        sleep(1);

        $aws = new AWS($config);
        $aws->uploadAndDownloadDirectory($tmp_dir, $dest);

        $this->moveTmpFolder($path, $tmp_dir);

        return $results;
    }

    /**
     * @return array
     */
    public function getPathInfo(): array
    {
        return $this->path_info;
    }

    /**
     * @return object|Media
     */
    public function getMedia()
    {
        return $this->media;
    }

    private function deleteOriginalFile()
    {
        sleep(1);
        @unlink($this->media->getPath());
    }

    /**
     * @param $path
     * @param $analyse
     * @return array
     * @throws Exception
     */
    private function saveToTemporaryFolder($path, $analyse)
    {
        $basename = Helper::randomString();

        if (null !== $path) {
            $basename = pathinfo($path)["basename"];
        }

        $tmp_dir = Helper::tmpDir();
        $tmp_file = $tmp_dir . $basename;

        return [$this->save($tmp_file, $analyse), $tmp_dir];
    }

    /**
     * @param string|null $path
     * @param $tmp_dir
     * @throws Exception
     */
    private function moveTmpFolder(?string $path, $tmp_dir)
    {
        if (null !== $path) {
            $destination = pathinfo($path)["dirname"] . DIRECTORY_SEPARATOR;
            Helper::makeDir($destination);
            Helper::moveDir($tmp_dir, $destination);
        } else {
            Helper::deleteDirectory($tmp_dir);
        }
    }

    /**
     * @param string $mediaInfoBinary
     * @return Export
     */
    public function setMediaInfoBinary(string $mediaInfoBinary)
    {
        $this->mediaInfoBinary = $mediaInfoBinary;
        return $this;
    }
}