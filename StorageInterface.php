<?php

namespace darwinapps\storage;

interface StorageInterface
{
    /**
     * Puts a file to the storage
     * @param \yii\web\UploadedFile $file
     * @return string the converted asset file path, relative to $basePath.
     */
    public function put(\yii\web\UploadedFile $file);

    /**
     * Streams file from the storage
     * @param \yii\web\UploadedFile $file
     * @return string the converted asset file path, relative to $basePath.
     */
    public function stream($path);
}
