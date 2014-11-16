<?php

namespace darwinapps\storage;

use yii\web\UploadedFile;
use yii\base\BootstrapInterface;

/**
 * Class Storage
 * @package app\components\storage
 *
 */
class Module extends \yii\base\Module implements BootstrapInterface
{

    public $controllerNamespace = 'darwinapps\storage';

    public $adapter = [];

    /* @var StorageInterface */
    protected $_adapter;

    public function init()
    {
        parent::init();

        $this->adapter = empty($this->adapter)
            ? $this->defaultAdapter()
            : array_merge($this->defaultAdapter(), $this->adapter);

    }

    /**
     * @return StorageInterface|object
     */
    protected function getAdapter()
    {
        return $this->_adapter
            ? $this->_adapter
            : $this->_adapter = \Yii::createObject($this->adapter);
    }

    protected function defaultAdapter()
    {
        return [
            'class' => 'darwinapps\storage\adapters\FileSystem',
            'uploadPath' => '@runtime/uploads'
        ];
    }

    public function store(UploadedFile $file)
    {
        $adapter = $this->getAdapter();
        if ($path = $adapter->put($file)) {
            return new StoredFile([
                'name' => $file->name,
                'size' => $file->size,
                'type' => $file->type,
                'path' => $path,
            ]);
        }
        return false;
    }

    public function download(StoredFile $file)
    {
        header('Content-Disposition: attachment; filename="' . $file->name . '"');
        if ($file->type)
            header("Content-Type: " . $file->type);
        if ($file->size)
            header("Content-Length: " . $file->size);
        return $this->getAdapter()->stream($file->path);
    }

    public function bootstrap($app)
    {
    }

}