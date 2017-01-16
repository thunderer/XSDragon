<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Filesystem;

/**
 * @codeCoverageIgnore
 */
final class FilePutContentsFilesystem implements FilesystemInterface
{
    /** @var string */
    private $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function write(string $path, string $content)
    {
        $finalPath = $this->basePath.DIRECTORY_SEPARATOR.$path;
        if(!@mkdir(dirname($finalPath), 0777, true) && !is_dir(dirname($finalPath))) {
            throw new \RuntimeException(sprintf('Failed to create target directory %s!', dirname($path)));
        }
        file_put_contents($finalPath, $content);
    }
}
