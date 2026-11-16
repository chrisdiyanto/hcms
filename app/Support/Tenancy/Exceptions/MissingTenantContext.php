<?php

namespace App\Support\Tenancy\Exceptions;

use RuntimeException;

class MissingTenantContext extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Tenant context belum di-set; akses data bisnis ditolak.');
    }
}
