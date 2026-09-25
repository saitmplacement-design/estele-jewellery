<?php

namespace App\Support;

/**
 * Tidies a typed or pasted Indian mobile number before validation: drops
 * spaces, dashes, brackets and dots, and a leading "+91" / "91" / "0" when
 * exactly 10 digits follow it. Letters are left in place on purpose, so a
 * "digits:10" rule after this still rejects them.
 *
 *   "+91 98765-43210" => "9876543210"
 *   "098765 43210"    => "9876543210"
 *   "98765abcde"      => "98765abcde" (fails digits:10)
 */
class Phone
{
    public static function normalise(string $phone): string
    {
        $phone = preg_replace('/[\s\-().]/', '', $phone);

        return preg_replace('/^(?:\+?91|0)(?=\d{10}$)/', '', $phone);
    }
}
