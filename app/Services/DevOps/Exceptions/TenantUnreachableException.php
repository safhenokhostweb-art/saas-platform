<?php

namespace App\Services\DevOps\Exceptions;

/**
 * The site has no completed installation, no server record, or a hosting
 * type whose driver cannot run shell commands (SFTP, DirectAdmin, Docker).
 * Reported as "not measurable", never as a failure of the site itself.
 */
class TenantUnreachableException extends \RuntimeException
{
}
