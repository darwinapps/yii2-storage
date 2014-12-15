<?php

namespace darwinapps\storage\components;

use yii\web\UploadedFile;

class Storage extends \yii\base\Component
{
    public $adapter = [];

    /* @var StorageInterface */
    protected $_adapter;

    public function init()
    {
        parent::init();

        $this->adapter = empty($this->adapter)
            ? $this->defaultAdapter()
            : $this->adapter['class']
                ? $this->adapter
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
        if ($path = $this->getAdapter()->put($file)) {
            return [
                'name' => $file->name,
                'size' => $file->size,
                'type' => $file->type,
                'path' => $path,
            ];
        }
        return false;
    }

    public function download($path)
    {
        return $this->getAdapter()->stream($path);
    }

}