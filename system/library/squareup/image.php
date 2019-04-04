<?php

namespace Squareup;

class Image extends Library {
    const WIDTH = 720;
    const HEIGHT = 720;

    private $source_image_path = null;
    private $target_image_path = null;
    private $target_image_name = null;
    private $old = null;
    private $new = null;
    private $info = null;
    private $mime = null;
    private $width = null;
    private $height = null;

    public function __construct($registry, $source_image_path, $target_image_name) {
        parent::__construct($registry);

        $this->source_image_path = $source_image_path;
        $this->target_image_name = $target_image_name;
    }

    public function __destruct() {
        if (is_resource($this->old)) {
            @imagedestroy($this->old);
        }

        if (is_resource($this->new)) {
            @imagedestroy($this->new);
        }
    }

    public function resize() {
        set_error_handler(array($this, 'errorHandler'));

        $resized_image = null;

        try {
            $this->init();

            $this->target_image_path = $this->makeDir(DIR_IMAGE . 'cache/squareup') . $this->target_image_name . '.jpeg';

            $this->initInfo();
            $this->initOldImage();

            if ($this->width == self::WIDTH && $this->height == self::HEIGHT && $this->mime == 'image/jpeg') {
                copy($this->source_image_path, $this->target_image_path);
            } else {
                $this->makeResized();
            }

            $resized_image = $this->target_image_path;
        } catch (\Squareup\Exception\Image $e) {
            $this->squareup_diff->output($e->getMessage());
        }

        restore_error_handler();

        return $resized_image;
    }

    protected function makeResized() {
        $xpos = 0;
        $ypos = 0;

        $scale_w = self::WIDTH / $this->width;
        $scale_h = self::HEIGHT / $this->height;

        $scale = min($scale_w, $scale_h);

        $new_width = (int)($this->width * $scale);
        $new_height = (int)($this->height * $scale);
        $xpos = (int)((self::WIDTH - $new_width) / 2);
        $ypos = (int)((self::HEIGHT - $new_height) / 2);

        $this->new = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if ($this->mime == 'image/png') {
            imagealphablending($this->new, false);
            imagesavealpha($this->new, true);
            $background = imagecolorallocatealpha($this->new, 255, 255, 255, 127);
            imagecolortransparent($this->new, $background);
        } else {
            $background = imagecolorallocate($this->new, 255, 255, 255);
        }

        imagefilledrectangle($this->new, 0, 0, self::WIDTH, self::HEIGHT, $background);

        imagecopyresampled($this->new, $this->old, $xpos, $ypos, 0, 0, $new_width, $new_height, $this->width, $this->height);

        imagedestroy($this->old);

        imagejpeg($this->new, $this->target_image_path);

        imagedestroy($this->new);
    }

    protected function initOldImage() {
        $this->old = null;

        if ($this->mime == 'image/gif') {
            $this->old = imagecreatefromgif($this->source_image_path);
        } elseif ($this->mime == 'image/png') {
            $this->old = imagecreatefrompng($this->source_image_path);
        } elseif ($this->mime == 'image/jpeg') {
            $this->old = imagecreatefromjpeg($this->source_image_path);
        }

        if (is_null($this->old)) {
            throw new \Squareup\Exception\Image('Cannot load image: ' . $this->source_image_path);
        }
    }

    protected function initInfo() {
        $this->info = getimagesize($this->source_image_path);

        $this->width  = $this->info[0];
        $this->height = $this->info[1];
        $this->mime = isset($this->info['mime']) ? $this->info['mime'] : null;

        if (is_null($this->mime)) {
            throw new \Squareup\Exception\Image('Cannot determine the image mime: ' . $this->source_image_path);
        }

        if (empty($this->width) || empty($this->height)) {
            throw new \Squareup\Exception\Image('Cannot determine image dimensions (WxH): ' . $this->source_image_path);
        }
    }

    protected function init() {
        if (!extension_loaded('gd')) {
            throw new \Squareup\Exception\Image('PHP GD is not installed!');
        }

        if (!is_readable($this->source_image_path) || !is_file($this->source_image_path)) {
            throw new \Squareup\Exception\Image('Cannot read the source image: ' . $this->source_image_path);
        }
    }

    protected function makeDir($dir) {
        $dir = str_replace('\\', '/', $dir);
        $parts = explode('/', $dir);

        $new_dir = '';

        foreach ($parts as $part) {
            $new_dir .= $part . '/';

            if (!is_dir($new_dir)) {
                if (false === mkdir($new_dir, 0755)) {
                    throw new \Squareup\Exception\Image('Cannot create dir: ' . $new_dir);
                }
            }
        }

        if (!is_writable($new_dir)) {
            throw new \Squareup\Exception\Image('Cannot write to dir: ' . $new_dir);
        }

        return $new_dir;
    }

    public function errorHandler($code, $message, $file, $line) {
        if (error_reporting() === 0) {
            return false;
        }

        switch ($code) {
            case E_NOTICE:
            case E_USER_NOTICE:
                $error = 'Notice';
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $error = 'Warning';
                break;
            case E_ERROR:
            case E_USER_ERROR:
                $error = 'Fatal Error';
                break;
            default:
                $error = 'Unknown';
                break;
        }

        $message = 'PHP ' . $error . ':  ' . $message . ' in ' . $file . ' on line ' . $line;

        throw new \Squareup\Exception\Image($message);
    }
}