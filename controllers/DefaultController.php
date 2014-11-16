<?php

namespace darwinapps\storage\controllers;

class DefaultController extends \yii\web\Controller
{
    public function actionView($path, $name = null, $type = null)
    {
        header('Content-Disposition: attachment; filename="' . $name ? $name : basename($path) . '"');
        if ($type)
            header("Content-Type: " . $type);

        $this->module()->get('storage')->download($path);
        \Yii::$app->end();
    }
}