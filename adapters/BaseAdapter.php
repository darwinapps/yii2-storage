<?php

namespace darwinapps\storage\adapters;

use yii\base\NotSupportedException;

class BaseAdapter extends \yii\base\Component implements \darwinapps\storage\interfaces\StorageInterface
{
    /**
     * Generates specified number of random bytes.
     * Note that output may not be ASCII.
     * @see generateRandomString() if you need a string.
     *
     * @param integer $length the number of bytes to generate
     * @throws Exception on failure.
     * @return string the generated random bytes
     */
    public function generateRandomKey($length = 32)
    {
        if (!extension_loaded('mcrypt')) {
            throw new InvalidConfigException('The mcrypt PHP extension is not installed.');
        }
        $bytes = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
        if ($bytes === false) {
            throw new Exception('Unable to generate random bytes.');
        }
        return $bytes;
    }

    /**
     * Generates a random string of specified length.
     * The string generated matches [A-Za-z0-9_-]+ and is transparent to URL-encoding.
     *
     * @param integer $length the length of the key in characters
     * @throws Exception Exception on failure.
     * @return string the generated random key
     */
    public function generateRandomString($length = 32)
    {
        $bytes = $this->generateRandomKey($length);
        // '=' character(s) returned by base64_encode() are always discarded because
        // they are guaranteed to be after position $length in the base64_encode() output.
        return strtr(substr(base64_encode($bytes), 0, $length), '+/', '_-');
    }

    public function generate_unique_id()
    {
        $unique_id = 'ad';
        $i = 100;
        while (--$i >= 0 && strpos($unique_id, 'ad') === 0) {
            $unique_id = $this->generateRandomString();
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
    public function download($id, $filename)
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