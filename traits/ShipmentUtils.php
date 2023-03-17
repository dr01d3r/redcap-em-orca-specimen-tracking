<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $this */
namespace ORCA\OrcaSpecimenTracking;

use Exception;

/**
 *
 */
trait ShipmentUtils {

    /**
     * @param $exclude_system_fields
     * @return array
     * @throws Exception
     */
    function getShipmentFields($exclude_system_fields = true) {
        $fields = array_column($this->getShipmentProject()->metadata, "element_label", "field_name");
        if ($exclude_system_fields === true) {
            $fields = array_filter($fields,
                function($k) {
                    return !$this->getShipmentProject()->isFormStatus($k);
                }, ARRAY_FILTER_USE_KEY);
        }
        return $fields;
    }

    /**
     * @return string
     * @throws Exception
     */
    function getNewShipmentURL() {
        // prep new shipment url
        $new_shipment_id = \DataEntry::getAutoId($this->getShipmentProject()->project_id);
        $new_shipment_url = APP_PATH_WEBROOT . "DataEntry/index.php?" . http_build_query([
                "pid" => $this->getShipmentProject()->project_id,
                "id" => $new_shipment_id,
                "event_id" => $this->getShipmentProject()->firstEventId,
                "page" => $this->getShipmentProject()->firstForm,
                "auto" => "1"
            ]);
        return $new_shipment_url;
    }

    /**
     * @param $shipment_id
     * @return array
     */
    function getShipment($shipment_id) {
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
    function getShipments() {
        $project = $this->getShipmentProject();
        // get all plate info by record
        $records = \REDCap::getData([
            "project_id" => $project->project_id
        ]);
        $tmp = [];
        $url = $this->getUrl("views/shipment.php");
        foreach ($records as $record_id => $record) {
            $row = [];
            foreach ($record[$project->firstEventId] as $key => $val) {
                // convert raw value to display value, since all we're using it for here is display
                // just grab value, as we're not doing any sorting either - keep it flat!
                $row[$key] = $this->getFieldDisplayValue($project, $key, $val)["value"];
            }
            $row["shipment_dashboard_url"] = $url . "&" . http_build_query([ "id" => $record_id ]);
            $tmp[] = $row;
        }
        return $tmp;
    }

    /**
     * @param $shipment_id
     * @param string $regex
     * @param array|null $fields Fields to pull.  By default, pulls all box fields.
     * @return array
     * @throws Exception
     */
    function getBoxesByShipment($shipment_id, string $regex, array $fields = null) {
        if (!is_numeric($shipment_id)) return [];
        $plate_project = $this->getPlateProject();
        // get all plate info by record
        $records = \REDCap::getData([
            "project_id" => $plate_project->project_id,
            "fields" => $fields ?? $this->_config["fields"]["box"],
            "filterLogic" => "[shipment_record_id] = $shipment_id"
        ]);
        return array_values(array_map(function($record) use ($plate_project, $regex) {
            $tmp = $record[$plate_project->firstEventId];
            foreach ($tmp as $key => $val) {
                // convert raw value to display value, since all we're using it for here is display
                // just grab value, as we're not doing any sorting either - keep it flat!
                $tmp[$key] = $this->getFieldDisplayValue($plate_project, $key, $val)["value"];
            }
            $tmp["name_parsed"] = $this->parsePlateName($tmp["box_name"], $regex);
            return $tmp;
        }, $records));
    }

    /**
     * @param $shipment_id
     * @param array $system_config
     * @return array|void
     * @throws Exception
     */
    function getShipmentManifestData($shipment_id, array $system_config) {
        // TODO for now just exit if a shipment_id is invalid
        if (!is_numeric($shipment_id)) return;
        /*
         * specimen.csid
         * specimen.cuid
         * box.box_name
         * specimen.box_position
         * specimen.name (specimen_name)
         * shipment.shipment_date
         * shipment.shipment_to
         * shipment.shipment_location
         * shipment.shipment_tracking
         * specimen.volume
         * specimen.comment
         *
         * ORDERBY box.box_name, specimen.box_position
         */
        // get the shipment
        $shipment = $this->getShipment($shipment_id);

        // TODO for now, just exit if no shipment exists with that record_id
        if (empty($shipment)) return;

        // use raw SQL to quickly identify all boxes associated with this shipment
        $box_query_result = $this->query("
SELECT d1.record
, d1.value 'shipment_record_id'
, d2.value 'box_name'
FROM redcap_data d1
JOIN redcap_data d2 ON d1.project_id = d2.project_id AND d1.record = d2.record AND d2.field_name = 'box_name'
WHERE d1.project_id = ?
AND d1.field_name = 'shipment_record_id'
AND d1.value = ?
ORDER BY d2.value",
            [
                $this->getPlateProject()->project_id,
                $shipment_id
            ]
        );
        // get all box record_ids
        $boxes = [];
        while($r = db_fetch_assoc($box_query_result)) {
            $boxes[] = $r;
        }
        unset($box_query_result);

        // use raw SQL to quickly identify all specimens associated with these boxes
        $specimen_query = $this->createQuery();
        $specimen_query->add("
SELECT d1.record
, d1.value 'box_record_id'
, d2.value 'name'
, d3.value 'csid'
, d4.value 'cuid'
, d5.value 'box_position'
, d6.value 'volume'
, d7.value 'comment'
FROM redcap_data d1
JOIN redcap_data d2 ON d1.project_id = d2.project_id AND d1.record = d2.record AND d2.field_name = 'name'
LEFT OUTER JOIN redcap_data d3 ON d1.project_id = d3.project_id AND d1.record = d3.record AND d3.field_name = 'csid'
LEFT OUTER JOIN redcap_data d4 ON d1.project_id = d4.project_id AND d1.record = d4.record AND d4.field_name = 'cuid'
LEFT OUTER JOIN redcap_data d5 ON d1.project_id = d5.project_id AND d1.record = d5.record AND d5.field_name = 'box_position'
LEFT OUTER JOIN redcap_data d6 ON d1.project_id = d6.project_id AND d1.record = d6.record AND d6.field_name = 'volume'
LEFT OUTER JOIN redcap_data d7 ON d1.project_id = d7.project_id AND d1.record = d7.record AND d7.field_name = 'comment'
WHERE d1.project_id = ?
AND d1.field_name = 'box_record_id'
",
            [
                $this->getSpecimenProject()->project_id
            ]
        );
        $specimen_query
            ->add('AND')->addInClause('d1.value', array_column($boxes, 'record'))
            ->add('ORDER BY d5.value')
        ;
        $specimen_query_result = $specimen_query->execute();
        // get all the specimen record_ids
        $specimens = [];
        while ($r = db_fetch_assoc($specimen_query_result)) {
            $specimens[$r["box_record_id"]][] = $r;
        }
        unset($specimen_query_result);

        // lets build this dataset!
        $shipment_fields = array_fill_keys([
            "shipment_name",
            "shipment_date",
            "sample_type",
            "shipment_to",
            "shipment_location",
            "shipment_tracking"
        ], true);
        $box_fields = array_fill_keys([
            "box_name"
        ], true);
        $specimen_fields = array_fill_keys([
            "name",
            "csid",
            "cuid",
            "box_position",
            "volume",
            "comment",
        ], true);
        $result = [];
        // prep shipment fields
        $shipment_data = array_intersect_key($shipment, $shipment_fields);
        foreach ($shipment_data as $key => $val) {
            $shipment_data[$key] = $this->getFieldDisplayValue($this->getShipmentProject(), $key, $val)["value"];
        }
        // let's manually inject the study_name value to be the 2nd column in the output
        $this->array_splice_assoc($shipment_data, 1, 0, [ "study_name" => $system_config["study_name"] ]);

        foreach($boxes as $i => $box) {
            // prep box fields
            $box_data = array_intersect_key($box, $box_fields);
            foreach ($specimens[$box["record"]] as $j => $specimen) {
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
            $this->sendError("Cannot update shipment status.  The [shipment_record_id] is invalid or missing.");
        }
        // ensure the shipment exists
        $shipment = $this->getShipment($shipment_record_id);
        if (empty($shipment)) {
            $this->sendError("Cannot update shipment status.  No shipment exists with [record_id]=$shipment_record_id.");
        }
        if ($shipment[$this->getShipmentProject()->firstEventId]["shipment_status"] === $status) {
            $this->sendError("Aborting request.  Shipment status is already '$status'.");
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
            $this->sendError("Cannot complete shipment.  The [shipment_record_id] is invalid or missing.");
        }
        $box_save_data = [];
        // get the boxes
        $boxes = $this->getBoxesByShipment($shipment_record_id, $system_config["box_name_regex"], [ "record_id" ]);
        // update boxes to the 'closed' status
        foreach ($boxes as $box) {
            $box_save_data[$box["record_id"]][$this->getPlateProject()->firstEventId]["box_status"] = $status;
        }
        // save box updates, ensuring overwrite behavior is set
        $save_result = \REDCap::saveData(
            $this->getPlateProject()->project_id,
            "array",
            $box_save_data,
            "overwrite"
        );
        // handle any errors from the save attempt
        if (!empty($save_result["errors"])) {
            $this->log("ERROR: " . json_encode($save_result["errors"]), [ "project_id" => $this->getPlateProject()->project_id ]);
            return $save_result["errors"];
        }
        return true;
    }

    /* REQUEST HANDLERS */

    /**
     * @param $system_config
     * @return void
     */
    function handleInitializeShipmentDashboard($system_config) {
        $response = [
            "config" => [],
            "messages" => [],
            "warnings" => [],
            "errors" => []
        ];

        try {
            // prep some helper info for validation
            $response["config"] = [
                "box_name_regex" => $system_config["box_name_regex"],
                "shipment_fields" => $this->getShipmentFields(true),
                "box_fields" => array_column($this->getPlateProject()->metadata, "element_label", "field_name"),
                "box_dashboard_base_url" => $this->getUrl("views/index.php"),
                "new_shipment_url" => $this->getNewShipmentURL()
            ];

            // get box context if specified
            if (!empty($_GET["id"]) && is_numeric($_GET["id"])) {
                $shipment_id = $_GET["id"];
                // get the shipment data
                $shipment = $this->getShipment($shipment_id);
                if (!empty($shipment)) {
                    $response["shipment"] = $shipment;
                    $response["shipment_details"] = [];
                    // build display values
                    foreach ($response["config"]["shipment_fields"] as $key => $val) {
                        $response["shipment_details"][$key] = $this->getFieldDisplayValue($this->getShipmentProject(), $key, $shipment[$key])["value"];
                    }
                    // get the boxes associated with this shipment
                    $response["boxes"] = $this->getBoxesByShipment($shipment_id, $system_config["box_name_regex"]);
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
        $this->sendResponse($response);
    }

    /**
     * @return void
     * @throws Exception
     */
    function handleSearchShipments() {
        $response = [
            "config" => [],
            "shipments" => [],
            "messages" => [],
            "warnings" => [],
            "errors" => []
        ];
        $response["shipments"] = $this->getShipments();
        $response["config"]["new_shipment_url"] = $this->getNewShipmentURL();
        $response["config"]["shipment_fields"] = array_keys($this->getShipmentFields(true));
        // send it back!
        $this->sendResponse($response);
    }

    /**
     * @param $shipment_record_id
     * @param $system_config
     * @return void
     * @throws Exception
     */
    function handleCompleteShipment($shipment_record_id, $system_config) {
        if (!is_numeric($shipment_record_id)) {
            $this->sendError("Cannot complete shipment.  The [shipment_record_id] is invalid or missing.");
        }
        try {
            // update shipment status
            $shipment_save_result = $this->updateShipmentStatus($shipment_record_id, "complete", $system_config);
            // handle any errors from the save attempt
            if ($shipment_save_result !== true) {
                $this->sendError($shipment_save_result);
            }
            // update the box status for boxes tied to this shipment
            $box_save_result = $this->updateBoxStatusByShipmentId($shipment_record_id, "closed", $system_config);
            if ($box_save_result !== true) {
                // revert the shipment status change so this error can be more easily corrected
                $this->updateShipmentStatus($shipment_record_id, "incomplete", $system_config);
                $this->sendError($box_save_result);
            }
            // if we got this far, the process was successful
            $this->sendResponse("Save successful");
        } catch (Exception $ex) {
            $this->log("ERROR: " . $ex->getMessage(), [ "project_id" => $this->getShipmentProject()->project_id ]);
            $this->sendError($ex->getMessage());
        }
    }

    /**
     * @param $box_record_id
     * @param $shipment_record_id
     * @param $system_config
     * @return void
     * @throws Exception
     */
    function handleUpdateBoxShipment($box_record_id, $shipment_record_id, $system_config) {
        if (!is_numeric($box_record_id)) {
            $this->sendError("Cannot update box/shipment.  The [box_record_id] is invalid or missing.");
        }
        if (!empty($shipment_record_id) && !is_numeric($shipment_record_id)) {
            $this->sendError("Cannot update box/shipment.  The [shipment_record_id] must be null or numeric.");
        }
        try {
            // ensure the box exists
            $plate = $this->getPlate($box_record_id, $system_config["box_name_regex"]);
            if (empty($plate)) {
                $this->sendError("Cannot update box/shipment.  No box exists with [record_id]=$box_record_id.");
            }
            // if provided, ensure the shipment exists
            if (!empty($shipment_record_id)) {
                $shipment = $this->getShipment($shipment_record_id);
                if (empty($shipment)) {
                    $this->sendError("Cannot update box/shipment.  No shipment exists with [record_id]=$shipment_record_id.");
                }
            }
            // validation is good, lets save this change
            $save_data = [];
            // add to save dataset
            $save_data[$box_record_id][$this->getPlateProject()->firstEventId] = [
                "shipment_record_id" => $shipment_record_id
            ];
            // save it, ensuring overwrite behavior is set
            $save_result = \REDCap::saveData(
                $this->getPlateProject()->project_id,
                "array",
                $save_data,
                "overwrite"
            );
            // handle any errors from the save attempt
            if (!empty($save_result["errors"])) {
                $this->sendError($save_result["errors"]);
            }
            $this->sendResponse("Save successful");
        } catch (Exception $ex) {
            $this->log("ERROR: " . $ex->getMessage(), [ "project_id" => $this->getPlateProject()->project_id ]);
            $this->sendError($ex->getMessage());
        }
    }
}