<?php

namespace darwinapps\storage\adapters\filesystem;

class MetaData extends \yii\base\Model
{
    public $id;
    public $name;
    public $type;
    public $size;
    public $path;

    public function getPath()
    {
        return $this->path . DIRECTORY_SEPARATOR . $this->id;
    }
}