<?php

namespace App\Exceptions;

/**
 * The wallet part of a checkout couldn't be taken (e.g. the balance changed
 * mid-checkout). Its own type so checkout can tell it apart from every other
 * RuntimeException — a database error is one too, and used to be shown as a
 * wallet error that wasn't even on screen for a customer with no balance.
 */
class WalletPaymentException extends \RuntimeException {}
