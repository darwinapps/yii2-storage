<?php

use yii\helpers\FileHelper;
use darwinapps\storage\Module;

class UploadedFile extends \yii\web\UploadedFile
{
    public function saveAs($file, $deleteTempFile = true)
    {
        if ($this->error == UPLOAD_ERR_OK) {
            return copy($this->tempName, $file);
        }
        return false;
    }
}

class StorageTest extends \yii\codeception\TestCase
{
    public $appConfig = '@tests/unit/_config.php';

    public function test()
    {
        $storage = \Yii::$app->getModule('storage');
        $filename = Yii::getAlias('@tests/_data/nature-q-c-640-480-4.jpg');
        $size = filesize($filename);
        $file = new UploadedFile([
            'name' => 'nature-q-c-640-480-4.jpg',
            'tempName' => $filename,
            'size' => $size,
            'type' => 'image/jpeg',
            'error' => UPLOAD_ERR_OK,
        ]);
        $storedfile = $storage->store($file);

        $this->assertArrayContainsArray([
            'name' => 'nature-q-c-640-480-4.jpg',
            'size' => $size,
            'type' => 'image/jpeg',
        ], $storedfile);
        $this->assertNotEmpty($storedfile['path']);

        ob_start();
        $storage->download($storedfile['path'], $storedfile['name']);
        $file = ob_get_flush();
        $this->assertEquals($size, strlen($file));
    }

    protected function assertArrayContainsArray($needle, $haystack)
    {

        foreach ($needle as $key => $val) {
            $this->assertArrayHasKey($key, $haystack);

            if (is_array($val)) {
                $this->assertArrayContainsArray($val, $haystack[$key]);
            } else {
                $this->assertEquals($val, $haystack[$key]);
            }
        }
    }

}
