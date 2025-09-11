<?php
declare(strict_types=1);

namespace Crm\InvoicesModule\Models\Sandbox;

use Crm\ApplicationModule\Application\Managers\ApplicationMountManager;
use Nette\InvalidArgumentException;

class InvoiceSandbox
{
    public const INVOICE_SANDBOX_BUCKET_NAME = 'invoice-sandbox';

    public function __construct(
        private readonly ApplicationMountManager $mountManager,
    ) {
    }

    public function getFileList()
    {
        $contents = $this->mountManager->getListContents(self::INVOICE_SANDBOX_BUCKET_NAME);
        $result = [];

        foreach ($contents as $content) {
            if ($content['type'] === 'file') {
                $result[] = $content;
            }
        }

        return $result;
    }

    public function addFile($localFile, $newName = null)
    {
        if (!file_exists($localFile)) {
            throw new InvalidArgumentException("File '$localFile' doesn't exists");
        }
        if ($newName == null) {
            $newName = basename($localFile);
        }

        $fileContents = file_get_contents($localFile);

        $path = $this->mountManager->getFilePath(self::INVOICE_SANDBOX_BUCKET_NAME, $newName);
        $this->mountManager->write($path, $fileContents);

        unlink($localFile);
    }

    public function removeFile($fileName): bool
    {
        $path = $this->mountManager->getFilePath(self::INVOICE_SANDBOX_BUCKET_NAME, $fileName);
        if (!$this->mountManager->has($path)) {
            throw new InvalidArgumentException("File '$fileName' doesn't exists");
        }
        $this->mountManager->delete($path);
        return !$this->mountManager->has($path);
    }

    public function clearOld()
    {
        // todo
    }

    private function getFilePath(string $fileName)
    {
        return $this->mountManager->getFilePath(self::INVOICE_SANDBOX_BUCKET_NAME, $fileName);
    }

    public function readFileStream(string $fileName)
    {
        $path = $this->getFilePath($fileName);
        if (!$this->mountManager->has($path)) {
            throw new InvalidArgumentException("File '$fileName' doesn't exists");
        }
        return $this->mountManager->readStream($path);
    }

    public function fileExists(string $fileName): bool
    {
        $path = $this->getFilePath($fileName);
        return $this->mountManager->has($path);
    }
}
