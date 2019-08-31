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
use Streaming\Exception\Exception;
use Streaming\Exception\InvalidArgumentException;
use Streaming\Exception\RuntimeException;
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
    protected $strict = "-2";

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

        try {
            $this->media->save(
                $this->getFormat(),
                $path
            );
        } catch (ExceptionInterface $e) {
            throw new RuntimeException(sprintf(
                "There was an error saving files: \n\n reason: \n %s",
                $e->getMessage()
            ),
                $e->getCode(),
                $e->getFile());
        }


        $response = ($analyse) ? (new StreamingAnalytics($this))->analyse() : $this;

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
            throw new InvalidArgumentException("You need to specify a path. It is not possible to save to a tmp directory");
        }

        $dirname = str_replace("\\", "/", $this->path_info["dirname"]);
        $filename = substr($this->path_info["filename"], -50);

        FileManager::makeDir($dirname);

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
        if ($this instanceof HLS && $this->getTsSubDirectory()) {
            throw new InvalidArgumentException("It is not possible to create subdirectory in a cloud");
        }
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
    public function saveToS3(
        array $config,
        string $dest,
        string $path = null,
        bool $analyse = true
    )
    {
        list($results, $tmp_dir) = $this->saveToTemporaryFolder($path, $analyse);
        sleep(1);

        $aws = new AWS($config);
        $aws->uploadAndDownloadDirectory($tmp_dir, $dest);

        $this->moveTmpFolder($path, $tmp_dir);

        return $results;
    }

    /**
     * @param array $config
     * @param string $bucket
     * @param string|null $path
     * @param array $options
     * @param bool $userProject
     * @param bool $analyse
     * @return mixed
     * @throws Exception
     */
    public function saveToGCS(
        array $config,
        string $bucket,
        string $path = null,
        array $options = [],
        $userProject = false,
        bool $analyse = true
    )
    {
        list($results, $tmp_dir) = $this->saveToTemporaryFolder($path, $analyse);
        sleep(1);

        $google_cloud = new GoogleCloudStorage($config, $bucket, $userProject);
        $google_cloud->uploadDirectory($tmp_dir, $options);

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
    public function getMedia(): Media
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
            $basename = pathinfo($path, PATHINFO_BASENAME);
        }

        $tmp_dir = FileManager::tmpDir();
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
            FileManager::moveDir($tmp_dir, pathinfo($path, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR);
        } else {
            FileManager::deleteDirectory($tmp_dir);
        }
    }

    /**
     * @param string $strict
     * @return Export
     */
    public function setStrict(string $strict): Export
    {
        $this->strict = $strict;
        return $this;
    }

    /**
     * @return string
     */
    public function getStrict(): string
    {
        return $this->strict;
    }
}