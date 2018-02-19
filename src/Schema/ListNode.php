<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Schema;

final class ListNode
{
    /** @var string */
    private $itemType;

    public function __construct(string $itemType)
    {
        $this->itemType = $itemType;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }
}
