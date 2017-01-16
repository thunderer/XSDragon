<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Filesystem;

final class MemoryFilesystem implements FilesystemInterface
{
    /** @var string */
    private $basePath;
    /** @var string[] */
    private $files = [];

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function write(string $path, string $content)
    {
        $this->files[] = $this->basePath.DIRECTORY_SEPARATOR.$path."\n".$content;
    }

    public function getFiles(): array
    {
        return $this->files;
    }
}
