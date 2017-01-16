<?php
namespace Thunder\Xsdragon\Filesystem;

interface FilesystemInterface
{
    public function write(string $path, string $content);
}
