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

    public function preview($id, $headers = [])
    {
        return $this->getAdapter()->preview($id, $headers);
    }

    public function getText($path)
    {
        return $this->getAdapter()->getText($path);
    }

    public function move($path, $destination)
    {
        return $this->getAdapter()->move($path, $destination);
    }

    /**
     * @param UploadedFile $file
     * @param null $dir
     * @return \darwinapps\storage\models\File
     */
    public function store(UploadedFile $file, $dir = null)
    {
        \Yii::info("Storing $file to directory '$dir'");
        return $this->getAdapter()->put($file, $dir);
    }

    public function download($id, $headers = [])
    {
        return $this->getAdapter()->download($id, $headers);
    }

    public function get($id)
    {
        return $this->getAdapter()->get($id);
    }
}