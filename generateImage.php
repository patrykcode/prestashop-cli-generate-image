<?php

require __DIR__ . '/config/config.inc.php';

class GenerateImage
{
    public function regenerate()
    {
        $this->_regenerateThumbnailsCli('all', true);
    }

    private function _regenerateThumbnailsCli($type = 'all', $deleteOldImages = false)
    {
        $process = [
            ['type' => 'categories', 'dir' => _PS_CAT_IMG_DIR_],
            ['type' => 'manufacturers', 'dir' => _PS_MANU_IMG_DIR_],
            ['type' => 'suppliers', 'dir' => _PS_SUPP_IMG_DIR_],
            ['type' => 'products', 'dir' => _PS_PRODUCT_IMG_DIR_],
            ['type' => 'stores', 'dir' => _PS_STORE_IMG_DIR_],
        ];

        // Launching generation process
        foreach ($process as $proc) {
            if ($type != 'all' && $type != $proc['type']) {
                echo "skip";
                continue;
            }

            // Getting format generation
            $formats = ImageType::getImagesTypes($proc['type']);

            if ($type != 'all') {
                $format = (string) (Tools::getValue('format_' . $type));
                if ($format != 'all') {
                    foreach ($formats as $k => $form) {
                        if ($form['id_image_type'] != $format) {
                            unset($formats[$k]);
                        }
                    }
                }
            }

            if (($return = $this->_regenerateNewImagesCli($proc['dir'], $formats, ($proc['type'] == 'products' ? true : false))) === true) {

                echo "Cannot write images for this type: {$proc['type']}. Please check then {$proc['dir']} folder\'s writing permissions.";
            }
        }

    }

    private function _regenerateNewImagesCli($dir, $type, $productsImages = false)
    {
        if (!is_dir($dir)) {
            return false;
        }

        // Should we generate high DPI images?
        $generate_high_dpi_images = (bool) Configuration::get('PS_HIGHT_DPI');

        /*
         * Let's resolve which formats we will use for image generation.
         *
         * In case of .jpg images, the actual format inside is decided by ImageManager.
         */
        $configuredImageFormats = ['webp'];
        $errors = 0;

        if (!$productsImages) {
            $formated_medium = ImageType::getFormattedName('medium');

            foreach (scandir($dir, SCANDIR_SORT_NONE) as $image) {
                if (preg_match('/^[0-9]*\.jpg$/', $image)) {
                    foreach ($type as $k => $imageType) {
                        // Customizable writing dir
                        $newDir = $dir;
                        if (!file_exists($newDir)) {
                            continue;
                        }

                        if (($dir == _PS_CAT_IMG_DIR_) && ($imageType['name'] == $formated_medium) && is_file(_PS_CAT_IMG_DIR_ . str_replace('.', '_thumb.', $image))) {
                            $image = str_replace('.', '_thumb.', $image);
                        }

                        foreach ($configuredImageFormats as $imageFormat) {
                            // For JPG images, we let Imagemanager decide what to do and choose between JPG/PNG.
                            // For webp and avif extensions, we want it to follow our command and ignore the original format.
                            $forceFormat = ($imageFormat !== 'jpg');
                            // If thumbnail does not exist
                            if (!file_exists($newDir . substr($image, 0, -4) . '-' . stripslashes($imageType['name']) . '.' . $imageFormat)) {
                                // Check if original image exists
                                if (!file_exists($dir . $image) || !filesize($dir . $image)) {
                                    echo "\nSource file does not exist or is empty ({ $dir . $image})";
                                } else {
                                    if (!ImageManager::resize(
                                        $dir . $image,
                                        $newDir . substr(str_replace('_thumb.', '.', $image), 0, -4) . '-' . stripslashes($imageType['name']) . '.' . $imageFormat,
                                        (int) $imageType['width'],
                                        (int) $imageType['height'],
                                        $imageFormat,
                                        $forceFormat
                                    )) {
                                        $errors++;
                                        echo "\nFailed to resize image file ({ $dir . $image })";
                                    }

                                    if ($generate_high_dpi_images && !ImageManager::resize(
                                        $dir . $image,
                                        $newDir . substr($image, 0, -4) . '-' . stripslashes($imageType['name']) . '2x.' . $imageFormat,
                                        (int) $imageType['width'] * 2,
                                        (int) $imageType['height'] * 2,
                                        $imageFormat,
                                        $forceFormat
                                    )) {
                                        $errors++;
                                        echo "\nFailed to resize image file to high resolution ({ $dir . $image })";
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            foreach (Image::getAllImages() as $image) {
                $imageObj = new Image($image['id_image']);
                $existing_img = $dir . $imageObj->getExistingImgPath() . '.jpg';

                if (file_exists($existing_img) && filesize($existing_img)) {
                    foreach ($type as $imageType) {
                        foreach ($configuredImageFormats as $imageFormat) {
                            // For JPG images, we let Imagemanager decide what to do and choose between JPG/PNG.
                            // For webp and avif extensions, we want it to follow our command and ignore the original format.
                            $forceFormat = ($imageFormat !== 'jpg');

                            if (!file_exists($dir . $imageObj->getExistingImgPath() . '-' . stripslashes($imageType['name']) . '.' . $imageFormat)) {
                                if (!ImageManager::resize(
                                    $existing_img,
                                    $dir . $imageObj->getExistingImgPath() . '-' . stripslashes($imageType['name']) . '.' . $imageFormat,
                                    (int) $imageType['width'],
                                    (int) $imageType['height'],
                                    $imageFormat,
                                    $forceFormat
                                )) {
                                    $errors++;
                                    echo "\nOriginal image is corrupt ({$existing_img}) for product ID {$imageObj->id_product} or bad permission on folder.";
                                }
                            } else {
                                echo "\n file exists (" . ($dir . $imageObj->getExistingImgPath() . '-' . stripslashes($imageType['name']) . '.' . $imageFormat) . ")";
                            }
                            if ($generate_high_dpi_images) {
                                if (!file_exists($dir . $imageObj->getExistingImgPath() . '-' . stripslashes($imageType['name']) . '2x.' . $imageFormat)) {
                                    if (!ImageManager::resize(
                                        $existing_img,
                                        $dir . $imageObj->getExistingImgPath() . '-' . stripslashes($imageType['name']) . '2x.' . $imageFormat,
                                        (int) $imageType['width'] * 2,
                                        (int) $imageType['height'] * 2,
                                        $imageFormat,
                                        $forceFormat
                                    )) {
                                        $errors++;
                                        echo "\nOriginal image is corrupt ({$existing_img}) for product ID {$imageObj->id_product} or bad permission on folder.";

                                    }
                                }
                            }
                        }
                    }
                } else {
                    $errors++;
                    echo "\nOriginal image is missing or empty ({$existing_img}) for product ID {$imageObj->id_product}";
                }

            }
        }

        return (bool) $errors;
    }
}

$obj = new GenerateImage();
echo "\nStart\n";
$obj->regenerate();
echo "\nEnd\n";
