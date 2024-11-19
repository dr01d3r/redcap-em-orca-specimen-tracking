<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $this */
namespace ORCA\OrcaSpecimenTracking;

use Exception;

trait HookUtils {
    public function redcap_module_link_check_display($project_id, $link): bool
    {
        try {
            // limit restricted links to only Project Design rights
            if ($link["restricted"] === true) {
                return defined("USERID") && \REDCap::getUserRights()[USERID]['design'] === "1";
            }
        } catch (Exception $ex) {}
        return true;
    }

    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) {
        if ($project_id === null) return;
        try {
            $config = $this->getConfiguration($project_id);
            // handle fatal errors and stop processing
            if (empty($config["errors"])) {
                switch ($project_id) {
                    case $config["box_project_id"]:
                        // load script for doing the secondary unique field copy
                        $scriptUrl = $this->getUrl("hooks/box_project/redcap_data_entry_form.js");
                        echo "<script src='$scriptUrl'></script>";
                        break;
                }
            }
        } catch (Exception $ex) {
            // log it, but fail silently
            $this->log("ERROR - redcap_data_entry_form: " . $ex->getMessage(), [ "project_id" => $project_id ]);
        }
    }

    function redcap_every_page_top($project_id) {
        if ($project_id === null) return;

        try {
            // only process this page request if it is the page and context we care about
            if (PAGE === "DataEntry/record_home.php" && !isset($_GET["auto"])) {
                $config = $this->getConfiguration($project_id);
                // handle fatal errors and stop processing
                if (empty($config["errors"])) {
                    $url = null;
                    $link_text = "";
                    switch ($project_id) {
                        case $config["box_project_id"]:
                            $url = $this->getUrl("views/dashboard.php");
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
            // log it, but fail silently
            $this->log("ERROR - redcap_every_page_top: " . $ex->getMessage(), [ "project_id" => $project_id ]);
        }
    }
}