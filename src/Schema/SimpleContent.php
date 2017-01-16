<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Schema;

use Thunder\Xsdragon\Utility\XsdUtility;

final class SimpleContent
{
    /** @var Extension|Restrictions */
    private $type;

    public function __construct($type)
    {
        if(false === (is_object($type) && in_array(get_class($type), [Extension::class, Restrictions::class], true))) {
            throw new \InvalidArgumentException(sprintf('Invalid SimpleContent type `%s`!', XsdUtility::describe($type)));
        }

        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }
}
