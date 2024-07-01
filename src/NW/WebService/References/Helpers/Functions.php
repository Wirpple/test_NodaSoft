<?php

namespace Helpers;

class Functions
{
    function getResellerEmailFrom(): string
    {
        return 'contractor@example.com';
    }

    function getEmailsByPermit($resellerId, $event): array
    {
        return ['someemail@example.com', 'someemail2@example.com'];
    }
}
