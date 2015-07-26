<?php

namespace Backup\Storage;

require_once 'Phar/aws.phar';

use Aws\Glacier\GlacierClient;
use Aws\Common\Enum\Region;
use Guzzle\Http\EntityBody;

class Amazon
{

    private $glacier = null;

    public function __construct($key, $secret, $region = Region::US_EAST_1)
    {
        $this->glacier = GlacierClient::factory(array(
            'key'    => $key,
            'secret' => $secret,
            'region' => $region,
            'ssl.certificate_authority' => false
        ));
    }

    private function createVault($vaultName)
    {
        $this->glacier->createVault(array(
            'vaultName' => $vaultName
        ));
    }

    private function vaultExist($vaultName)
    {
        $vaults = $this->glacier->listVaults()->get('VaultList');
        foreach ($vaults as $vault) {
            if ($vault['VaultName'] == $vaultName) {
                return true;
            }
        }
        return false;
    }

    public function save($vaultName, $file, $description = null)
    {

        if (!$this->vaultExist($vaultName)) {
            $this->createVault($vaultName);
        }

        if (!is_file($file)) {
            throw new \Exception('File not exist');
        }

        $options = array(
            'vaultName'          => $vaultName,
            'body'               => EntityBody::factory(fopen($file, 'r'))
        );

        /*if (filesize($file) > 1024000) {

            $multiupload = $this->glacier->initiateMultipartUpload(array(
                'vaultName' => $vaultName,
                'archiveDescription' => $description,
                'partSize' => '4194304'
            ));

            $options['uploadId'] = $multiupload->get('uploadId');
            $options['range'] = filesize($file);
            $this->glacier->uploadMultipartPart($options);

            $result = $this->glacier->completeMultipartUpload(array(
                'vaultName' => $vaultName,
                'uploadId' => $multiupload->get('uploadId'),
            ));

        } else {*/

            if ($description) {
                $options['archiveDescription'] = $description;
            }
            $result = $this->glacier->uploadArchive($options);
        //}

        $archiveId = $result->get('archiveId');
        return $archiveId ? true : false;
    }

}