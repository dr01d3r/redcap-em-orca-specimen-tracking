<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $this */
namespace ORCA\OrcaSpecimenTracking;

use Exception;

trait ShipmentUtils {

    /**
     * @return string
     * @throws Exception
     */
    function getNewShipmentURL(): string
    {
        // prep new shipment url
        $new_shipment_id = \DataEntry::getAutoId($this->getShipmentProject()->project_id);
        return APP_PATH_WEBROOT . "DataEntry/index.php?" . http_build_query([
                "pid" => $this->getShipmentProject()->project_id,
                "id" => $new_shipment_id,
                "event_id" => $this->getShipmentProject()->firstEventId,
                "page" => $this->getShipmentProject()->firstForm,
                "auto" => "1"
            ]);
    }

    /**
     * @param $shipment_id
     * @return array
     * @throws Exception
     */
    function getShipment($shipment_id): array
    {
        if (!is_numeric($shipment_id)) return [];
        $project = $this->getShipmentProject();

        // get all plate info by record
        $records = \REDCap::getData([
            "project_id" => $project->project_id,
            "records" => [ $shipment_id ]
        ]);
        if (count($records) === 1 && isset($records[$shipment_id])) {
            return $records[$shipment_id][$project->firstEventId];
        }
        return [];
    }

    /**
     * @return array
     * @throws Exception
     */
    function getShipments(): array
    {
        $project = $this->getShipmentProject();
        // get all plate info by record
        $records = \REDCap::getData([
            "project_id" => $project->project_id
        ]);
        $result = [];
        $url = $this->getUrl("views/shipment.php");
        foreach ($records as $record_id => $record) {
            $row = [];
            foreach ($record[$project->firstEventId] as $key => $val) {
                // convert raw value to display value, since all we're using it for here is display
                // just grab value, as we're not doing any sorting either - keep it flat!
                $row[$key] = $this->getFieldDisplayValue($project, $key, $val)["value"];
            }
            $row["shipment_dashboard_url"] = $url . "&" . http_build_query([ "id" => $record_id ]);
            $result[$record_id] = $row;
        }
        return $result;
    }

    /**
     * @param $shipment_id
     * @param array|null $fields Fields to pull.  By default, pulls all box fields.
     * @return array
     * @throws Exception
     */
    function getBoxesByShipment($shipment_id, array $fields = null): array
    {
        if (!is_numeric($shipment_id)) return [];
        $box_project = $this->getBoxProject();
        // get all plate info by record
        $records = \REDCap::getData([
            "project_id" => $box_project->project_id,
            "fields" => $fields ?? [],
            "filterLogic" => "[shipment_record_id] = $shipment_id"
        ]);
        return array_values(array_map(function($record) use ($box_project) {
            return $record[$box_project->firstEventId];
        }, $records));
    }

    /**
     * @param $shipment_id
     * @return array|void
     * @throws Exception
     */
    function getShipmentManifestData($shipment_id) {
        // for now just exit if a shipment_id is invalid
        if (!is_numeric($shipment_id)) return;

        // get the shipment
        $shipment = $this->getShipment($shipment_id);

        // for now, just exit if no shipment exists with that record_id
        if (empty($shipment)) return;

        // get all boxes tied to this shipment
        $boxes = $this->getBoxesByShipment($shipment_id);

        // for now, just exit if no boxes exists for this shipment
        if (empty($boxes)) return;

        // get the specimens
        $specimens = $this->getSpecimensForBoxes(array_column($boxes, "record_id"));

        // get the module config
        list($metadata, $state) = $this->getModuleConfig();
        // shipment fields
        $shipment_fields = [];
        foreach ($state["fields"]["shipment"] as $fk => $fv) {
            if ($fv["shipment-manifest"]) {
                $shipment_fields[$fk] = true;
            }
        }
        // boxfields
        $box_fields = [];
        foreach ($state["fields"]["box"] as $fk => $fv) {
            if ($fv["shipment-manifest"]) {
                $box_fields[$fk] = true;
            }
        }
        // specimen fields
        $specimen_fields = [];
        foreach ($state["fields"]["specimen"] as $fk => $fv) {
            if ($fv["shipment-manifest"]) {
                $specimen_fields[$fk] = true;
            }
        }

        $result = [];
        // prep shipment fields
        $shipment_data = array_intersect_key($shipment, $shipment_fields);
        foreach ($shipment_data as $key => $val) {
            $shipment_data[$key] = $this->getFieldDisplayValue($this->getShipmentProject(), $key, $val)["value"];
        }
        // let's manually inject the study_name value to be the 2nd column in the output
        $this->array_splice_assoc($shipment_data, 1, 0, [ "study_name" => $state["general"]["study_name"] ]);

        foreach($boxes as $i => $box) {
            // prep box fields
            $box_data = array_intersect_key($box, $box_fields);
            foreach ($specimens[$box["record_id"]] as $j => $specimen) {
                // prep specimen fields
                $specimen_data = array_intersect_key($specimen, $specimen_fields);
                // merge all parts
                $result[] = array_merge($shipment_data, $box_data, $specimen_data);
            }
        }
        // return the result
        return [ $shipment, $result ];
    }

    /**
     * @param $shipment_record_id
     * @param string $status
     * @param array $system_config
     * @return bool|mixed Returns true if successful, otherwise an array of errors
     * @throws Exception
     */
    function updateShipmentStatus($shipment_record_id, string $status, array $system_config) {
        if (!is_numeric($shipment_record_id)) {
            throw new Exception("Cannot update shipment status.  The [shipment_record_id] is invalid or missing.");
        }
        // ensure the shipment exists
        $shipment = $this->getShipment($shipment_record_id);
        if (empty($shipment)) {
            throw new Exception("Cannot update shipment status.  No shipment exists with [record_id]=$shipment_record_id.");
        }
        if ($shipment[$this->getShipmentProject()->firstEventId]["shipment_status"] === $status) {
            throw new Exception("Aborting request.  Shipment status is already '$status'.");
        }
        // validation is good, lets complete this shipment!
        $shipment_save_data = [
            $shipment_record_id => [
                $this->getShipmentProject()->firstEventId => [
                    "shipment_status" => $status
                ]
            ]
        ];
        // save it, ensuring overwrite behavior is set
        $save_result = \REDCap::saveData(
            $this->getShipmentProject()->project_id,
            "array",
            $shipment_save_data,
            "overwrite"
        );
        // handle any errors from the save attempt
        if (!empty($save_result["errors"])) {
            $this->log("ERROR: " . json_encode($save_result["errors"]), [ "project_id" => $this->getShipmentProject()->project_id ]);
            return $save_result["errors"];
        }
        return true;
    }

    /**
     * @param $shipment_record_id
     * @param string $status
     * @param array $system_config
     * @return bool|mixed Returns true if successful, otherwise an array of errors
     * @throws Exception
     */
    function updateBoxStatusByShipmentId($shipment_record_id, string $status, array $system_config) {
        if (!is_numeric($shipment_record_id)) {
            throw new Exception("Cannot complete shipment.  The [shipment_record_id] is invalid or missing.");
        }
        $box_save_data = [];
        // get the boxes
        $boxes = $this->getBoxesByShipment($shipment_record_id, [ "record_id" ]);
        // update boxes to the 'closed' status
        foreach ($boxes as $box) {
            $box_save_data[$box["record_id"]][$this->getBoxProject()->firstEventId]["box_status"] = $status;
        }
        // save box updates, ensuring overwrite behavior is set
        $save_result = \REDCap::saveData(
            $this->getBoxProject()->project_id,
            "array",
            $box_save_data,
            "overwrite"
        );
        // handle any errors from the save attempt
        if (!empty($save_result["errors"])) {
            $this->log("ERROR: " . json_encode($save_result["errors"]), [ "project_id" => $this->getBoxProject()->project_id ]);
            return $save_result["errors"];
        }
        return true;
    }

    /* REQUEST HANDLERS */

    /**
     * @param $system_config
     * @param $payload
     * @return array
     * @throws Exception
     */
    function handleInitializeShipmentDashboard($system_config, $payload): array
    {
        $response = [
            "config" => [],
            "errors" => []
        ];
        $shipment_id = $payload["id"];
        try {
            // get module config, if it exists
            list($metadata, $state) = $this->getModuleConfig();

            $response["config"] = [
                "general" => $state["general"],
                "save-state" => $state["fields"] ?? [],
                "fields" => $metadata ?? [],
                "box_dashboard_base_url" => $this->getUrl("views/dashboard.php"),
                "new_shipment_url" => $this->getNewShipmentURL()
            ];

            // get box context if specified
            if (!empty($shipment_id) && is_numeric($shipment_id)) {
                // get the shipment data
                $shipment = $this->getShipment($shipment_id);
                if (!empty($shipment)) {
                    $response["shipment"] = $shipment;
                    $response["shipment_details"] = [];
                    // build display values
                    foreach ($response["config"]["save-state"]["shipment"] as $key => $val) {
                        if (!$val["shipment-list"]) continue;
                        $response["shipment_details"][$key] = $this->getFieldDisplayValue($this->getShipmentProject(), $key, $shipment[$key])["value"];
                    }
                    // get the boxes associated with this shipment
                    $response["boxes"] = $this->getBoxesByShipment($shipment_id);
                }
                // get record_home url
                $shipment_record_home_url = APP_PATH_WEBROOT . "DataEntry/record_home.php?" . http_build_query([
                        "pid" => $this->getShipmentProject()->project_id,
                        "id" => $shipment_id
                    ]);
                $response["config"]["shipment_record_home_url"] = $shipment_record_home_url;
                // manifest export url
                $manifest_export_url = $this->getUrl("manifest-export.php") . "&" . http_build_query([
                        "id" => $shipment_id
                    ]);
                $response["config"]["manifest_export_url"] = $manifest_export_url;
            }
            $this->addTime("initialization finished");
        } catch (Exception $ex) {
            $this->log("ERROR: " . $ex->getMessage(), [ "project_id" => $this->getShipmentProject()->project_id ]);
            $response["errors"][] = $ex->getMessage();
        }
        // send it back!
        return $response;
    }

    /**
     * @return array
     * @throws Exception
     */
    function handleSearchShipments(): array
    {
        $response = [
            "config" => [],
            "shipments" => [],
            "errors" => []
        ];
        try {
            // get module config, if it exists
            list($metadata, $state) = $this->getModuleConfig();
            $response["config"] = [
                "general" => $state["general"],
                "save-state" => $state["fields"] ?? [],
                "fields" => $metadata ?? [],
                "new_shipment_url" => $this->getNewShipmentURL()
            ];
            $response["shipments"] = array_values($this->getShipments());
        } catch (\Exception $ex) {
            $response["errors"][] = $ex->getMessage();
        }
        // send it back!
        return $response;
    }

    /**
     * @param $shipment_record_id
     * @param $system_config
     * @return array
     * @throws Exception
     */
    function handleCompleteShipment($shipment_record_id, $system_config): array
    {
        $response = [
            "errors" => []
        ];
        if (!is_numeric($shipment_record_id)) {
            $response["errors"][] = "Cannot complete shipment.  The [shipment_record_id] is invalid or missing.";
        } else {
            try {
                // update shipment status
                $shipment_save_result = $this->updateShipmentStatus($shipment_record_id, "complete", $system_config);
                // handle any errors from the save attempt
                if ($shipment_save_result !== true) {
                    $response["errors"][] = $shipment_save_result;
                } else {
                    // update the box status for boxes tied to this shipment
                    $box_save_result = $this->updateBoxStatusByShipmentId($shipment_record_id, "closed", $system_config);
                    if ($box_save_result !== true) {
                        // revert the shipment status change so this error can be more easily corrected
                        $this->updateShipmentStatus($shipment_record_id, "incomplete", $system_config);
                        $response["errors"][] = $box_save_result;
                    } else {
                        // if we got this far, the process was successful
                        $response["success"] = true;
                    }
                }
            } catch (Exception $ex) {
                $this->log("ERROR: " . $ex->getMessage(), [ "project_id" => $this->getShipmentProject()->project_id ]);
                $response["errors"][] = $ex->getMessage();
            }
        }
        // send it back!
        return $response;
    }

    /**
     * @param $box_record_id
     * @param $shipment_record_id
     * @param $system_config
     * @return array
     * @throws Exception
     */
    function handleUpdateBoxShipment($box_record_id, $shipment_record_id, $system_config): array
    {
        $response = [
            "errors" => []
        ];
        if (!is_numeric($box_record_id)) {
            $response["errors"][] = "Cannot update box/shipment.  The [box_record_id] is invalid or missing.";
        }
        if (!empty($shipment_record_id) && !is_numeric($shipment_record_id)) {
            $response["errors"][] = "Cannot update box/shipment.  The [shipment_record_id] must be null or numeric.";
        }
        if (empty($response["errors"])) {
            try {
                // ensure the box exists
                $plate = $this->getBox($box_record_id, $system_config["box_name_regex"]);
                if (empty($plate)) {
                    $response["errors"][] = "Cannot update box/shipment.  No box exists with [record_id]=$box_record_id.";
                }
                // if provided, ensure the shipment exists
                if (!empty($shipment_record_id)) {
                    $shipment = $this->getShipment($shipment_record_id);
                    if (empty($shipment)) {
                        $response["errors"][] = "Cannot update box/shipment.  No shipment exists with [record_id]=$shipment_record_id.";
                    }
                }
                // validation is good, lets save this change
                $save_data = [];
                // add to save dataset
                $save_data[$box_record_id][$this->getBoxProject()->firstEventId] = [
                    "shipment_record_id" => $shipment_record_id
                ];
                // save it, ensuring overwrite behavior is set
                $save_result = \REDCap::saveData(
                    $this->getBoxProject()->project_id,
                    "array",
                    $save_data,
                    "overwrite"
                );
                // handle any errors from the save attempt
                if (!empty($save_result["errors"])) {
                    array_push($response["errors"], ...(is_array($save_result["errors"])?$save_result["errors"]:[$save_result["errors"]]));
                } else {
                    // if we got this far, the process was successful
                    $response["success"] = true;
                }
            } catch (Exception $ex) {
                $this->log("ERROR: " . $ex->getMessage(), [ "project_id" => $this->getBoxProject()->project_id ]);
                $response["errors"][] = $ex->getMessage();
            }
        }
        // send it back!
        return $response;
    }
}