<?php

namespace App\Services\Box;

use App\Traits\YieldTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class BoxService
{
    use YieldTrait;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @var Client
     */
    private $client;

    /**
     * @param string $baseUrl
     * @param string $secretKey
     */
    public function __construct($baseUrl, $secretKey)
    {
        $this->baseUrl = $baseUrl;
        $this->secretKey = $secretKey;

        $this->client = new Client([
            "verify" => false,
            "http_errors" => false
        ]);
    }

    /**
     * @param $storageId
     * @param $name
     * @return array
     */
    public function init($storageId, $name)
    {
        $res = $this->client->post(
            $this->baseUrl . '/' . $storageId . '/init',
            [
                'headers' => [
                    'Authorization' => $this->secretKey,
                ],
                "json" => [
                    "instanceName" => $name
                ]
            ]
        );

        return [
            $res->getStatusCode() === Response::HTTP_CREATED ? true : false,
            $res->getBody()->getContents()
        ];
    }

    /**
     * @param $storageId
     * @param $folderId
     * @param array $files
     * @param callable $fileNameCallback
     * @return \GuzzleHttp\Promise\PromiseInterface
     * @throws \Exception
     */
    public function uploadFiles($storageId, $folderId, array $files,  callable $fileNameCallback)
    {
        $output = [];

        $index = 0;

        /** @var UploadedFile $file */
        foreach ($this->yieldCollection($files) as $file) {
            $content = fopen($file->getPathname(), 'r');

            if ($content === false) {
                continue;
            }

            $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);

            $output[] = [
                'name'     => 'docs',
                'contents' => $content,
                'filename' => $fileNameCallback($index, $ext)
            ];

            $index++;
        }

        if (empty($output)) {
            throw new \Exception("Output data for stream are empty");
        }

        $request = new Request(
            'POST',
            $this->baseUrl . '/' . $storageId . '/files/' . $folderId,
            [
                'Authorization' => $this->secretKey,
                'Content-Disposition' => 'multipart/form-data'
            ],
            new MultipartStream($output)
        );


        return $this->client->sendAsync($request);
    }

    /**
     * @param $storageId
     * @param $folderName
     * @param $parentFolderId
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function createFolder($storageId, $folderName, $parentFolderId)
    {
        $request = new Request(
            'POST',
            $this->baseUrl . '/' . $storageId . '/folder',
            [
                'Authorization' => $this->secretKey,
                'Content-Type' => 'application/json'
            ],
            json_encode([
                'folderName' => $folderName,
                'parentFolderId' => $parentFolderId
            ])
        );

        return $this->client->sendAsync($request);
    }

    /**
     * @param $storageId
     * @param array $foldersName
     * @param $parentFolderId
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function createFolders($storageId, array $foldersName, $parentFolderId)
    {
        $request = new Request(
            'POST',
            $this->baseUrl . '/' . $storageId . '/folders',
            [
                'Authorization' => $this->secretKey,
                'Content-Type' => 'application/json'
            ],
            json_encode([
                'foldersName' => $foldersName,
                'parentFolderId' => $parentFolderId
            ])
        );

        return $this->client->sendAsync($request);
    }

    /**
     * @param $path
     * @param $name
     * @param $storageId
     * @param $folderId
     * @return bool|\GuzzleHttp\Promise\PromiseInterface
     */
    public function uploadFileByPath($path, $name, $storageId, $folderId)
    {
        $resource = fopen($path, 'r');

        if ($resource === false) {
            return false;
        }

        $request = new Request(
            'POST',
            $this->baseUrl . '/' . $storageId . '/file/' . $folderId,
            [
                'Authorization' => $this->secretKey,
                'Content-Disposition' => 'multipart/form-data'
            ],
            new MultipartStream([
                [
                    'name'     => 'doc',
                    'contents' => $resource,
                    'filename' => $name
                ]
            ])
        );


        return $this->client->sendAsync($request);
    }

    /**
     * @param $fileId
     * @param $folderId
     * @param $storageId
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function copyFile($fileId, $folderId, $storageId)
    {
        return $this->client->postAsync(
            $this->baseUrl . '/' . $storageId . '/file/copy',
            [
                'headers' => [
                    'Authorization' => $this->secretKey
                ],
                'json' => [
                    'fileId' => $fileId,
                    'folderId' => $folderId
                ]
            ]
        );
    }

    /**
     * @param $fileId
     * @param $folderId
     * @param $storageId
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function moveFile($fileId, $folderId, $storageId)
    {
        return $this->client->postAsync(
            $this->baseUrl . '/' . $storageId . '/file/move',
            [
                'headers' => [
                    'Authorization' => $this->secretKey
                ],
                'json' => [
                    'fileId' => $fileId,
                    'folderId' => $folderId
                ]
            ]
        );
    }

    /**
     * @param $fileId
     * @param $storageId
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function deleteFile($fileId, $storageId)
    {
        return $this->client->postAsync(
            $this->baseUrl . '/' . $storageId . '/file/delete',
            [
                'headers' => [
                    'Authorization' => $this->secretKey
                ],
                'json' => [
                    'fileId' => $fileId
                ]
            ]
        );
    }

    /**
     * @param $fileId
     * @param $storageId
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function createShareLink($fileId, $storageId)
    {
        return $this->client->postAsync(
            $this->baseUrl . '/' . $storageId . '/file/share',
            [
                'headers' => [
                    'Authorization' => $this->secretKey
                ],
                'json' => [
                    'fileId' => $fileId
                ]
            ]
        );
    }

    /**
     * @param $folderId
     * @param $storageId
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function createFolderShareLink($folderId, $storageId)
    {
        return $this->client->postAsync(
            $this->baseUrl . '/' . $storageId . '/folder/share',
            [
                'headers' => [
                    'Authorization' => $this->secretKey
                ],
                "json" => [
                    "folderId" => $folderId
                ]
            ]
        );
    }

    /**
     * @param $folderId
     * @param $folderName
     * @param $storageId
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function updateFolderName($folderId, $folderName, $storageId)
    {
        return $this->client->postAsync(
            $this->baseUrl . '/' . $storageId . '/folder/update',
            [
                'headers' => [
                    'Authorization' => $this->secretKey
                ],
                'json' => [
                    "folderId" => $folderId,
                    "data" => [
                        'name' => $folderName
                    ]
                ]
            ]
        );
    }
}