<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $this */
namespace ORCA\OrcaSpecimenTracking;

use Exception;

trait ReportUtils {

    function handleInitializeReportDashboard($system_config) {
        $response = [
            "config" => [],
            "messages" => [],
            "warnings" => [],
            "errors" => []
        ];
        try {
            // TODO: placeholder for now
        } catch (Exception $ex) {
            $response["errors"][] = $ex->getMessage();
        }
        // send it back!
        $this->sendResponse($response);
    }

    function handleGetSpecimenReportData($system_config) {
        try {
            $response = [
                "data" => $this->getAllSpecimens($system_config)
            ];
            $this->sendResponse($response);
        } catch (Exception $ex) {
            $this->sendError($ex->getMessage());
        }
    }
}