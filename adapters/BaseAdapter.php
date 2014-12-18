<?php

namespace darwinapps\storage\adapters;

use yii\base\NotSupportedException;

class BaseAdapter extends \yii\base\Component implements \darwinapps\storage\interfaces\StorageInterface
{
    public function generate_unique_id()
    {
        $unique_id = 'ad';
        $i = 100;
        while (--$i >= 0 && strpos($unique_id, 'ad') === 0) {
            $unique_id = uniqid();
        }
        if (!$i) {
            throw new Exception("Unable to generate ad-blocker safe filename");
        }
        return $unique_id;
    }

    /**
     * @inheritdoc
     */
    public function put(\yii\web\UploadedFile $file, $dir = null)
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function download($id)
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function preview($id, $type = 'application/pdf')
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function move($id, $dir)
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function getText($id)
    {
        throw new NotSupportedException();
    }

}