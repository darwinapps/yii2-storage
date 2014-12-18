<?php

namespace darwinapps\storage\adapters;

use Yii;
use yii\helpers\FileHelper;
use yii\base\InvalidConfigException;

use darwinapps\storage\models\File;

class GoogleDrive extends BaseAdapter
{

    static $SCOPE = ['https://www.googleapis.com/auth/drive'];

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
     * @param $path
     * @param null|\Google_Service_Drive_DriveFile $parent
     * @return \Google_Service_Drive_DriveFile
     */
    public function createDirectoryRecursive($path, $parent = null)
    {
        foreach (explode("/", $path) as $name) {
            $directory = $this->createDirectory($name, $parent);
            $parent = $directory;
        }
        return $directory;
    }

    /**
     * @param $name
     * @param null|\Google_Service_Drive_DriveFile $parent
     * @return \Google_Service_Drive_DriveFile
     */
    public function createDirectory($name, $parent = null)
    {
        $files = $this->getService()->files->listFiles([
            'q' => "title = '$name' and mimeType = 'application/vnd.google-apps.folder'"
        ]);

        if (!$files->count()) {

            $driveFolder = new \Google_Service_Drive_DriveFile([
                'title' => $name,
                'mimeType' => 'application/vnd.google-apps.folder'
            ]);

            if ($parent) {
                $driveFolder->setParents([$parent]);
            }

            return $this->getService()->files->insert($driveFolder);
        } else {
            return $files->current();
        }
    }

    /**
     * @inheritdoc
     */
    public function move($id, $dir)
    {
        if ($parent = $this->createDirectoryRecursive($dir)) {
            foreach ($this->getService()->parents->listParents($id) as $parent) {
                $this->getService()->parents->delete($id, $parent->id);
            }
            if ($this->getService()->parents->insert($id, $parent)) {
                return $id;
            }
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function put(\yii\web\UploadedFile $file, $path = null)
    {
        $parent = null;

        if ($path != null)
            $parent = $this->createDirectoryRecursive($path);

        $mimeType = FileHelper::getMimeTypeByExtension($file->name) ? : $file->type;
        $driveFile = new \Google_Service_Drive_DriveFile([
            'title' => $file->name,
            'mimeType' => $mimeType
        ]);

        if ($parent) {
            $driveFile->setParents([$parent]);
        }

        $result = $this->getService()->files->insert($driveFile, [
            'data' => file_get_contents($file->tempName),
            'uploadType' => 'media',
        ]);

        if ($response = $this->convert($result->id, 'text/plain')) {
            $has_preview = true;
            $text = preg_replace("/[_\W]+/", " ", $response->getResponseBody());
        } else {
            $has_preview = false;
            $text = '';
        }

        return new File([
            'id' => $result->id,
            'name' => $file->name,
            'size' => $file->size,
            'type' => $file->type,
            'has_preview' => $has_preview,
            'text' => $text,
        ]);
    }

    public function download($id)
    {
        if (($file = $this->get($id)) && ($downloadUrl = $file->getDownloadUrl())) {
            if ($response = $this->fetch($downloadUrl)) {
                header('Content-Disposition:' . $response->getResponseHeader('content-disposition'));
                header('Content-Type:' . $response->getResponseHeader('content-type'));
                echo $response->getResponseBody();
                return true;
            }
        }

        return false;
    }

    public function preview($id, $type = 'application/pdf')
    {
        if (($file = $this->get($id))) {
            // returning file itself if matches content-type, no conversion needed
            // returning file itself if image
            if (($file->mimeType == 'application/pdf' || preg_match('/^image/', $file->mimeType))) {
                return $this->download($id);
            } elseif ($response = $this->convert($id, $type)) {
                header('Content-Disposition:' . $response->getResponseHeader('content-disposition'));
                header('Content-Type:' . $response->getResponseHeader('content-type'));
                echo $response->getResponseBody();
                return true;
            }
        }
        return false;
    }

    /**
     * @param $url
     * @return \Google_Http_Request
     */
    public function fetch($url)
    {
        $auth = $this->getService()->getClient()->getAuth();
        $response = $auth->authenticatedRequest(new \Google_Http_Request($url, 'GET', null, null));
        if ($response->getResponseHttpCode() == 200) {
            return $response;
        }
    }

    public function get($id)
    {
        try {
            return $this->getService()->files->get($id);
        } catch (\Exception $e) {
        }
    }

    /**
     * @param string $id
     * @param string $type
     * @return \Google_Http_Request|null
     */
    public function convert($id, $type = 'application/pdf')
    {
        if ($file = $this->get($id)) {

            // images never gets converted
            if (($type == $file->mimeType) || preg_match('/^image/', $file->mimeType))
                return;

            // trying to convert
            try {
                $preview = $this->getService()->properties->get($id, 'preview');
            } catch (\Exception $e) {
            }

            if (
                (isset($preview)) &&
                ($file = $this->get($preview->value)) &&
                ($exportLinks = $file->getExportLinks()) &&
                ($exportLinks[$type])
            ) {
                return $this->fetch($exportLinks[$type]);
            } else {
                $target = new \Google_Service_Drive_DriveFile([
                    'parents' => [$this->createDirectoryRecursive('previews')]
                ]);
                $file = $this->getService()->files->copy($id, $target, ['convert' => true]);
                if (($exportLinks = $file->getExportLinks()) && $exportLinks[$type]) {
                    $preview = new \Google_Service_Drive_Property([
                        'key' => 'preview',
                        'value' => $file->id
                    ]);
                    $this->getService()->properties->insert($id, $preview);
                    return $this->fetch($exportLinks[$type]);
                } else {
                    $this->getService()->files->delete($file->id);
                }
            }
        }
        return;
    }
}