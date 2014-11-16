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
    public function put(\yii\web\UploadedFile $file)
    {
        $uuid = $this->generate_unique_id();
        $key = substr($uuid, 0, 3) . '/' . $uuid . '.' . $file->extension;

        $path = $this->getRealPath($key);
        FileHelper::createDirectory(dirname($path), $this->mode);
        if ($file->saveAs($path)) {
            return $key;
        }
    }

    public function stream($path, $mode = 'r')
    {
        if ($stream = fopen($this->getRealPath($path), $mode)) {
            while (!feof($stream)) {
                echo fread($stream, 8192);
            }
            return fclose($stream);
        }
        return false;
    }

}