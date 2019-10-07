<?php

/**
 * This file is part of the PHP-FFmpeg-video-streaming package.
 *
 * (c) Amin Yazdanpanah <contact@aminyazdanpanah.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\FFMpegStreaming;

use Streaming\FileManager;

class FileManagerTest extends TestCase
{
    public function testMakeDir()
    {
        $path = $this->srcDir . DIRECTORY_SEPARATOR . "test_make_dir";
        FileManager::makeDir($path);

        $this->assertDirectoryExists($path);
    }

    public function testTmp()
    {
        $tmp_file = FileManager::tmpFile();
        $tmp_dir = FileManager::tmpDir();

        $this->assertIsString($tmp_file);
        $this->assertIsString($tmp_dir);
    }
}
