<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Logger;

final class NullLogger implements LoggerInterface
{
    public function log(string $message)
    {
        // shh... quiet and calm.
    }
}
