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
     * @return bool $success
     */
    public function download($id);

    /**
     * Downloads converted version of the file from the storage
     * @param string $id
     * @param string $type
     * @return bool $success
     */
    public function preview($id, $type = 'application/pdf');

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
