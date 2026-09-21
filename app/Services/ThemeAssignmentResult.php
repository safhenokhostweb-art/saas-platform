<?php

namespace App\Services;

use App\Models\PlatformLicense;
use App\Models\ThemeAssignment;
use App\Models\ThemeLicense;
use App\Models\ThemeRelease;

final readonly class ThemeAssignmentResult
{
    public function __construct(
        public ThemeAssignment $assignment,
        public PlatformLicense $platformLicense,
        public ThemeLicense $themeLicense,
        public ThemeRelease $release,
        public bool $platformLicenseCreated,
        public bool $themeLicenseCreated,
        public bool $assignmentCreated,
    ) {}
}
