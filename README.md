# 📼 PHP FFMPEG Video Streaming

[![Build Status](https://travis-ci.org/aminyazdanpanah/PHP-FFmpeg-video-streaming.svg?branch=master)](https://travis-ci.org/aminyazdanpanah/PHP-FFmpeg-video-streaming)
[![Build status](https://img.shields.io/appveyor/ci/aminyazdanpanah/PHP-FFmpeg-video-streaming/master.svg?style=flat&logo=appveyor)](https://ci.appveyor.com/project/aminyazdanpanah/php-ffmpeg-video-streaming)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/aminyazdanpanah/PHP-FFmpeg-video-streaming/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/aminyazdanpanah/PHP-FFmpeg-video-streaming/?branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/aminyazdanpanah/php-ffmpeg-video-streaming.svg?style=flat)](https://packagist.org/packages/aminyazdanpanah/php-ffmpeg-video-streaming)
[![Latest Version on Packagist](https://img.shields.io/packagist/vpre/aminyazdanpanah/PHP-FFmpeg-video-streaming?color=success)](https://packagist.org/packages/aminyazdanpanah/php-ffmpeg-video-streaming)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](https://github.com/aminyazdanpanah/PHP-FFmpeg-video-streaming/blob/master/LICENSE)

## Overview
This package provides integration with **[PHP-FFmpeg](https://github.com/PHP-FFMpeg/PHP-FFMpeg)** and packages well-known online streaming techniques such as DASH and HLS. You can also use DRM for HLS packaging.
- Before you get started, please read the FFMpeg Document found **[here](https://ffmpeg.org/ffmpeg-formats.html)**.
- **[Full Documentation](https://video.aminyazdanpanah.com/)** is available describing all features and components.
- For DRM and encryption(DASH and HLS), I **strongly recommend** to try **[Shaka PHP](https://github.com/aminyazdanpanah/shaka-php)**, which is a great tool for this use case.

**Contents**
- [Requirements](#requirements)
- [Installation](#installation)
- [Quickstart](#quickstart)
  - [Configuration](#configuration)
  - [Opening a File](#opening-a-file)
  - [DASH](#dash)
  - [HLS](#hls)
    - [Encrypted HLS](#encrypted-hls)
  - [Transcoding](#transcoding)
  - [Saving Files](#saving-files)
  - [Other Advanced Features](#other-advanced-features)
- [Several Open Source Players](#several-open-source-players)
- [Contributing and Reporting Bug](#contributing-and-reporting-bugs)
- [Credits](#credits)
- [License](#license)

## Requirements
1. This version of the package is only compatible with PHP 7.2 or higher.

2. To use this package, you need to install the **[FFMpeg](https://ffmpeg.org/download.html)**. You will need both FFMpeg and FFProbe binaries to use it.

## Installation
Install the package via **[composer](https://getcomposer.org/)**:
``` bash
composer require aminyazdanpanah/php-ffmpeg-video-streaming
```
Alternatively, add the dependency directly to your `composer.json` file:
``` json
"require": {
    "aminyazdanpanah/php-ffmpeg-video-streaming": "^1.1"
}
```

## Quickstart
First of all, you need to include the package in Your Code:
``` php
require 'vendor/autoload.php'; // path to the autoload file
```
**Note:** If you are using such a framework(e.g. **[Laravel](https://github.com/laravel/laravel)**) that include the autoload automatically, then you can skip this step.

### Configuration
FFMpeg will autodetect FFmpeg and FFprobe binaries. If you want to give binary paths explicitly, you can pass an array as configuration. A Psr\Logger\LoggerInterface can also be passed to log binary executions.

``` php
$config = [
    'ffmpeg.binaries'  => '/usr/bin/ffmpeg',
    'ffprobe.binaries' => '/usr/bin/ffprobe',
    'timeout'          => 3600, // The timeout for the underlying process
    'ffmpeg.threads'   => 12,   // The number of threads that FFMpeg should use
];
    
$ffmpeg = Streaming\FFMpeg::create($config);
```

### Opening a File
There are three ways to open a file:

#### 1. From a Local Path
You can pass a local path of video to the `open` method:
``` php
$video = $ffmpeg->open('/var/www/media/videos/test.mp4');
```

#### 2. From a Cloud
You can open a file by passing a URL to the `fromURL` method:
``` php
$video = $ffmpeg->fromURL('https://www.aminyazdanpanah.com/my_sweetie.mp4');
```

A path to save the file, the method of request, and **[request options](http://docs.guzzlephp.org/en/stable/request-options.html)** can also be passed to the method.
``` php
$api = 'https://www.aminyazdanpanah.com/api/v1.0';
$save_to = '/var/www/media/videos/my_sweetie.mp4';
$method = 'POST';
$current_percentage = 0;
$options = [
    'auth' => ['username', 'password', 'digest'],
    'form_params' => [
        'token'  => 'YOR_TOKEN',
        'method' => 'download',
        'path'   => ['dir3', 'videos', 'my_sweetie.mp4']
    ],
    'headers' => [
        'User-Agent'        => 'testing/1.0',
        'Accept'            => 'application/json',
        'X-Authentication'  => 'Bearer x1234'
    ],
    'progress' => function(
        $downloadTotal,
        $downloadedBytes,
        $uploadTotal,
        $uploadedBytes
    ) use (&$current_percentage) {
        $percentage = ($downloadTotal > 200) ? intval(($downloadedBytes / $downloadTotal) * 100) : 0;
        if ($current_percentage !== $percentage) {
            // You can update a field in your database
            // You can also create a socket connection and show the progress to users
            echo '$percentage% is downloaded\n';
            $current_percentage = $percentage;
        }
    },
];

$video = $ffmpeg->fromURL($api, $save_to, $method, $options);
```
**NOTE:** This package uses **[Guzzle](https://github.com/guzzle/guzzle)** to send and receive files. [Learn more](http://docs.guzzlephp.org/en/stable/index.html).

#### 3. From Amazon S3
Amazon S3 or Amazon Simple Storage Service is a service offered by **[Amazon Web Services (AWS)](https://aws.amazon.com/)** that provides object storage through a web service interface. [Learn more](https://en.wikipedia.org/wiki/Amazon_S3)
- For getting credentials, you need to have an AWS account or you can **[create one](https://portal.aws.amazon.com/billing/signup#/start)**.
- Before you get started, please read the AWS SDK for PHP Document found **[here](https://aws.amazon.com/sdk-for-php/)**.

For downloading a file from Amazon S3, you need to pass an associative array of options, the name of your bucket, and the key of your bucket to the `fromS3` method:
``` php
$config = [
    'version'     => 'latest',
    'region'      => 'us-west-1',
    'credentials' => [
        'key'    => 'my-access-key-id',
        'secret' => 'my-secret-access-key',
    ]
];

$bucket = 'my-bucket-name';
$key = '/videos/my_sweetie.mp4';

$video = $ffmpeg->fromS3($config, $bucket, $key);
```
A path can also be passed to save the file on your local computer/server.

### DASH
**[Dynamic Adaptive Streaming over HTTP (DASH)](https://en.wikipedia.org/wiki/Dynamic_Adaptive_Streaming_over_HTTP)**, also known as MPEG-DASH, is an adaptive bitrate streaming technique that enables high quality streaming of media content over the Internet delivered from conventional HTTP web servers.

Similar to Apple's HTTP Live Streaming (HLS) solution, MPEG-DASH works by breaking the content into a sequence of small HTTP-based file segments, each segment containing a short interval of playback time of content that is potentially many hours in duration, such as a movie or the live broadcast of a sports event. The content is made available at a variety of different bit rates, i.e., alternative segments encoded at different bit rates covering aligned short intervals of playback time. While the content is being played back by an MPEG-DASH client, the client uses a bit rate adaptation (ABR) algorithm to automatically select the segment with the highest bit rate possible that can be downloaded in time for playback without causing stalls or re-buffering events in the playback. The current MPEG-DASH reference client dash.js offers both buffer-based (BOLA) and hybrid (DYNAMIC) bit rate adaptation algorithms. Thus, an MPEG-DASH client can seamlessly adapt to changing network conditions and provide high quality playback with fewer stalls or re-buffering events. [Learn more](https://en.wikipedia.org/wiki/Dynamic_Adaptive_Streaming_over_HTTP)
 
Create DASH Files:
``` php
$video->DASH()
    ->HEVC() // Format of the video. Alternatives: X264() and VP9()
    ->autoGenerateRepresentations() // Auto generate representations
    ->setAdaption('id=0,streams=v id=1,streams=a') // Set the adaption.
    ->save(); // It can be passed a path to the method or it can be null
```
You can also create multi-representations video files using the `Representation` object:
``` php
use Streaming\Representation;

$rep_1 = (new Representation())->setKiloBitrate(800)->setResize(1080 , 720);
$rep_2 = (new Representation())->setKiloBitrate(300)->setResize(640 , 360);

$video->DASH()
    ->HEVC()
    ->addRepresentation($rep_1) // Add a representation
    ->addRepresentation($rep_2) 
    ->setAdaption('id=0,streams=v id=1,streams=a') // Set a adaption.
    ->save('/var/www/media/videos/dash/test.mpd');
```
See **[DASH options](https://ffmpeg.org/ffmpeg-formats.html#dash-2)** for more information.

### HLS
**[HTTP Live Streaming (also known as HLS)](https://en.wikipedia.org/wiki/HTTP_Live_Streaming)** is an HTTP-based adaptive bitrate streaming communications protocol implemented by Apple Inc. as part of its QuickTime, Safari, OS X, and iOS software. Client implementations are also available in Microsoft Edge, Firefox and some versions of Google Chrome. Support is widespread in streaming media servers.

HLS resembles MPEG-DASH in that it works by breaking the overall stream into a sequence of small HTTP-based file downloads, each download loading one short chunk of an overall potentially unbounded transport stream. A list of available streams, encoded at different bit rates, is sent to the client using an extended M3U playlist. [Learn more](https://en.wikipedia.org/wiki/HTTP_Live_Streaming)
 
Create HLS files based on original video(auto generate qualities).
``` php
$video->HLS()
    ->X264()
    ->autoGenerateRepresentations([720, 360]) // You can limit the numbers of representatons
    ->save();
```
Create multi-qualities video files using the `Representation` object(set bit-rate and size manually):
``` php
use Streaming\Representation;

$rep_1 = (new Representation())->setKiloBitrate(1000)->setResize(1080 , 720);
$rep_2 = (new Representation())->setKiloBitrate(500)->setResize(640 , 360);
$rep_3 = (new Representation())->setKiloBitrate(200)->setResize(480 , 270);

$video->HLS()
    ->X264()
    ->setHlsBaseUrl('https://bucket.s3-us-west-1.amazonaws.com/videos') // Add a base URL
    ->addRepresentation($rep_1)
    ->addRepresentation($rep_2)
    ->addRepresentation($rep_3)
    ->setHlsTime(5) // Set Hls Time. Default value is 10 
    ->setHlsAllowCache(false) // Default value is true 
    ->save();
```
**NOTE:** You cannot use HEVC and VP9 formats for HLS packaging.

#### Encrypted HLS
The encryption process requires some kind of secret (key) together with an encryption algorithm. HLS uses AES in cipher block chaining (CBC) mode. This means each block is encrypted using the ciphertext of the preceding block. [Learn more](https://en.wikipedia.org/wiki/Block_cipher_mode_of_operation)

You need to pass both `URL to the key` and `path to save a random key` to the `generateRandomKeyInfo` method:
``` php
//A path you want to save a random key on your server
$save_to = '/var/www/my_website_project/keys/enc.key';

//A URL (or a path) to access the key on your website
$url = 'https://www.aminyazdanpanah.com/keys/enc.key';// or '/keys/enc.key';

$video->HLS()
    ->X264()
    ->setTsSubDirectory('ts_files')// put all ts files in a subdirectory
    ->generateRandomKeyInfo($url, $save_to)
    ->autoGenerateRepresentations([1080, 480, 240])
    ->save('/var/www/media/videos/hls/test.m3u8');
```
**NOTE:** It is very important to protect your key on your website using a token or a session/cookie(****It is highly recommended****).    

See **[HLS options](https://ffmpeg.org/ffmpeg-formats.html#hls-2)** for more information.
### Transcoding
A format can also extend `FFMpeg\Format\ProgressableInterface` to get realtime information about the transcoding. 

Transcoding progress can be monitored in realtime, see Format documentation in **[PHP-FFMpeg documentation](https://github.com/PHP-FFMpeg/PHP-FFMpeg#documentation)** for more information.
``` php
$format = new Streaming\Format\HEVC();
$current_percentage = 0;

$format->on('progress', function ($video, $format, $percentage) use (&$current_percentage) {
    if ($current_percentage !== intval($percentage)) {
        // You can update a field in your database
        // You can also create a socket connection and show the progress to users
        echo '$percentage% is transcoded\n';
        $current_percentage = intval($percentage);
    }
});

$video->DASH()
    ->setFormat($format)
    ->autoGenerateRepresentations()
    ->setAdaption('id=0,streams=v id=1,streams=a')
    ->save();
```
HLS Transcoding:
``` php
$format = new Streaming\Format\X264();
$current_percentage = 0;

$format->on('progress', function ($video, $format, $percentage) use (&$current_percentage) {
    if ($current_percentage !== intval($percentage)) {
        echo '$percentage% is transcoded\n';
        $current_percentage = intval($percentage);
    }
});

$video->HLS()
    ->setFormat($format)
    ->setTsSubDirectory('ts_files')
    ->setHlsBaseUrl('https://bucket.s3-us-west-1.amazonaws.com/videos')
    ->autoGenerateRepresentations([240, 144], [200, 100]) // You can also set the kilo bite rate of each video
    ->save('/var/www/media/videos/dash/test.m3u8');
```

### Saving Files
There are three options to save your packaged video files:

#### 1. To a Local Path
You can pass a local path to the `save` method. If there was no directory in the path, then the package auto makes the directory.
``` php
$dash = $video->DASH()
            ->HEVC()
            ->autoGenerateRepresentations()
            ->setAdaption('id=0,streams=v id=1,streams=a');
            
$dash->save('/var/www/media/videos/dash/test.mpd');
```
It can also be null. The default path to save files is the input path.
``` php
$hls = $video->HLS()
            ->X264()
            ->autoGenerateRepresentations();
            
$hls->save();
```
**NOTE:** If you opened a file from cloud and did not pass a path to save a file, then you have to pass a local path to the `save` method.

#### 2. To a Cloud
You can save your files to a cloud using the `saveToCloud` method. 
``` php
$api = 'https://www.aminyazdanpanah.com/api/v1.0/video/uploading';
$field_name = 'MY_FILES';
$method = 'POST';
$headers = [
    'User-Agent'        => 'testing/1.0',
    'Accept'            => 'application/json',
    'X-Authentication'  => 'Bearer x1234'
]
$current_percentage = 0;
$options = [
    'auth' => ['username', 'password', 'digest'],
    'progress' => function(
        $downloadTotal,
        $downloadedBytes,
        $uploadTotal,
        $uploadedBytes
    ) use (&$current_percentage) {
        $percentage = ($uploadTotal > 200) ? intval(($uploadedBytes / $uploadTotal) * 100) : 0;
        if ($current_percentage !== $percentage) {
            // You can update a field in your database
            // You can also create a socket connection and show the progress to users
            echo '$percentage% is uploaded\n';
            $current_percentage = $percentage;
        }
    },
];

$dash->saveToCloud($api, $field_name, null, $method, $headers, $options);
```
A path can also be passed to save a copy of files on your local server/computer:
``` php
$save_to = '/var/www/media/videos/hls/test.m3u8';
$hls->saveToCloud($api, $field_name, $save_to, $method, $headers, $options);
```

#### 3. TO Amazon S3
You can save and upload entire packaged video files to **[Amazon S3](https://aws.amazon.com/)**. For uploading files, you need to have credentials.
``` php
$config = [
    'version' => 'latest',
    'region' => 'us-west-1',
    'credentials' => [
        'key' => 'my-access-key-id',
        'secret' => 'my-secret-access-key',
    ]
];

$dest = 's3://bucket'; 
```
Upload DASH files to Amazon Simple Storage Service:
``` php
$dash->saveToS3($config, $dest);
```
A path can also be passed to save a copy of files on your local computer/server.
``` php
$hls->saveToS3($config, $dest, '/var/www/media/videos/hls/test.m3u8');
```
**NOTE:** You can mix opening and saving options together. For instance, you can open a file on your local computer/server and save packaged files to a Cloud (or vice versa).   

![schema](/docs/schema.gif?raw=true "schema" )

### Other Advanced Features
You can easily use other advanced features in the **[PHP-FFMpeg](https://github.com/PHP-FFMpeg/PHP-FFMpeg)** library. In fact, when you open a file with the `open` method(or `fromURL`), it holds the Media object that belongs to the PHP-FFMpeg.
``` php
$ffmpeg = Streaming\FFMpeg::create()
$video = $$ffmpeg->fromURL('https://www.aminyazdanpanah.com/my_sweetie.mp4', '/var/wwww/media/my/new/video.mp4');
```

#### Example(Extracting image)
You can extract a frame at any timecode using the `FFMpeg\Media\Video::frame` method.
``` php
$frame = $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(42));
$frame->save('image.jpg');
```

## Several Open Source Players
You can use these players to play your packaged videos.
- **WEB**
    - DASH and HLS: **[Plyr](https://github.com/sampotts/plyr)**
    - DASH and HLS: **[MediaElement.js](https://github.com/mediaelement/mediaelement)**
    - DASH and HLS: **[Clappr](https://github.com/clappr/clappr)**
    - DASH and HLS: **[Flowplayer](https://flowplayer.com/)**
    - DASH and HLS: **[Shaka Player](https://github.com/google/shaka-player)**
    - DASH and HLS: **[videojs-http-streaming (VHS)](https://github.com/videojs/http-streaming)**
    - DASH: **[dash.js](https://github.com/Dash-Industry-Forum/dash.js)**
    - HLS: **[hls.js](https://github.com/video-dev/hls.js)**
- **Android**
    - DASH and HLS: **[ExoPlayer](https://github.com/google/ExoPlayer)**
    
## Contributing and Reporting Bugs
I'd love your help in improving, correcting, adding to the specification.
Please **[file an issue](https://github.com/aminyazdanpanah/PHP-FFmpeg-video-streaming/issues)** or **[submit a pull request](https://github.com/aminyazdanpanah/PHP-FFmpeg-video-streaming/pulls)**.
- Please see **[Contributing File](https://github.com/aminyazdanpanah/PHP-FFmpeg-video-streaming/blob/master/CONTRIBUTING.md)** for more information.
- Please, just **[file an issue](https://github.com/aminyazdanpanah/PHP-FFmpeg-video-streaming/issues)** for reporting bugs or if you have any question. 
- If you discover a security vulnerability within this package, please see **[SECURITY File](https://github.com/aminyazdanpanah/PHP-FFmpeg-video-streaming/blob/master/SECURITY.md)** for more information to help with that.

## Credits
- **[Amin Yazdanpanah](https://www.aminyazdanpanah.com/?u=github.com/aminyazdanpanah/PHP-FFmpeg-video-streaming)**

## License
The MIT License (MIT). Please see **[License File](https://github.com/aminyazdanpanah/PHP-FFmpeg-video-streaming/blob/master/LICENSE)** for more information.