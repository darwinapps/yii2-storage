<?php

namespace darwinapps\storage\adapters;

use Yii;
use yii\helpers\FileHelper;
use darwinapps\storage\adapters\filesystem\MetaData;
use darwinapps\storage\models\File;

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
        $id = $this->generate_unique_id();

        if (!$dir)
            $dir = substr($id, -3, 3) . DIRECTORY_SEPARATOR . $id;

        $metadata = new MetaData([
            'id' => $id,
            'name' => $file->name,
            'type' => FileHelper::getMimeTypeByExtension($file->name),
            'size' => $file->size,
            'path' => $dir,
        ]);

        $path = $this->getRealPath($metadata->getPath());

        FileHelper::createDirectory(dirname($path), $this->mode);

        if ($file->saveAs($path) && $this->saveMetaData($id, $metadata)) {
            return new File([
                'id' => $metadata->id,
                'name' => $metadata->name,
                'type' => $metadata->type,
                'size' => $metadata->size,
                'has_preview' => $file->type == 'application/pdf',
                'text' => ''
            ]);
        }
        return false;
    }

    protected function getMetaDataFilePath($id)
    {
        return $this->getRealPath('metadata' . DIRECTORY_SEPARATOR . substr($id, -3, 3) . DIRECTORY_SEPARATOR . $id);
    }

    protected function saveMetaData($id, MetaData $metadata)
    {
        $file = $this->getMetaDataFilePath($id);
        if (!file_exists(dirname($file)))
            FileHelper::createDirectory(dirname($file), $this->mode);
        return file_put_contents($file, serialize($metadata->toArray())) !== false;
    }

    protected function loadMetaData($id)
    {
        $file = $this->getMetaDataFilePath($id);
        $metadata = unserialize(file_get_contents($file));
        return new MetaData($metadata);
    }

    /**
     * @inheritdoc
     */
    public function download($id, $headers = [])
    {
        $metadata = $this->loadMetaData($id);
        header('Content-Disposition: attachment; filename="' . $metadata->name . '"');
        header("Content-Type: " . $metadata->type);
        if (isset($headers) && is_array($headers) && !empty($headers)) {
            foreach ($headers as $header) {
                header($header);
            }
        }

        if ($stream = fopen($this->getRealPath($metadata->getPath()), 'r')) {
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
    public function get($id)
    {
        $metadata = $this->loadMetaData($id);

        if ($file = file_get_contents($this->getRealPath($metadata->getPath()))) {
            return $file;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function preview($id, $type = 'application/pdf', $headers = [])
    {
        $metadata = $this->loadMetaData($id);
        if ($metadata->type == $type) {
            return $this->download($id, $headers);
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function move($id, $dir)
    {
        $metadata = $this->loadMetaData($id);

        $source = $this->getRealPath($metadata->getPath());
        $target = $this->getRealPath($dir . DIRECTORY_SEPARATOR . $id);
        FileHelper::createDirectory(dirname($target), $this->mode);

        $metadata->path = $dir;
        return rename($source, $target) && $this->saveMetaData($id, $metadata);
    }
}