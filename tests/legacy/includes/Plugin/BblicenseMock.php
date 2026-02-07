<?php

class Plugin_BblicenseMock
{
    public function partner_order_reset(): bool
    {
        return true;
    }

    public function partner_order_create(): bool
    {
        return true;
    }

    public function partner_order_suspend(): bool
    {
        return true;
    }

    public function partner_order_unsuspend(): bool
    {
        return true;
    }

    public function partner_order_delete(): bool
    {
        return true;
    }
}
