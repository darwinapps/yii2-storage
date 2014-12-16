<?php

namespace darwinapps\storage\interfaces;

interface StorageInterface
{
    /**
     * @param string $fileId
     * @param string $dir
     * @return bool $success
     */
    public function move($fileId, $dir);

    /**
     * Puts a file to the storage
     * @param \yii\web\UploadedFile $file uploaded file
     * @param string $path
     * @return string $path the converted asset file path, relative to $basePath.
     */
    public function put(\yii\web\UploadedFile $file, $path = null);

    /**
     * Downloads file from the storage
     * @param string $path
     * @param string $filename
     * @return bool $success
     */
    public function download($path, $filename);
}
