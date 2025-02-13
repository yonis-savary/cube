<?php

namespace YonisSavary\Cube\Http;

use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Logger\Logger;
use YonisSavary\Cube\Utils\Path;

class Upload
{
    const KB = 1024;
    const MB = 1024 * self::KB;
    const GB = 1024 * self::MB;

    const PHP_ERROR_EXPLAINATION = [
        UPLOAD_ERR_OK         => 'UPLOAD_ERR_OK: No error with the upload',
        UPLOAD_ERR_PARTIAL    => 'UPLOAD_ERR_PARTIAL: File only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'UPLOAD_ERR_NO_FILE: No file was uploaded',
        UPLOAD_ERR_EXTENSION  => 'UPLOAD_ERR_EXTENSION: File upload stopped by a PHP extension',
        UPLOAD_ERR_FORM_SIZE  => 'UPLOAD_ERR_FORM_SIZE: File exceeds MAX_FILE_SIZE in the HTML form',
        UPLOAD_ERR_INI_SIZE   => 'UPLOAD_ERR_INI_SIZE: File exceeds upload_max_filesize in php.ini',
        UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR: Temporary folder not found',
        UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE: Unknown upload error',
    ];

    public readonly string $inputName;
    public readonly string $filename;
    public readonly string $extension;
    public readonly string $type;
    public readonly string $tempName;
    public readonly int $error;
    public readonly int $size;

    protected ?string $newPath = null;

    /**
     * @param array $data Data from PHP $_FILES
     * @param string $inputName Input name key from $_FILES
     */
    public function __construct(array $data, string $inputName='uploads')
    {
        // $_FILES data
        $this->filename = $data['name'];
        $this->type = $data['type'];
        $this->tempName = $data['tmp_name'];
        $this->error = $data['error'];
        $this->size = $data['size'];

        // Extras info
        $this->inputName = $inputName;
        $this->extension = pathinfo($this->filename, PATHINFO_EXTENSION);
    }

    public function fail(string $reason, Storage $destination): false
    {
        $destination = $destination->getRoot();
        $logger = Logger::getInstance();
        $logger->error("Could not move file {path} ({size} bytes) into {dest}", [
            "path" => $this->tempName,
            "size" => $this->size,
            "dest" => $destination
        ]);
        $logger->error("Reason: {reason}", ["reason" => $reason]);
        return false;
    }

    public function getPHPUploadErrorMessage(): string
    {
        return self::PHP_ERROR_EXPLAINATION[$this->error];
    }

    /**
     * Try to move the file to a new directory, return `true` on success or `false` on failure
     *
     * @param string|Storage $destination Either a target directory name (relative to Storage directory), or a Storage object
     * @param string $newName New name of the file, a name is generated if null is given
     * @return string|false The new file path on success, `false` on fail, see `getFailReason()` to get the reason behind a failure
     */
    public function move(string|Storage|null $destination=null, ?string $newName=null): string|false
    {
        if ($movedFile = $this->newPath)
            return $movedFile;

        $newName = $newName ?? uniqid(time() . "-", true);

        $destination ??= "Uploads";
        if (is_string($destination))
            $destination = Storage::getInstance()->child($destination);

        $newPath = $destination->path($newName);

        if ($this->error !== UPLOAD_ERR_OK)
            return $this->fail($this->getPHPUploadErrorMessage(), $destination);

        if (!is_writable($destination->getRoot()))
            return $this->fail("Target directory [".Path::toRelative($destination->getRoot())."] is not writable", $destination);

        if (is_file($newPath))
            return $this->fail("[". Path::toRelative($newPath) ."] already exists", $destination);

        if (!rename($this->tempName, $newPath))
            return $this->fail("rename() function failed", $destination);

        if (!$destination->isFile($newName))
            return $this->fail("successful renamed() but [". Path::toRelative($newPath) ."] does not exists", $destination);

        if (filesize($newPath) != $this->size)
            return $this->fail("New file size do not match PHP upload size", $destination);

        $this->newPath = $newPath;
        return $newPath;
    }


}