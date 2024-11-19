<?php
// Set the namespace defined in your config file
namespace ORCA\OrcaSpecimenTracking;

// The next 2 lines should always be included and be the same in every module
use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use Exception;

require_once 'traits/ConfigUtils.php';
require_once 'traits/HookUtils.php';
require_once 'traits/ModuleUtils.php';
require_once 'traits/PlateUtils.php';
require_once 'traits/SpecimenUtils.php';
require_once 'traits/ShipmentUtils.php';
require_once 'traits/ReportUtils.php';
require_once 'traits/REDCapUtils.php';
require_once 'traits/RequestHandlers.php';

/**
 * Class OrcaSpecimenTracking
 * @package ORCA\OrcaSpecimenTracking
 */
class OrcaSpecimenTracking extends AbstractExternalModule {
    use ConfigUtils;
    use HookUtils;
    use ModuleUtils;
    use PlateUtils;
    use SpecimenUtils;
    use ShipmentUtils;
    use ReportUtils;
    use REDCapUtils;
    use RequestHandlers;
}