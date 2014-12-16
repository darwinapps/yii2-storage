<?php

namespace darwinapps\storage\adapters;

use Yii;
use yii\helpers\FileHelper;

class FileSystem extends BaseAdapter
{
    public $uploadPath = '@runtime/uploads';
    public $mode = 0775;

    protected function getRealPath($path)
    {
        return Yii::getAlias($this->uploadPath) . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * @param \yii\web\UploadedFile $file uploaded file
     * @return string $path
     */
    public function put(\yii\web\UploadedFile $file, $dir = null)
    {
        $uuid = $this->generate_unique_id();

        $key = $dir
            ? $dir . DIRECTORY_SEPARATOR . $uuid . '.' . $file->extension
            : substr($uuid, 0, 3) . DIRECTORY_SEPARATOR . $uuid . '.' . $file->extension;

        $path = $this->getRealPath($key);
        FileHelper::createDirectory(dirname($path), $this->mode);
        if ($file->saveAs($path)) {
            return $key;
        }
    }

    public function download($path, $filename)
    {
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header("Content-Type: " . FileHelper::getMimeTypeByExtension($filename));

        if ($stream = fopen($this->getRealPath($path), 'r')) {
            while (!feof($stream)) {
                echo fread($stream, 8192);
            }
            return fclose($stream);
            return true;
        }
        return false;
    }

}