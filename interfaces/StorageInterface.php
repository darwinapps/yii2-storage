<?php

namespace darwinapps\storage\interfaces;

interface StorageInterface
{
    /**
     * Puts a file to the storage
     * @param \yii\web\UploadedFile $file uploaded file
     * @param string $path
     * @return string $id the converted asset $id
     */
    public function put(\yii\web\UploadedFile $file, $path = null);

    /**
     * Downloads file from the storage
     * @param string $id
     * @param string $filename
     * @return bool $success
     */
    public function download($id, $filename);


    /**
     * @param string $id
     * @param string $dir
     * @return string $newId
     */
    public function move($id, $dir);


    /**
     * @param string $id
     * @return string $text
     */
    public function getText($id);
}
