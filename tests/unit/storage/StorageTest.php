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

class StorageTest extends \Codeception\TestCase\Test
{
    public $uploadPath = '@tests/_tmp';

    public function setUp()
    {
        parent::setUp();
        FileHelper::createDirectory(Yii::getAlias($this->uploadPath));
    }

    public function tearDown()
    {
        FileHelper::removeDirectory(Yii::getAlias($this->uploadPath));
        parent::tearDown();
    }

    public function test()
    {
        $storage = new Module('storage', 'storage', [
            'storage' => [
                'uploadPath' => $this->uploadPath
            ]
        ]);
        //var_dump($storage); exit;
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
        ], (array)$storedfile);
        $this->assertNotEmpty($storedfile->path);

        ob_start();
        $storage->download($storedfile);
        $file = ob_get_flush();
        $this->assertEquals($size, strlen($file));
    }

    protected function assertArrayContainsArray($needle, $haystack)
    {
        if ($haystack instanceof \Codeception\Maybe) {
            $haystack = $haystack->__value(); // or else array_has_key fails for keys with null values
        }

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
