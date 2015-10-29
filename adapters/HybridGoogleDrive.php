<?php

namespace darwinapps\storage\adapters;

use Yii;
use yii\helpers\FileHelper;
use yii\base\InvalidConfigException;
use Tebru\Executioner\Executor;
use Tebru\Executioner\Subscriber\WaitSubscriber;
use Tebru\Executioner\Strategy\StaticWaitStrategy;
use darwinapps\storage\adapters\hybrid\MetaData;
use darwinapps\storage\models\File;

class HybridGoogleDrive extends BaseAdapter
{

    static $SCOPE = ['https://www.googleapis.com/auth/drive'];

    public $serviceAccountEmail;
    public $serviceAccountPKCS12FilePath;
    public $userEmail;
    public $serviceDomain;
    public $retries = 3;

    private $_service;
    private $_executor;

    public $uploadPath = '@runtime/hybrid';
    public $mode = 0775;

    public function init()
    {
        if (!$this->serviceAccountPKCS12FilePath)
            throw new InvalidConfigException("Google service key path not set");

        if (!$this->serviceAccountEmail)
            throw new InvalidConfigException("Google service email not set");

        $keyPath = Yii::getAlias($this->serviceAccountPKCS12FilePath);

        if (!file_exists($keyPath))
            throw new InvalidConfigException("Google key not found");

        $waitStrategy = new StaticWaitStrategy();
        $this->_executor = new Executor();
        $this->_executor->addSubscriber(new WaitSubscriber($waitStrategy));
    }

    protected function getRealPath($path)
    {
        return Yii::getAlias($this->uploadPath) . DIRECTORY_SEPARATOR . $path;
    }

    protected function getPreviewFilePath($id)
    {
        return $this->getRealPath('preview' . DIRECTORY_SEPARATOR . substr($id, -3, 3) . DIRECTORY_SEPARATOR . $id);
    }

    protected function getOriginFilePath($id)
    {
        return $this->getRealPath('origin' . DIRECTORY_SEPARATOR . substr($id, -3, 3) . DIRECTORY_SEPARATOR . $id);
    }

    protected function saveHybrid($id = false, $gd_id = false, $type = 'preview', $file_type = '', $file_name = '', $file_content = '', $file_time = 0)
    {
        if ($id && $type && $file_type && $file_name && $file_content) {
            if (!in_array($type, ['preview', 'origin'])) {
                return false;
            }

            $metadata = $this->loadMetaData($id) ?: new MetaData();

            $metadata->setAttributes([
                'id' => $id,
                $type . '_name' => $file_name,
                $type . '_type' => $file_type,
                $type . '_time' => $file_time,
            ], false);

            if ($gd_id) {
                $metadata->setAttributes([
                    'gd_id' => $gd_id,
                ], false);
            }

            $this->saveMetaData($id, $metadata);

            $file = ($type == 'preview') ? $this->getPreviewFilePath($id) : $this->getOriginFilePath($id);

            if (!file_exists(dirname($file))) FileHelper::createDirectory(dirname($file), $this->mode);

            return file_put_contents($file, $file_content) !== false;
        }
        return false;
    }

    protected function getMetaDataFilePath($id)
    {
        return $this->getRealPath('metadata' . DIRECTORY_SEPARATOR . substr($id, -3, 3) . DIRECTORY_SEPARATOR . $id);
    }

    protected function saveMetaData($id, MetaData $metadata)
    {
        $file = $this->getMetaDataFilePath($id);
        if (!file_exists(dirname($file)))
            FileHelper::createDirectory(dirname($file), $this->mode);
        return file_put_contents($file, serialize($metadata->toArray())) !== false;
    }

    protected function loadMetaData($id)
    {
        $file = $this->getMetaDataFilePath($id);
        if (file_exists($file)) {
            $metadata = unserialize(file_get_contents($file));

            return new MetaData($metadata);
        }
        return false;
    }

    public function execute($fn)
    {
        return $this->_executor->execute($this->retries, $fn);
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

        $this->execute(function () use ($client) {
            if ($client->getAuth()->isAccessTokenExpired()) {
                $client->getAuth()->refreshTokenWithAssertion();
            }
        });

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
        $files = $this->execute(function () use ($name) {
            return $this->getService()->files->listFiles([
                'q' => "title = '$name' and mimeType = 'application/vnd.google-apps.folder'"
            ]);
        });

        if (!$files->count()) {

            $driveFolder = new \Google_Service_Drive_DriveFile([
                'title' => $name,
                'mimeType' => 'application/vnd.google-apps.folder'
            ]);

            if ($parent) {
                $driveFolder->setParents([$parent]);
            }

            return $this->execute(function () use ($driveFolder) {
                return $this->getService()->files->insert($driveFolder);
            });
        } else {
            return $files->current();
        }
    }

    /**
     * @inheritdoc
     */
    public function move($id, $dir)
    {
        if ($metadata = $this->loadMetaData($id)) {
            if ($file = $this->getOriginFilePath($id)) {
                if (file_exists($file)) {
                    return $id;
                }
            }
            if ($metadata->gd_id) {
                $id = $metadata->gd_id;
            }
        }

        if (($file = $this->google_files_get($id)) && ($parent = $this->createDirectoryRecursive($dir))) {
            $file->setParents([$parent]);
            $file = $this->execute(function () use ($id, $file) {
                return $this->getService()->files->update($id, $file);
            });
            return $file->id;
        }
        return false;
    }


    /**
     * @inheritdoc
     */
    public function put(\yii\web\UploadedFile $file, $path = null)
    {
        $id = $this->generate_unique_id();
        $hb = $this->saveHybrid($id, false, 'origin', $file->type, $file->name, file_get_contents($file->tempName), time());
        if ($hb) {
            $metadata = $this->loadMetaData($id);
            $metadata->setAttributes([
                'gd_id' => false,
            ], false);
            $this->saveMetaData($id, $metadata);

            return new File([
                'id' => $id,
                'name' => $file->name,
                'size' => $file->size,
                'type' => $file->type,
                'has_preview' => false,
                'text' => '',
            ]);
        }
        return false;
    }

    public function sync($id) {
        if ($metadata = $this->loadMetaData($id)) {
            if ($file = $this->getOriginFilePath($id)) {
                if (file_exists($file)) {
                    if (!$metadata->gd_id) {
                        $content = file_get_contents($file);
                        $result = $this->_put($metadata->origin_name, $metadata->origin_type, $content);
                        $gd_id = $result->id;
                        $metadata->gd_id = $gd_id;
                        $this->saveMetaData($id, $metadata);

                        if ($response = $this->convert($result->id, 'text/plain')) {
                            $has_preview = true;
                            $text = preg_replace("/[_\W]+/", " ", $response->getResponseBody());
                        } else {
                            $has_preview = false;
                            $text = '';
                        }

                        $result_file = new File([
                            'id' => $id,
                            'name' => $metadata->origin_name,
                            'size' => strlen($content),
                            'type' => $metadata->origin_type,
                            'has_preview' => $has_preview,
                            'text' => $text,
                        ]);


                        if ($gd_id) {
                            if (($file = $this->google_files_get($gd_id))) {
                                $time = $file->getModifiedDate() ?: $file->getCreatedDate();
                                $name = $id;
                                if (($file->mimeType == 'application/pdf' || preg_match('/^image/', $file->mimeType))) {
                                    if ($downloadUrl = $file->getDownloadUrl()) {
                                        if ($response = $this->fetch($downloadUrl)) {
                                            if ($file_name = $response->getResponseHeader('content-disposition')) {
                                                if (preg_match('/filename="(.*)"/', $file_name, $matches)) {
                                                    $name = $matches[1];
                                                }
                                            }
                                            $result_file->has_preview = $this->saveHybrid($id, $gd_id, 'preview', $response->getResponseHeader('content-type'), $name, $response->getResponseBody(), $time);
                                        }
                                    }
                                } elseif ($response = $this->convert($gd_id, 'application/pdf')) {

                                    if ($file_name = $response->getResponseHeader('content-disposition')) {
                                        if (preg_match('/filename="(.*)"/', $file_name, $matches)) {
                                            $name = $matches[1];
                                        }
                                    }
                                    $type = $response->getResponseHeader('content-type');
                                    $content = $response->getResponseBody();
                                    $result_file->has_preview = $this->saveHybrid($id, $gd_id, 'preview', $type, $name, $content, $time);
                                }
                            }
                        }

                        return $result_file;
                    }
                }
            }
        }
        return false;
    }

    private function _put($name, $type, $content, $path = null)
    {
        $parent = null;
        if ($path != null) $parent = $this->createDirectoryRecursive($path);

        $mimeType = FileHelper::getMimeTypeByExtension($name) ? : $type;
        $driveFile = new \Google_Service_Drive_DriveFile([
            'title' => $name,
            'mimeType' => $mimeType
        ]);

        if ($parent) $driveFile->setParents([$parent]);

        $result = $this->execute(function () use ($content, $driveFile) {
            return $this->getService()->files->insert($driveFile, [
                'data' => $content,
                'uploadType' => 'media',
            ]);
        });

        return $result;
    }

    private function file_out($file_path = false, $file_name = false, $file_type = false, $headers = []) {
        // we need to implement getModifiedDate from drive and if it changed refresh it
        // but for this we must call file = $this->google_files_get($id) - is it ok for speed ?

        if ($file_path &&
            file_exists($file_path) &&
            $file_name &&
            $file_type) {

            header('Content-Disposition: attachment; filename="' . $file_name . '"');
            header("Content-Type: " . $file_type);
            if (isset($headers) && is_array($headers) && !empty($headers)) {
                foreach ($headers as $header) {
                    header($header);
                }
            }

            if ($stream = fopen($file_path, 'r')) {
                while (!feof($stream)) {
                    echo fread($stream, 8192);
                }

                return fclose($stream);
            }
            return false;
        }
        return false;
    }

    public function download($id, $headers = [])
    {
        $gd_id = false;
        if ($metadata = $this->loadMetaData($id)) {
            if ($file = $this->getOriginFilePath($id)) {

                if (file_exists($file) && !$metadata->gd_id) {
                    $result = $this->_put($metadata->origin_name, $metadata->origin_type, file_get_contents($file));
                    $gd_id = $result->id;

                    $metadata->gd_id = $gd_id;
                    $this->saveMetaData($id, $metadata);
                }

                if ($this->file_out($file, $metadata->origin_name, $metadata->origin_type, $headers)) {
                    return true;
                }
            }
            if ($metadata->gd_id) {
                $gd_id = $metadata->gd_id;
            }
        }
        if (!$gd_id) {
            $gd_id = $id;
        }

        if (($file = $this->google_files_get($gd_id)) && ($downloadUrl = $file->getDownloadUrl())) {
            if ($response = $this->fetch($downloadUrl)) {
                $type = $response->getResponseHeader('content-type');
                $content = $response->getResponseBody();
                $time = $file->getModifiedDate() ?: $file->getCreatedDate();
                $name = $id;
                if ($file_name = $response->getResponseHeader('content-disposition')) {
                    if (preg_match('/filename="(.*)"/', $file_name, $matches)) {
                        $name = $matches[1];
                    }
                }
                $this->saveHybrid($id, $gd_id, 'origin', $type, $name, $content, $time);

                header('Content-Disposition:' . $response->getResponseHeader('content-disposition'));
                header('Content-Type:' . $type);
                if (isset($headers) && is_array($headers) && !empty($headers)) {
                    foreach ($headers as $header) {
                        header($header);
                    }
                }

                echo $content;
                return true;
            }
        }

        return false;
    }

    public function get($id)
    {
        $gd_id = false;
        if (($metadata = $this->loadMetaData($id)) && ($file = $this->getOriginFilePath($id))) {
            if (file_exists($file))  {
                if ($file_content = file_get_contents($file)) {
                    return $file_content;
                }
            }
            if ($metadata->gd_id) {
                $gd_id = $metadata->gd_id;
            }
        }
        if (!$gd_id) {
            $gd_id = $id;
        }

        if (($file = $this->google_files_get($gd_id)) && ($downloadUrl = $file->getDownloadUrl())) {
            if ($response = $this->fetch($downloadUrl)) {

                $time = $file->getModifiedDate() ?: $file->getCreatedDate();
                $type = $response->getResponseHeader('content-type');
                $content = $response->getResponseBody();
                $name = $id;
                if ($file_name = $response->getResponseHeader('content-disposition')) {
                    if (preg_match('/filename="(.*)"/', $file_name, $matches)) {
                        $name = $matches[1];
                    }
                }
                $this->saveHybrid($id, $gd_id, 'origin', $type, $name, $content, $time);

                return $content;
            }
        }

        return false;
    }

    public function preview($id, $type = 'application/pdf', $headers = [])
    {
        $gd_id = false;
        if ($metadata = $this->loadMetaData($id)) {
            if ($file = $this->getPreviewFilePath($id)) {
                if ($this->file_out($file, $metadata->preview_name, $metadata->preview_type, $headers)) {
                    return true;
                }
            }

            if ($metadata->gd_id) {
                $gd_id = $metadata->gd_id;
            } else {
                if ($file = $this->getOriginFilePath($id)) {
                    if (file_exists($file)) {
                        $result = $this->_put($metadata->origin_name, $metadata->origin_type, file_get_contents($file));
                        $gd_id = $result->id;

                    }
                }
            }
        }
        // if no preview & no origin then seems we have gd_id in id
        if (!$gd_id) {
            $gd_id = $id;
        }

        if (($file = $this->google_files_get($gd_id))) {
            $time = $file->getModifiedDate() ?: $file->getCreatedDate();
            $name = $id;
            // returning file itself if matches content-type, no conversion needed
            // returning file itself if image
            if (($file->mimeType == 'application/pdf' || preg_match('/^image/', $file->mimeType))) {
                if ($downloadUrl = $file->getDownloadUrl()) {
                    if ($response = $this->fetch($downloadUrl)) {
                        if ($file_name = $response->getResponseHeader('content-disposition')) {
                            if (preg_match('/filename="(.*)"/', $file_name, $matches)) {
                                $name = $matches[1];
                            }
                        }
                        $this->saveHybrid($id, $gd_id, 'preview', $response->getResponseHeader('content-type'), $name, $response->getResponseBody(), $time);
                    }
                }

                return $this->download($id, $headers);

            } elseif ($response = $this->convert($gd_id, $type)) {

                if ($file_name = $response->getResponseHeader('content-disposition')) {
                    if (preg_match('/filename="(.*)"/', $file_name, $matches)) {
                        $name = $matches[1];
                    }
                }
                $type = $response->getResponseHeader('content-type');
                $content = $response->getResponseBody();
                $this->saveHybrid($id, $gd_id, 'preview', $type, $name, $content, $time);

                header('Content-Disposition:' . $response->getResponseHeader('content-disposition'));
                header('Content-Type:' . $type);
                if (isset($headers) && is_array($headers) && !empty($headers)) {
                    foreach ($headers as $header) {
                        header($header);
                    }
                }
                echo $content;
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
        return $this->execute(function () use ($url) {
            $auth = $this->getService()->getClient()->getAuth();
            $response = $auth->authenticatedRequest(new \Google_Http_Request($url, 'GET', null, null));
            if ($response->getResponseHttpCode() == 200) {
                return $response;
            }
        });
    }

    /**
     * @param $id
     * @return \Google_Service_Drive_DriveFile
     */
    public function google_files_get($id)
    {
        return $this->execute(function () use ($id) {
            return $this->getService()->files->get($id);
        });
    }

    /**
     * @param string $id
     * @param string $type
     * @return \Google_Http_Request|null
     */
    public function convert($id, $type = 'application/pdf')
    {
        if ($file = $this->google_files_get($id)) {

            // images never gets converted
            if (($type == $file->mimeType) || preg_match('/^image/', $file->mimeType))
                return false;

            // trying to convert
            try {
                $preview = $this->getService()->properties->get($id, 'preview');
            } catch (\Exception $e) {
            }

            if (
                (isset($preview)) &&
                ($file = $this->google_files_get($preview->value)) &&
                ($exportLinks = $file->getExportLinks()) &&
                ($exportLinks[$type])
            ) {
                return $this->fetch($exportLinks[$type]);
            } else {
                $target = new \Google_Service_Drive_DriveFile([
                    'parents' => [$this->createDirectoryRecursive('previews')]
                ]);
                $file = $this->execute(function () use ($id, $target) {
                    return $this->getService()->files->copy($id, $target, ['convert' => true]);
                });
                if ($file && ($exportLinks = $file->getExportLinks()) && $exportLinks[$type]) {
                    $preview = new \Google_Service_Drive_Property([
                        'key' => 'preview',
                        'value' => $file->id
                    ]);
                    $this->execute(function () use ($id, $preview) {
                        $this->getService()->properties->insert($id, $preview);
                    });
                    return $this->fetch($exportLinks[$type]);
                } else {
                    $this->execute(function () use ($file) {
                        $this->getService()->files->delete($file->id);
                    });
                }
            }
        }
        return false;
    }
}