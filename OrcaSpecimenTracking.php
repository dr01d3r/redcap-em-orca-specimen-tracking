<?php
// Set the namespace defined in your config file
namespace ORCA\OrcaSpecimenTracking;

// The next 2 lines should always be included and be the same in every module
use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use Exception;

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
    use ModuleUtils;
    use PlateUtils;
    use SpecimenUtils;
    use ShipmentUtils;
    use ReportUtils;
    use REDCapUtils;
    use RequestHandlers;

    public $_config = [
        // TODO fields required to exist in each project
        "fields" => [
            "box" => [
                "record_id",
                "box_name",
                "box_status",
                "box_type",
                "sample_type",
                "shipment_record_id"
            ],
            "aliquot" => [
                "record_id",
                "name",
                "box_record_id"
            ],
            "shipment" => [
                "record_id",
                "shipment_name",
                "shipment_status"
            ]
        ],
        "plate_size_map" => [
            "9x9" => [
                "row" => 9,
                "col" => 9
            ],
            "8x12" => [
                "row" => 8,
                "col" => 12
            ]
        ],
        "sample_type_units" => [
            "bD" => "tube(s)",
            "sD" => "vial(s)",
            "bl" => "mL",
            "bs" => "slide(s)",
            "cb" => "x10(6) cells",
            "il" => "mL",
            "mx" => "n/a",
            "np" => "mL",
            "ns" => "mL",
            "pb" => "x10(6) cells",
            "pf" => "mL",
            "pl" => "mL",
            "px" => "tube(s)",
            "sa" => "mL",
            "sr" => "mL",
            "st" => "vial(s)",
            "ur" => "mL",
            // TODO don't have a key for Blood Spot
        ]
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function redcap_module_link_check_display($project_id, $link) {
        return true;
    }

    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) {
        if ($project_id === null) return;
        try {
            $config = $this->getConfiguration($project_id);
            // handle fatal errors and stop processing
            if (!empty($config["errors"])) {
                $config_errors = $config["errors"];
                // TODO do something with the errors?
            } else {
                switch ($project_id) {
                    case $config["plate_project_id"]:
                        // load script for doing the secondary unique field copy
                        $scriptUrl = $this->getUrl("hooks/box_project/redcap_data_entry_form.js");
                        echo "<script src='$scriptUrl'></script>";
                        break;
                }
            }
        } catch (Exception $ex) {
            // TODO do something with the error?
        }
    }

    function redcap_every_page_top($project_id) {
        if ($project_id === null) return;

        try {
            // only process this page request if it is the page and context we care about
            if (PAGE === "DataEntry/record_home.php" && !isset($_GET["auto"])) {
                $config = $this->getConfiguration($project_id);
                // handle fatal errors and stop processing
                if (!empty($config["errors"])) {
                    $config_errors = $config["errors"];
                    // TODO do something with the errors?
                } else {
                    $url = null;
                    $link_text = "";
                    switch ($project_id) {
                        case $config["plate_project_id"]:
                            $url = $this->getUrl("views/index.php");
                            $link_text = "Box Dashboard";
                            if (isset($_GET["id"])) {
                                $url .= "&" . http_build_query([
                                        "id" => $_GET["id"]
                                    ]);
                            }
                            break;
                        case $config["shipment_project_id"]:
                            $url = $this->getUrl("views/shipment.php");
                            $link_text = "Shipment Dashboard";
                            if (isset($_GET["id"])) {
                                $url .= "&" . http_build_query([
                                        "id" => $_GET["id"]
                                    ]);
                            }
                            break;
                    }
                    // stop processing if url is empty
                    if (empty($url)) return;
                    // if this is after a new record, auto-navigate back to dashboard
                    // otherwise, just inject a link into record_home
                    if (isset($_GET["msg"]) && $_GET["msg"] === "add") {
                        // be sure to always call exitAfterHook!
                        $this->exitAfterHook();
                        header("Location: $url");
                    } else {
                        ?><script type="text/javascript">
                            $(function() {
                                let html = `<span>&nbsp;|&nbsp;</span><a href="<?=$url?>" class="text-primary ml-1"><i class="fas fa-share"></i>&nbsp;<?=$link_text?></a>`;
                                let target = $(".projhdr:first");
                                if (target.length) {
                                    target.append(html);
                                }
                            });
                        </script><?php
                    }
                }
            }
        } catch (Exception $ex) {
            // TODO do something with the error?
        }
    }
}