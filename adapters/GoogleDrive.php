<?php

namespace darwinapps\storage\adapters;

use Yii;
use yii\helpers\FileHelper;
use yii\base\InvalidConfigException;

class GoogleDrive extends BaseAdapter
{

    static $SCOPE = ['https://www.googleapis.com/auth/drive'];
    static $CONVERT_MAP = [
        'doc' => 'docx',
        'xls' => 'xlsx',
        'ods' => 'xlsx',
    ];

    public $serviceAccountEmail;
    public $serviceAccountPKCS12FilePath;
    public $userEmail;
    public $serviceDomain;

    private $_service;

    public function init()
    {
        if (!$this->serviceAccountPKCS12FilePath)
            throw new InvalidConfigException("Google service key path not set");

        if (!$this->serviceAccountEmail)
            throw new InvalidConfigException("Google service email not set");

        $keyPath = Yii::getAlias($this->serviceAccountPKCS12FilePath);

        if (!file_exists($keyPath))
            throw new InvalidConfigException("Google key not found");

    }

    public function buildService()
    {
        $keyPath = Yii::getAlias($this->serviceAccountPKCS12FilePath);

        if (!file_exists($keyPath))
            throw new InvalidConfigException("Google key not found");

        $key = file_get_contents($keyPath);

        $auth = new \Google_Auth_AssertionCredentials(
            $this->serviceAccountEmail,
            static::$SCOPE,
            $key);

        $client = new \Google_Client();

        if ($this->userEmail)
            $auth->sub = $this->userEmail;

        $client->setAssertionCredentials($auth);

        if ($client->getAuth()->isAccessTokenExpired()) {
            $client->getAuth()->refreshTokenWithAssertion();
        }

        return new \Google_Service_Drive($client);
    }

    /**
     * @return \Google_Service_Drive
     */
    public function getService()
    {
        if (!$this->_service) {
            $this->_service = $this->buildService();
        }
        return $this->_service;
    }

    /**
     * @param \yii\web\UploadedFile $file uploaded file
     * @return string $path
     */
    public function put(\yii\web\UploadedFile $file)
    {
        $driveFile = new \Google_Service_Drive_DriveFile([
            'title' => $file->name
        ]);

        $result = $this->getService()->files->insert($driveFile, [
            'data' => file_get_contents($file->tempName),
            'uploadType' => 'media',
            'mimeType' => FileHelper::getMimeTypeByExtension($file->name) ? : $file->type
        ]);

        return $result->id;
    }

    public function getText($fileId)
    {
        $result = $this->getService()->files->copy($fileId, new \Google_Service_Drive_DriveFile(), ['convert' => true]);
        $exportLinks = $result->getExportLinks();
        if ($exportLinks['text/plain']) {
            $request = new \Google_Http_Request($exportLinks['text/plain'], 'GET', null, null);
            $httpRequest = $this->getService()->getClient()->getAuth()->authenticatedRequest($request);
            if ($request->getResponseHttpCode() == 200) {
                $this->getService()->files->delete($result['id']);
                return $httpRequest->getResponseBody();
            }
        }
        $this->getService()->files->delete($result->id);
    }

    public function createFolder($name, $parentId)
    {
        $files = $this->getService()->files->listFiles([
            'q' => "title = '$name' and mimeType = 'application/vnd.google-apps.folder'"
        ]);

        if (!$files->count()) {
            $driveFolder = new \Google_Service_Drive_DriveFile([
                'title' => $name,
                'mimeType' => 'application/vnd.google-apps.folder'
            ]);
            if ($parentId) {
                $driveFolder->setParents([
                    new \Google_Service_Drive_ParentReference(['id' => $parentId])
                ]);
            }
            $folder = $this->getService()->files->insert($driveFolder);
            return $folder->id;
        } else {
            return $files->current()->id;
        }
    }

    public function move($fileId, $dir)
    {
        $parentId = null;
        foreach (explode("/", $dir) as $name) {
            $folderId = $this->createFolder($name, $parentId);
            $parentId = $folderId;
        }
        $this->getService()->parents->insert($fileId, new \Google_Service_Drive_ParentReference(['id' => $parentId]));
    }

    public function download($path, $filename)
    {
        $file = $this->getService()->files->get($path);
        if ($downloadUrl = $file->getDownloadUrl()) {
            $request = new \Google_Http_Request($downloadUrl, 'GET', null, null);;
            $httpRequest = $this->getService()->getClient()->getAuth()->authenticatedRequest($request);
            if ($request->getResponseHttpCode() == 200) {
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Type:' . $request->getResponseHeader('Content-Type'));
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