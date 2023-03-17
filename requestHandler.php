<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $module */

try {
    // get system configuration
    $system_config = $module->getConfiguration($module->getProjectId());
    // if any errors
    if (!empty($system_config["errors"])) {
        // end the request and send back the error(s)
        $module->sendError($system_config["errors"]);
    } else {
        // establish context
        $module->setConfigProjectContext($system_config);
    }

    // action: 'initialize-box-dashboard'
    if ($_REQUEST['action'] == 'initialize-box-dashboard') {
        $module->initializeBoxDashboard($system_config);
    }
    // action: 'initialize-shipment-dashboard'
    else if ($_REQUEST['action'] == 'initialize-shipment-dashboard') {
        $module->initializeShipmentDashboard($system_config);
    }
    // action: 'initialize-report-dashboard'
    else if ($_REQUEST['action'] == 'initialize-report-dashboard') {
        $module->initializeReportDashboard($system_config);
    }
    // action: 'get-specimen-report-data'
    else if ($_REQUEST['action'] == 'get-specimen-report-data') {
        $module->handleGetSpecimenReportData($system_config);
    }
    // action: 'search-plate'
    else if ($_POST['action'] == 'search-plate') {
        if (isset($_POST['include_specimens'])) {
            $module->searchPlate($_POST['search_value'], $system_config, $_POST['include_specimens'] == "true");
        } else {
            $module->searchPlate($_POST['search_value'], $system_config);
        }
    }
    // action: 'get-specimen'
    else if ($_POST['action'] == 'get-specimen') {
        $module->getSpecimen($_POST['specimen_record_id'], $system_config);
    }
    // action: 'search-specimen'
    else if ($_POST['action'] == 'search-specimen') {
        $module->searchSpecimen($_POST['search_value'], $system_config);
    }
    // action: 'save-specimen'
    else if ($_POST['action'] == 'save-specimen') {
        $module->saveSpecimen($_POST['specimen'], $system_config);
    }
    // action: 'delete-specimen'
    else if ($_POST['action'] == 'delete-specimen') {
        $module->deleteSpecimen($_POST['specimen_record_id']);
    }
    // action: 'search-shipments'
    else if ($_POST['action'] == 'search-shipments') {
        $module->searchShipments();
    }
    // action: 'complete-shipment'
    else if ($_POST['action'] == 'complete-shipment') {
        $module->handleCompleteShipment($_POST['shipment_record_id'], $system_config);
    }
    // action: 'update-box-shipment'
    else if ($_POST['action'] == 'update-box-shipment') {
        $module->updateBoxShipment($_POST['box_record_id'], $_POST['shipment_record_id'], $system_config);
    }
    // action: 'validate-csid'
    else if ($_POST['action'] == 'validate-csid') {
        $module->handleValidateCSID($_POST['specimen'], $_POST['csid'], $system_config);
    }
    // action: 'validate-cuid'
    else if ($_POST['action'] == 'validate-cuid') {
        $module->handleValidateCUID($_POST['cuid']);
    }
    // unknown action
    else {
        header("HTTP/1.1 400 Bad Request");
        header('Content-Type: application/json; charset=UTF-8');
        die("The action does not exist.");
    }
} catch (Exception $ex) {
    $module->sendError($ex->getMessage());
}