<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Logger;

interface LoggerInterface
{
    public function log(string $message);
}
