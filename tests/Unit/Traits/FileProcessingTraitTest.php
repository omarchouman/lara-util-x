<?php

namespace LaraUtilX\Tests\Unit\Traits;

use LaraUtilX\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class FileProcessingTraitTest extends TestCase
{
    use \LaraUtilX\Traits\FileProcessingTrait;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use the local disk for testing
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        // Clean up any test files
        if (Storage::disk('local')->exists('uploads')) {
            Storage::disk('local')->deleteDirectory('uploads');
        }
        
        parent::tearDown();
    }

    public function test_can_get_existing_file()
    {
        $filename = 'test_file.txt';
        $directory = 'uploads';
        $content = 'Test file content';
        
        // Create a test file
        Storage::disk('local')->put($directory . '/' . $filename, $content);
        
        $result = $this->getFile($filename, $directory);
        
        $this->assertEquals($content, $result);
    }

    public function test_returns_file_not_found_for_non_existent_file()
    {
        $filename = 'non_existent.txt';
        $directory = 'uploads';
        
        $result = $this->getFile($filename, $directory);
        
        $this->assertEquals('File not found', $result);
    }

    public function test_can_upload_single_file()
    {
        $file = UploadedFile::fake()->create('test.pdf', 100);
        $directory = 'uploads';
        
        $filename = $this->uploadFile($file, $directory);
        
        $this->assertIsString($filename);
        $this->assertStringContainsString('test.pdf', $filename);
        $this->assertTrue(Storage::disk('local')->exists($directory . '/' . $filename));
    }

    public function test_uploaded_file_has_unique_name()
    {
        $file1 = UploadedFile::fake()->create('test.pdf', 100);
        $file2 = UploadedFile::fake()->create('test.pdf', 100);
        $directory = 'uploads';
        
        $filename1 = $this->uploadFile($file1, $directory);
        $filename2 = $this->uploadFile($file2, $directory);
        
        $this->assertNotEquals($filename1, $filename2);
    }

    public function test_can_upload_multiple_files()
    {
        $files = [
            UploadedFile::fake()->create('file1.pdf', 100),
            UploadedFile::fake()->create('file2.pdf', 100),
            UploadedFile::fake()->create('file3.pdf', 100),
        ];
        $directory = 'uploads';
        
        $filenames = $this->uploadFiles($files, $directory);
        
        $this->assertIsArray($filenames);
        $this->assertCount(3, $filenames);
        
        foreach ($filenames as $filename) {
            $this->assertIsString($filename);
            $this->assertTrue(Storage::disk('local')->exists($directory . '/' . $filename));
        }
    }

    public function test_can_delete_single_file()
    {
        $filename = 'test_delete.txt';
        $directory = 'uploads';
        $content = 'Test content';
        
        // Create a test file
        Storage::disk('local')->put($directory . '/' . $filename, $content);
        $this->assertTrue(Storage::disk('local')->exists($directory . '/' . $filename));
        
        // Delete the file
        $this->deleteFile($filename, $directory);
        
        $this->assertFalse(Storage::disk('local')->exists($directory . '/' . $filename));
    }

    public function test_can_delete_multiple_files()
    {
        $filenames = ['file1.txt', 'file2.txt', 'file3.txt'];
        $directory = 'uploads';
        
        // Create test files
        foreach ($filenames as $filename) {
            Storage::disk('local')->put($directory . '/' . $filename, 'content');
        }
        
        // Verify files exist
        foreach ($filenames as $filename) {
            $this->assertTrue(Storage::disk('local')->exists($directory . '/' . $filename));
        }
        
        // Delete all files
        $this->deleteFiles($filenames, $directory);
        
        // Verify files are deleted
        foreach ($filenames as $filename) {
            $this->assertFalse(Storage::disk('local')->exists($directory . '/' . $filename));
        }
    }

    public function test_uses_default_directory_when_not_specified()
    {
        $file = UploadedFile::fake()->create('test.pdf', 100);
        
        $filename = $this->uploadFile($file);
        
        $this->assertTrue(Storage::disk('local')->exists('uploads/' . $filename));
    }

    public function test_handles_empty_files_array()
    {
        $filenames = $this->uploadFiles([], 'uploads');
        
        $this->assertIsArray($filenames);
        $this->assertEmpty($filenames);
    }

    public function test_handles_empty_delete_files_array()
    {
        // This should not throw an exception
        $this->deleteFiles([], 'uploads');
        
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function test_upload_preserves_file_extension()
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);
        $directory = 'uploads';
        
        $filename = $this->uploadFile($file, $directory);
        
        $this->assertStringEndsWith('.pdf', $filename);
    }

    public function test_upload_handles_special_characters_in_filename()
    {
        $file = UploadedFile::fake()->create('test file with spaces.pdf', 100);
        $directory = 'uploads';
        
        $filename = $this->uploadFile($file, $directory);
        
        $this->assertIsString($filename);
        $this->assertTrue(Storage::disk('local')->exists($directory . '/' . $filename));
    }
}
