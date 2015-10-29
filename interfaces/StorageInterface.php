<?php

namespace darwinapps\storage\interfaces;

interface StorageInterface
{
    /**
     * Puts a file to the storage
     * @param \yii\web\UploadedFile $file uploaded file
     * @param string $path
     * @return \darwinapps\storage\models\File $file
     */
    public function put(\yii\web\UploadedFile $file, $path = null);

    /**
     * Downloads file from the storage
     * @param string $id
     * @param array $headers
     * @return bool $success
     */
    public function download($id, $headers = []);

    /**
     * Get file from the storage
     * @param string $id
     * @return bool $success
     */
    public function get($id);

    /**
     * Synchronize file in the storage
     * @param string $id
     * @return bool $success
     */
    public function sync($id);

    /**
     * Downloads converted version of the file from the storage
     * @param string $id
     * @param string $type
     * @param array $headers
     * @return bool $success
     */
    public function preview($id, $type = 'application/pdf', $headers = []);

    /**
     * @param string $id
     * @param string $dir
     * @return bool
     */
    public function move($id, $dir);


    /**
     * @param string $id
     * @return string $text
     */
    public function getText($id);
}
