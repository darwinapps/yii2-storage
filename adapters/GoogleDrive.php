<?php

namespace darwinapps\storage\adapters;

use Yii;
use yii\helpers\FileHelper;
use yii\base\InvalidConfigException;

class GoogleDrive extends BaseAdapter {

    static $SCOPE = ['https://www.googleapis.com/auth/drive'];

    public $serviceAccountEmail;
    public $serviceAccountPKCS12FilePath;
    public $userEmail;
    public $serviceDomain;

    protected $_auth;
    protected $_client;
    protected $_service;

    public function init() {
        if (!$this->serviceAccountPKCS12FilePath) 
            throw new InvalidConfigException("Google service key path not set");

        if (!$this->serviceAccountEmail) 
            throw new InvalidConfigException("Google service email not set");

        $keyPath = Yii::getAlias($this->serviceAccountPKCS12FilePath);

        if (!file_exists($keyPath))
            throw new InvalidConfigException("Google key not found");

        $key = file_get_contents($keyPath);

        $this->_auth = new \Google_Auth_AssertionCredentials(
            $this->serviceAccountEmail,
            static::$SCOPE,
            $key);

        $this->_client = new \Google_Client();

        if ($this->userEmail)
            $this->_auth->sub = $this->userEmail;

        $this->_client->setAssertionCredentials($this->_auth);

        if ($this->_client->getAuth()->isAccessTokenExpired()) {
            $this->_client->getAuth()->refreshTokenWithAssertion();
        }

        $this->_service = new \Google_Service_Drive($this->_client);
    }

    public function getService()
    {
        return $this->_service;
    }

    /**
     * @param \yii\web\UploadedFile $file uploaded file
     * @return string $path
     */
    public function put(\yii\web\UploadedFile $file)
    {
        $driveFile = new \Google_Service_Drive_DriveFile([
            'title' => $file->baseName
        ]);

        $result = $this->getService()->files->insert($driveFile, array(
          'data' => file_get_contents($file->tempName),
          'uploadType' => 'media',
          'convert' => true,
        ));

        return $result['id'];
    }

    public function stream($path, $mode = 'r')
    {
        $file = $this->getService()->files->get($path);
        $downloadUrl = $file->getDownloadUrl();
        if ($downloadUrl) {
            $request = new Google_HttpRequest($downloadUrl, 'GET', null, null);
            $httpRequest = Google_Client::$io->authenticatedRequest($request);
            if ($httpRequest->getResponseHttpCode() == 200) {
                echo $httpRequest->getResponseBody();
            } else {
                // An error occurred.
                return false;
            }
        } else {
            // The file doesn't have any content stored on Drive.
            return false;
        }
    }
}