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
            : ((isset($this->adapter['class']) && $this->adapter['class'] != 'darwinapps\storage\adapters\FileSystem')
                ? $this->adapter
                : array_merge($this->defaultAdapter(), $this->adapter));
    }

    /**
     * @return \darwinapps\storage\interfaces\StorageInterface|object
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

    public function move($path, $destination)
    {
        return $this->getAdapter()->move($path, $destination);
    }

    public function store(UploadedFile $file)
    {
        if ($path = $this->getAdapter()->put($file)) {
            return [
                'name' => $file->name,
                'size' => $file->size,
                'type' => $file->type,
                'path' => $path,
                'text' => $this->getAdapter()->getText($path)
            ];
        }
        return false;
    }

    public function download($path, $filename)
    {
        return $this->getAdapter()->download($path, $filename);
    }

}