<?php

namespace App\Platforms\Exceptions;

/**
 * The link can't be used, for a reason the user can act on, such as a playlist link given for a single clip. The
 * message is written for the user.
 */
class UnusableLinkException extends \RuntimeException {}
