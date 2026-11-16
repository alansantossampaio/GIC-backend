<?php

namespace Tests\Unit;

use App\Models\Condominium;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use App\Repositories\Core\ResidentRepository;
use App\Repositories\Core\StructureRepository;
use App\Services\ResidentService;
use App\Services\StructureService;
use PHPUnit\Framework\TestCase;

class ResidentServiceTest extends TestCase
{
    public function test_service_initialization(): void
    {
        $repository = new ResidentRepository();
        $service = new ResidentService($repository);

        $this->assertNotNull($service);
    }

    public function test_structure_service_initialization(): void
    {
        $service = new StructureService(new StructureRepository());

        $this->assertNotNull($service);
    }

    public function test_resident_repository_implements_interface(): void
    {
        $repository = new ResidentRepository();

        $this->assertInstanceOf(\App\Repositories\Contracts\ResidentInterface::class, $repository);
    }
}
