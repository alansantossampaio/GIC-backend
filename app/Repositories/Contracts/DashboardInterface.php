<?php

namespace App\Repositories\Contracts;

interface DashboardInterface
{
    public function statistics(array $condominiumIds): array;
}
