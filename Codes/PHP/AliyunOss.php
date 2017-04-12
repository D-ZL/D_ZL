<?php
use OSS\OssClient;
use OSS\Core\OssException;

/**
 * Class AliyunOss
 */
class AliyunOss
{
    public $accessKeyId;
    public $accessKeySecret;
    public $endpoint;
    public $bucket;
    public $ossClient;

    public function __construct($configs)
    {
        $this->accessKeyId     = $configs['OSS_ACCESS_ID'];
        $this->accessKeySecret = $configs['OSS_ACCESS_KEY'];
        $this->endpoint        = $configs['OSS_ENDPOINT'];
        $this->bucket          = $configs['OSS_BUCKET'];
        $this->dir             = $configs['OSS_DIR'];

        try {
            $this->ossClient = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint, false);
        } catch (OssException $e) {
            printf(__FUNCTION__ . "creating OssClient instance: FAILED\n");
            printf($e->getMessage() . "\n");
            exit;
        }
    }

    /**
     * ��ȡ Object Meta
     *
     * @return array
     */
    public function getObjectMeta($object)
    {
        try {
            $objectMeat = $this->ossClient->getObjectMeta($this->bucket, $object);
        } catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return false;
        }
        return $objectMeat;
    }
    

    /**
     * �ƶ� Object
     *
     * @return bool
     */
    public function moveObject($fromObject, $toObject)
    {
        if ($this->doesObjectExist($fromObject)) {
            $objectSize = $this->getObjectMeta($fromObject);
            $objectSize = $objectSize['content-length'];
            if ($objectSize < 1000000000) {
                if ($this->copyObject($fromObject, $toObject)) {
                    // return $this->delObject($fromObject);
                    return true;
                } else {
                    return false;
                }
            } else {
                if ($this->copysObject($fromObject, $toObject, $objectSize)) {
                    // return $this->delObject($fromObject);
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    }
    

    /**
     * ���� Object
     *
     * @return bool
     */
    public function copyObject($fromObject, $toObject)
    {
        try {
            $this->ossClient->copyObject($this->bucket, $fromObject, $this->bucket, $toObject);
        } catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return false;
        }
        return true;
    }

    /**
     * ���ƴ� Object
     *
     * @return bool
     */
    public function copysObject($fromObject, $toObject, $objectSize)
    {
        try {
            $uploadId = $this->ossClient->initiateMultipartUpload($this->bucket, $toObject);
            $pieces = $this->ossClient->generateMultiuploadParts($objectSize);
            $uploadPosition = 0;
            foreach ($pieces as $i => $piece) {
                $copyId = $i + 1;
                $options = array(
                    'start' => $piece['seekTo'],
                    'end'   => $piece['seekTo'] + $piece['length'] - 1
                );
                $eTag = $this->ossClient->uploadPartCopy($this->bucket, $fromObject, $this->bucket, $toObject, $copyId, $uploadId, $options);
                $uploadParts[] = array(
                    'PartNumber' => $copyId,
                    'ETag'       => $eTag
                );
            }
            $this->ossClient->completeMultipartUpload($this->bucket, $toObject, $uploadId, $uploadParts);
        } catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return false;
        }
        return true;
    }
    
    

    /**
     * ɾ�� Object
     *
     * @return bool
     */
    public function delObject($object)
    {
        $object = substr($object, strpos($object, $this->dir));
        try {
            if ($this->doesObjectExist($object)) {
                $this->ossClient->deleteObject($this->bucket, $object);
            } else {
                return false;
            }
        } catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return false;
        }
        return true;
    }

    /**
     * �ж� Object �Ƿ����
     *
     * @return bool
     */
    public function doesObjectExist($object)
    {
        try {
            $exist = $this->ossClient->doesObjectExist($this->bucket, $object);
        } catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return false;
        }
        return $exist;
    }
    
}
