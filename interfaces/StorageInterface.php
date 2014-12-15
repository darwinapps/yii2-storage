<?php

namespace darwinapps\storage\interfaces;

interface StorageInterface
{
    /**
     * Puts a file to the storage
     * @param \yii\web\UploadedFile $file
     * @return string the converted asset file path, relative to $basePath.
     */
    public function put(\yii\web\UploadedFile $file);

    /**
     * Downloads file from the storage
     * @param string $path
     * @param string $filename
     */
    public function download($path, $filename);
}
