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
     * @inheritdoc
     */
    public function put(\yii\web\UploadedFile $file, $dir = null)
    {
        $uuid = $this->generate_unique_id();

        $id = $dir
            ? $dir . DIRECTORY_SEPARATOR . $uuid . '.' . $file->extension
            : substr($uuid, 0, 3) . DIRECTORY_SEPARATOR . $uuid . '.' . $file->extension;

        $path = $this->getRealPath($id);
        FileHelper::createDirectory(dirname($path), $this->mode);
        if ($file->saveAs($path)) {
            return $id;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function download($id, $filename)
    {
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header("Content-Type: " . FileHelper::getMimeTypeByExtension($filename));

        if ($stream = fopen($this->getRealPath($id), 'r')) {
            while (!feof($stream)) {
                echo fread($stream, 8192);
            }
            return fclose($stream);
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function move($id, $dir)
    {
        $filename = pathinfo($id, PATHINFO_FILENAME);
        $source = $this->getRealPath($id);
        $target = $this->getRealPath($dir . DIRECTORY_SEPARATOR . $filename);
        FileHelper::createDirectory(dirname($target), $this->mode);
        if (rename($source, $target)) {
            return $dir . DIRECTORY_SEPARATOR . $filename;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getText($id)
    {
        return '';
    }


}