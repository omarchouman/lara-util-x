<?php

namespace LaraUtilX\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

trait FileProcessingTrait
{
    /**
     * Get file contents.
     *
     * Returns null when the file does not exist, so a missing file cannot be
     * confused with a file whose contents happen to say so.
     *
     * @param string $filename
     * @param string $directory
     * @return string|null
     */
    public function getFile(string $filename, string $directory = 'uploads'): ?string
    {
        $filePath = $directory . '/' . $filename;

        if (Storage::exists($filePath)) {
            return Storage::get($filePath);
        }

        return null;
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
        // The client-supplied name is never reused, only its extension, so a
        // hostile filename cannot influence where the file lands.
        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension();
        $filename  = Str::random(40) . ($extension ? '.' . $extension : '');

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
