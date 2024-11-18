<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $this */
namespace ORCA\OrcaSpecimenTracking;

use JetBrains\PhpStorm\NoReturn;

trait RequestHandlers {
    function redcap_module_ajax($action, $payload, $project_id) {
        $response = [
            "errors" => []
        ];
        try {
            // get system configuration
            $system_config = $this->getConfiguration($project_id);
            // if any errors
            if (!empty($system_config["errors"])) {
                // end the request and send back the error(s)
                $response["errors"] = $system_config["errors"];
            } else {
                // establish context
                $this->setConfigProjectContext($system_config);
                switch ($action) {
                    case "initialize-config-dashboard":
                        $response = $this->handleInitializeConfigDashboard($system_config);
                        break;
                    case "initialize-box-dashboard":
                        $response = $this->handleInitializeBoxDashboard();
                        break;
                    case "initialize-shipment-dashboard":
                        $response = $this->handleInitializeShipmentDashboard($system_config, $payload);
                        break;
                    case "save-module-config":
                        $response = $this->handleSaveModuleConfig($payload);
                        break;
                    case "get-report-data":
                        $response = $this->handleGetReportData();
                        break;
                    case "get-box":
                        $response = $this->handleGetBox($system_config, $payload);
                        break;
                    case "get-box-list":
                        $response = $this->handleGetBoxList($system_config);
                        break;
                    case "search-box-list":
                        $response = $this->handleSearchBoxList($system_config, $payload);
                        break;
                    case "get-specimen":
                        $response = $this->handleGetSpecimen($payload['specimen_record_id'], $system_config);
                        break;
                    case "search-specimen":
                        $response = $this->handleSearchSpecimen($payload['search_value'], $system_config);
                        break;
                    case "save-specimen":
                        $response = $this->handleSaveSpecimen($payload['specimen']);
                        break;
                    case "delete-specimen":
                        $response = $this->handleDeleteSpecimen($payload['specimen_record_id']);
                        break;
                    case "search-shipments":
                        $response = $this->handleSearchShipments();
                        break;
                    case "complete-shipment":
                        $response = $this->handleCompleteShipment($payload['shipment_record_id'], $system_config);
                        break;
                    case "update-box-shipment":
                        $response = $this->handleUpdateBoxShipment($payload['box_record_id'], $payload['shipment_record_id'], $system_config);
                        break;
                    default:
                        $response["errors"][] = "Unable to process request. Action '$action' is invalid.";
                        break;
                }
            }
        } catch (\Exception $ex) {
            $response["errors"][] = $ex->getMessage();
        }
        return $response;
    }

    /**
     * Echoes successful JSON response
     *
     * @param mixed $response
     * @return void
     */
    public function sendResponse($response) : void
    {
        header('Content-Type: application/json; charset=UTF-8');
        exit(json_encode($response));
    }

    /**
     * Echoes error JSON response
     *
     * @param mixed $error Optional error details.  Will be JSON encoded.
     * @return void
     */
    public function sendError($error = "") : void
    {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(400);
        exit(json_encode($this->escape($error)));
    }

    /**
     * Unauthorized error
     *
     * @param mixed $error Optional error details.  Will be JSON encoded.
     * @return void
     */
    public function sendUnauthorized($error = "") : void
    {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(401);
        exit(json_encode($this->escape($error)));
    }
}