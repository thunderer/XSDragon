<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Logger;

/**
 * @codeCoverageIgnore
 */
final class EchoLogger implements LoggerInterface
{
    public function log(string $message)
    {
        echo $message."\n";
    }
}
