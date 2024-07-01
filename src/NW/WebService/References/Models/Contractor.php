<?php

class Contractor
{
    const TYPE_CUSTOMER = 0;
    public $id;
    public $type;
    public $name;
    public $Seller;

    public static function getById(int $resellerId): self
    {
        $instance = new self();
        $instance->id = $resellerId;
        $instance->type = self::TYPE_CUSTOMER;
        $instance->name = "Reseller {$resellerId}";
        $instance->Seller = new Seller();
        $instance->Seller->id = $resellerId;
        return $instance;
    }

    public function getFullName(): string
    {
        return $this->name . ' ' . $this->id;
    }
}
