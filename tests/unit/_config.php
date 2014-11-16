<?php

return [
    'id' => 'yii2-storage',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['storage'],
    'modules' => [
        'storage' => [
            'class' => 'darwinapps\storage\Module',
            'adapter' => [
                'uploadPath' => '@tests/_tmp'
            ]
        ]
    ]
];
