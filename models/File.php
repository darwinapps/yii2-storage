<?php

namespace darwinapps\storage\models;

class File extends \yii\base\Model
{
    public $id;
    public $name;
    public $size;
    public $type;
    public $text;
    public $has_preview;
}