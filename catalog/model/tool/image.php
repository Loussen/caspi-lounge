<?php
class ModelToolImage extends Model {

    // Function to scale images with given dimensions. What's left will be filles with white.
    function resize($filename, $width, $height) {

        if (!file_exists(DIR_IMAGE . $filename) || !is_file(DIR_IMAGE . $filename)) {
            return;
        }

        $info = pathinfo($filename);
        $extension = $info['extension'];

        $old_image = $filename;
        $new_image = 'cache/' . substr($filename, 0, strrpos($filename, '.')) . '-' . $width . 'x' . $height . '.' . $extension;

        if (!file_exists(DIR_IMAGE . $new_image) || (filemtime(DIR_IMAGE . $old_image) > filemtime(DIR_IMAGE . $new_image))) {
            $path = '';

            $directories = explode('/', dirname(str_replace('../', '', $new_image)));

            foreach ($directories as $directory) {
                $path = $path . '/' . $directory;

                if (!file_exists(DIR_IMAGE . $path)) {
                    @mkdir(DIR_IMAGE . $path, 0777);
                }
            }

            $image = new Image(DIR_IMAGE . $old_image);
            $image->resize($width, $height);
            $image->save(DIR_IMAGE . $new_image);
        }

        if ($this->request->server['HTTPS']) {
            return $this->config->get('config_ssl') . 'image/' . $new_image;
        } else {
            return $this->config->get('config_url') . 'image/' . $new_image;
        }

//		if (isset($this->request->server['HTTPS']) && (($this->request->server['HTTPS'] == 'on') || ($this->request->server['HTTPS'] == '1'))) {
//			return HTTPS_IMAGE . $new_image;
//		} else {
//			return HTTP_IMAGE . $new_image;
//		}

    }


    // Function to crop an image with given dimensions. What doesn/t fit will be cut off.
    function cropsize($filename, $width, $height) {

        if (!file_exists(DIR_IMAGE . $filename) || !is_file(DIR_IMAGE . $filename)) {
            return;
        }

        $info = pathinfo($filename);
        $extension = $info['extension'];

        $old_image = $filename;
        $new_image = 'cache/' . substr($filename, 0, strrpos($filename, '.')) . '-cr-' . $width . 'x' . $height . '.' . $extension;

        if (!file_exists(DIR_IMAGE . $new_image) || (filemtime(DIR_IMAGE . $old_image) > filemtime(DIR_IMAGE . $new_image))) {
            $path = '';

            $directories = explode('/', dirname(str_replace('../', '', $new_image)));

            foreach ($directories as $directory) {
                $path = $path . '/' . $directory;

                if (!file_exists(DIR_IMAGE . $path)) {
                    @mkdir(DIR_IMAGE . $path, 0777);
                }
            }

            $image = new Image(DIR_IMAGE . $old_image);
            $image->cropsize($width, $height);
            $image->save(DIR_IMAGE . $new_image);
        }

        if ($this->request->server['HTTPS']) {
            return $this->config->get('config_ssl') . 'image/' . $new_image;
        } else {
            return $this->config->get('config_url') . 'image/' . $new_image;
        }

//		if (isset($this->request->server['HTTPS']) && (($this->request->server['HTTPS'] == 'on') || ($this->request->server['HTTPS'] == '1'))) {
//			return HTTPS_IMAGE . $new_image;
//		} else {
//			return HTTP_IMAGE . $new_image;
//		}

    }

    // Function to resize image with one given max size.
    function onesize($filename, $maxsize) {

        if (!file_exists(DIR_IMAGE . $filename) || !is_file(DIR_IMAGE . $filename)) {
            return;
        }

        $info = pathinfo($filename);
        $extension = $info['extension'];

        $old_image = $filename;
        $new_image = 'cache/' . substr($filename, 0, strrpos($filename, '.')) . '-max-' . $maxsize . '.' . $extension;

        if (!file_exists(DIR_IMAGE . $new_image) || (filemtime(DIR_IMAGE . $old_image) > filemtime(DIR_IMAGE . $new_image))) {
            $path = '';

            $directories = explode('/', dirname(str_replace('../', '', $new_image)));

            foreach ($directories as $directory) {
                $path = $path . '/' . $directory;

                if (!file_exists(DIR_IMAGE . $path)) {
                    @mkdir(DIR_IMAGE . $path, 0777);
                }
            }

            $image = new Image(DIR_IMAGE . $old_image);
            $image->onesize($maxsize);
            $image->save(DIR_IMAGE . $new_image);
        }

        if ($this->request->server['HTTPS']) {
            return $this->config->get('config_ssl') . 'image/' . $new_image;
        } else {
            return $this->config->get('config_url') . 'image/' . $new_image;
        }


//		if (isset($this->request->server['HTTPS']) && (($this->request->server['HTTPS'] == 'on') || ($this->request->server['HTTPS'] == '1'))) {
//			return HTTPS_IMAGE . $new_image;
//		} else {
//			return HTTP_IMAGE . $new_image;
//		}

    }

}
?>