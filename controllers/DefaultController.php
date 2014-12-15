<?php

namespace darwinapps\storage\controllers;

class DefaultController extends \yii\web\Controller
{
    public function actionView($path, $name = null, $type = null)
    {
        $this->module->get('storage')->download($path, $name);
        \Yii::$app->end();
    }
}