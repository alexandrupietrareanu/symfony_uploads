<?php

namespace App\Service;
use Gedmo\Sluggable\Util\Urlizer;
use League\Flysystem\AdapterInterface;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploaderHelper
{
    private $folderImage = 'article_image';
    private $folderReference = 'article_reference';

    private $filesystem;

//    private $privateFilesystem;

    private $logger;

    private $publicAssetBaseUrl;

    public function __construct(
        LoggerInterface $logger,
        FilesystemInterface $publicUploadFilesystem,
//        FilesystemInterface $privateUploadFilesystem ,
        string $uploadedAssetsBaseUrl){

        $this->logger = $logger;
        $this->filesystem = $publicUploadFilesystem;
//        $this->privateFilesystem = $privateUploadFilesystem;
        $this->publicAssetBaseUrl = $uploadedAssetsBaseUrl;
    }

    public function uploadArticleImage(File $file, ?string $existingFilename): string
    {
        $newFilename = $this->uploadFile($file, $this->folderImage, true);
        if($existingFilename){
            try{
                $result = $this->filesystem->delete($this->folderImage.'/'.$existingFilename);
                if($result === false){
                    throw new \Exception(sprintf('Could not delete old uploaded file "%s"', $existingFilename));
                }
            } catch(FileNotFoundException $e){
                $this->logger->alert(sprintf('Old uploaded file %s was missing when trying to delete', $existingFilename));
            }

        }

        return $newFilename;
    }

    private function uploadFile(File $file, string $directory, bool $isPublic): string
    {
        if ($file instanceof UploadedFile) {
            $originalFilename = $file->getClientOriginalName();
        } else {
            $originalFilename = $file->getFilename();
        }

        $newFilename = Urlizer::urlize(pathinfo($originalFilename, PATHINFO_FILENAME)).'-'.uniqid().'.'.$file->guessExtension();
//        $filesystem = $isPublic ? $this->filesystem : $this->privateFilesystem;
        $filesystem = $this->filesystem;
        $stream = fopen($file->getPathname(), 'r');
        $result = $filesystem->writeStream(
            $directory .'/'. $newFilename,
            $stream,
            [
                'visibility' => $isPublic ? AdapterInterface::VISIBILITY_PUBLIC : AdapterInterface::VISIBILITY_PRIVATE
            ]
        );

        if($result === false){
            throw new \Exception(sprintf('Could not write uploade file "%s"', $newFilename));
        }

        if($result === false){
            throw new \Exception(sprintf('Could not write uploaded file "%s"', $newFilename));
        }

        if(is_resource($stream)){
            fclose($stream);
        }
        return $newFilename;
    }

    /**
     * @return resource
     */
    public function readStream(string $path, bool $isPublic)
    {
        $filesystem = $this->filesystem;
//        $filesystem = $isPublic ? $this->filesystem : $this->privateFilesystem;
        $resource = $filesystem->readStream($path);
        if ($resource === false) {
            throw new \Exception(sprintf('Error opening stream for "%s"', $path));
        }
        return $resource;
    }

    public function deleteFile(string $path, bool $isPublic)
    {
//        $filesystem = $isPublic ? $this->filesystem : $this->privateFilesystem;
        $filesystem = $this->filesystem;
        $result = $filesystem->delete($path);
        if ($result === false) {
            throw new \Exception(sprintf('Error deleting "%s"', $path));
        }
    }

    public function uploadArticleReference(File $file):string
    {
        return $this->uploadFile($file, $this->folderReference, false);
    }


    public function getPublicPath(string $path): string
    {
        return $this->publicAssetBaseUrl.$path;
    }
}