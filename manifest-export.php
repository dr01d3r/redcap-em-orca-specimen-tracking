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

    // ensure a shipment record_id was provided
    if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
        $module->sendError("Cannot export manifest - shipment_id is invalid or missing.");
    }

    // get the module config
    $module_config = $module->getModuleConfig() ?? [];

    // get the data
    [ $shipment, $data ] = $module->getShipmentManifestData($_GET["id"]);

    // dump data to csv
    $temp_path = $module->generateTempFileName(5);
    $temp_output = fopen($temp_path, 'c');
    // headers
    $file_headers = array_map(function($k) use ($module_config) {
        $tmp = $k;
        // TODO switch to use defined values in module config
//        switch ($k) {
//            case "volume":
//                $tmp = "$sample_type ($sample_unit)";
//                break;
//        }
        return $tmp;
    }, array_keys(reset($data)));
    fputcsv($temp_output, $file_headers);
    // rows
    foreach ($data as $i => $row) {
        fputcsv($temp_output, $row);
    }
    // close the stream
    fclose($temp_output);
    // filename
    $file_name = "manifest-" . date("Ymd_His") . ".csv";
    // output file
    $module->downloadFile($temp_path, $file_name);
} catch (Exception $ex) {
    $module->sendError($ex->getMessage());
}