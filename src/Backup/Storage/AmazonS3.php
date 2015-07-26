<?php

namespace Backup\Storage;

require_once 'Phar/aws.phar';

class AmazonS3
{

    public function __construct()
    {
    }

    private function createVault($vaultName)
    {
        system("s3cmd mb s3://{$vaultName}", $retVal);
        return $retVal == 0 ? true : false;

    }

    public function save($vaultName, $file, $description = null)
    {
        $this->createVault($vaultName);

        if (!is_file($file)) {
            throw new \Exception('File not exist');
        }

        system("s3cmd --progress put {$file} s3://{$vaultName}", $retVal);
        return $retVal == 0 ? true : false;
    }

    public function delete($vaultName, $file)
    {
        system("s3cmd del s3://{$vaultName}/{$file}", $retVal);
        return $retVal == 0 ? true : false;
    }

}