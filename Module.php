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

    public $storageConfig = [];

    public function init()
    {
        parent::init();

        $this->storageConfig = empty($this->storageConfig)
            ? $this->defaultStorageConfig()
            : array_merge($this->defaultStorageConfig(), $this->storageConfig);

    }

    protected function defaultStorageConfig()
    {
        return [
            'class' => 'darwinapps\storage\components\Storage'
        ];
    }

    public function store(UploadedFile $file)
    {
        return $this->get('storage')->store($file);
    }

    public function download($path)
    {
        return $this->get('storage')->download($path);
    }

    public function bootstrap($app)
    {
        if ($app instanceof \yii\web\Application) {
            $app->getUrlManager()->addRules([
                [
                    'verb' => 'GET',
                    'pattern' => $this->id,
                    'encodeParams' => true,
                    'route' => $this->id . '/default/view'
                ],
            ], false);
        }
        $this->setComponents(['storage' => $this->storageConfig]);
    }

}