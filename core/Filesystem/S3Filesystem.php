<?php

namespace Ryra\Core\Filesystem;

use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use Aws\S3\S3Client;

class S3Filesystem extends Filesystem implements FilesystemInterface
{
    public function __construct(array $configs)
    {
        $client = new S3Client([
            'version' => 'latest',
            'region' => $configs['region'],
            'endpoint' => $configs['endpoint'] ?? null,
            'use_path_style_endpoint' => $configs['use_path_style_endpoint'] ?? false,
            'credentials' => [
                'key' => $configs['key'],
                'secret' => $configs['secret'],
            ],
        ]);
        $adapter = new AwsS3V3Adapter($client, $configs['bucket']);
        parent::__construct($adapter, ['public_url' => $configs['url']]);
    }
}