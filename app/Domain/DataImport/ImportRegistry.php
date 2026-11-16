<?php

namespace App\Domain\DataImport;

use App\Domain\DataImport\Importers\EmployeesImporter;
use App\Domain\DataImport\Importers\JobFamiliesImporter;
use App\Domain\DataImport\Importers\JobGradesImporter;
use App\Domain\DataImport\Importers\JobLevelsImporter;
use App\Domain\DataImport\Importers\JobsImporter;
use App\Domain\DataImport\Importers\PositionsImporter;
use App\Domain\DataImport\Importers\UnitsImporter;

/** Jenis impor, dalam urutan pengerjaan yang disarankan (rujukan dulu). */
final class ImportRegistry
{
    public const MAX_WEB_ROWS = 5000;

    public const TYPES = [
        'units' => UnitsImporter::class,
        'job_families' => JobFamiliesImporter::class,
        'job_levels' => JobLevelsImporter::class,
        'job_grades' => JobGradesImporter::class,
        'jobs' => JobsImporter::class,
        'positions' => PositionsImporter::class,
        'employees' => EmployeesImporter::class,
    ];

    public static function make(string $type, array $options = []): Importer
    {
        $class = self::TYPES[$type] ?? abort(404);

        return new $class($options);
    }
}
