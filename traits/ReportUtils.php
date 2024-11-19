<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $this */
namespace ORCA\OrcaSpecimenTracking;

use Exception;

trait ReportUtils {

    function handleGetReportData(): array
    {
        $response = [
            "config" => [
                "study_name" => null,
                "datetime" => date("m/d/Y H:i:s", strtotime("now")),
                "timestamp" => date("Ymd_His", strtotime("now"))
            ],
            "fields" => [],
            "data" => [],
            "errors" => []
        ];
        try {
            // get the module config
            list($metadata, $state) = $this->getModuleConfig();

            $response["config"]["study_name"] = $state["general"]["study_name"];

            // shipment fields
            $shipment_fields = [];
            foreach ($state["fields"]["shipment"] as $fk => $fv) {
                if ($fv["reporting-table"]) {
                    $shipment_fields[$fk] = $metadata["shipment"][$fk]["field_label"];
                }
            }
            // box fields
            $box_fields = [];
            foreach ($state["fields"]["box"] as $fk => $fv) {
                if ($fv["reporting-table"]) {
                    $box_fields[$fk] = $metadata["box"][$fk]["field_label"];
                }
            }
            // specimen fields
            $specimen_fields = [];
            foreach ($state["fields"]["specimen"] as $fk => $fv) {
                if ($fv["reporting-table"]) {
                    $specimen_fields[$fk] = $metadata["specimen"][$fk]["field_label"];
                }
            }
            // output the fieldset
            $response["fields"] = [
                "shipment" => $shipment_fields,
                "box" => $box_fields,
                "specimen" => $specimen_fields
            ];

            // specimen data
            $box_specimens = [];
            $specimen_project = $this->getSpecimenProject();
            // get all plate info by record
            $specimen_data = \REDCap::getData([
                "project_id" => $specimen_project->project_id,
                "fields" => array_keys($specimen_fields + [ "box_record_id" => true ])
            ]);
            foreach ($specimen_data as $specimen_id => $specimen) {
                $row = [];
                $s = $specimen[$specimen_project->firstEventId];
                foreach ($specimen_fields as $fn => $fv) {
                    $row[$fn] = $this->getFieldDisplayValue($specimen_project, $fn, $s[$fn])["value"];
                }
                $box_specimens[$s["box_record_id"]][$specimen_id] = $row;
            }

            // box data - empty array returns everything
            $boxes = [];
            foreach ($this->getBoxes(array_keys($box_specimens)) as $box_id => $box) {
                $row = [];
                foreach ($box_fields as $fn => $fv) {
                    $row[$fn] = $this->getFieldDisplayValue($this->getBoxProject(), $fn, $box[$fn])["value"];
                }
                $row["shipment_record_id"] = $box["shipment_record_id"];
                $boxes[$box_id] = $row;
            }

            // shipment data
            $shipments = $this->getShipments();

            foreach ($box_specimens as $box_record_id => $specimens) {
                // prep box data
                $box = ($boxes[$box_record_id] ?? []);
                $arr2 = array_intersect_key($box, $box_fields);
                // prep shipment data
                $shipment = $shipments[$box["shipment_record_id"]] ?? [];
                $arr1 = [];
                foreach ($shipment_fields as $fn => $fv) {
                    $arr1[$fn] = $shipment[$fn];
                }
                // specimen loop
                foreach ($specimens as $specimen_record_id => $s) {
                    // prep specimen data
                    $arr3 = [];
                    foreach ($specimen_fields as $fn => $fv) {
                        $arr3[$fn] = $s[$fn];
                    }
                    // add to final dataset
                    $response["data"][] = array_merge($arr1, $arr2, $arr3);
                }
            }
        } catch (Exception $ex) {
            $response["errors"][] = $ex->getMessage();
        }
        // send it back!
        return $response;
    }
}