<?php

namespace Crm\InvoicesModule\Sandbox;

use Nette\InvalidArgumentException;
use Nette\Utils\Finder;
use RuntimeException;

class InvoiceSandbox
{
    private $folder;

    public function __construct($folder)
    {
        // todo: zober posledny znak / ak existuje
        if ($folder[strlen($folder) - 1] == '/') {
            $folder = substr($folder, 0, strlen($folder) - 1);
        }

        $this->folder = $folder;
    }

    public function getFileList()
    {
        $result = [];
        foreach (Finder::findFiles('*')->in($this->folder) as $key => $file) {
            $result[] = $file;
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

        $target = $this->folder . '/' . $newName;
        $result = copy($localFile, $target);
        if (!$result) {
            throw new RuntimeException("Cannot copy '$localFile' to '$target'");
        }

        unlink($localFile);
    }

    public function removeFile($fileName)
    {
        $file = $this->folder . '/' . $fileName;
        if (!file_exists($file)) {
            throw new InvalidArgumentException("File '$file' doesn't exists");
        }
        return unlink($file);
    }

    public function clearOld()
    {
        // todo
    }
}
