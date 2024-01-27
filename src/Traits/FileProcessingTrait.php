<?php

namespace omarchouman\LaraUtilX\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

trait FileProcessingTrait
{
    /**
     * Get file contents.
     *
     * @param string $filename
     * @param string $directory
     * @return string
     */
    public function getFile(string $filename, string $directory = 'uploads')
    {
        $filePath = $directory . '/' . $filename;

        if (Storage::exists($filePath)) {
            $fileContents = Storage::get($filePath);

            return $fileContents;
        }

        return "File not found";
    }

    /**
     * Upload a file.
     *
     * @param UploadedFile $file
     * @param string $directory
     * @return string
     */
    public function uploadFile(UploadedFile $file, string $directory = 'uploads')
    {
        $filename = uniqid() . '_' . $file->getClientOriginalName();

        $file->storeAs($directory, $filename);

        return $filename;
    }

    /**
     * Upload multiple files.
     *
     * @param array $files
     * @param string $directory
     * @return array
     */
    public function uploadFiles(array $files, string $directory = 'uploads')
    {
        $filenames = [];

        foreach ($files as $file) {
            $filenames[] = $this->uploadFile($file, $directory);
        }

        return $filenames;
    }

    /**
     * Delete a file.
     *
     * @param string $filename
     * @param string $directory
     * @return void
     */
    public function deleteFile(string $filename, string $directory = 'uploads')
    {
        Storage::delete($directory . '/' . $filename);
    }

    /**
     * Delete multiple files.
     *
     * @param array $filenames
     * @param string $directory
     * @return void
     */
    public function deleteFiles(array $filenames, string $directory = 'uploads')
    {
        foreach ($filenames as $filename) {
            $this->deleteFile($filename, $directory);
        }
    }
}
