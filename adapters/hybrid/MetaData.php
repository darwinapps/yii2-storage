<?php

namespace darwinapps\storage\adapters\hybrid;

class MetaData extends \yii\base\Model
{
    public $id;

    public $gd_id;

    public $preview_name;
    public $preview_type;
    public $preview_time;

    public $origin_name;
    public $origin_type;
    public $origin_time;
}