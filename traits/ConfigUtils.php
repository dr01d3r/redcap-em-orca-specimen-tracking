<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $this */
namespace ORCA\OrcaSpecimenTracking;

use Exception;

trait ConfigUtils {
    /**
     * @throws Exception
     */
    function handleInitializeConfigDashboard($system_config): array
    {
        $response = [
            "projects" => [],
            "state" => [],
            "metadata" => [],
            "errors" => []
        ];
        try {
            // let's set some project metadata
            $response["projects"] = [
                "specimen" => [
                    "project_id" => $this->getSpecimenProject()->project_id,
                    "app_title" => $this->getSpecimenProject()->project['app_title'],
                ],
                "box" => [
                    "project_id" => $this->getBoxProject()->project_id,
                    "app_title" => $this->getBoxProject()->project['app_title'],
                ],
                "shipment" => [
                    "project_id" => $this->getShipmentProject()->project_id,
                    "app_title" => $this->getShipmentProject()->project['app_title'],
                ]
            ];
            // pull the module config, if it exists
            list($metadata, $state) = $this->getModuleConfig();

            // set in the response
            $response["metadata"] = $metadata;
            $response["state"] = $state;

        } catch (Exception $ex) {
            $response["errors"][] = $ex->getMessage();
        }
        return $response;
    }

    function handleSaveModuleConfig($payload): array
    {
        $response = [
            "errors" => []
        ];
        try {
            // set the project context to save the hidden project setting
            $project_id = $this->getBoxProject()->project_id;
            // save the entire payload
            $this->setProjectSetting("module-config", json_encode($payload), $project_id);
        } catch (Exception $ex) {
            $response["errors"][] = $ex->getMessage();
        }
        // return the response
        return $response;
    }

    private function getFieldMetadataConfig($field_name, $project_name): array
    {
        // initialize a default config
        $config = [
            "specimen-dashboard"  => [ "enabled" => false, "required" => false ],
            "specimen-entry-form" => [ "enabled" => false, "required" => false ],
            "batch-mode"          => [ "enabled" => false, "required" => false ],
            "specimen-list"       => [ "enabled" => false, "required" => false ],
            "reporting-table"     => [ "enabled" => false, "required" => false ],
            "shipment-list"       => [ "enabled" => false, "required" => false ],
            "shipment-box-list"   => [ "enabled" => false, "required" => false ],
            "shipment-manifest"   => [ "enabled" => false, "required" => false ],
        ];        
        // enable based on project
        switch ($project_name) {
            case "box":
                $config["specimen-dashboard"]["enabled"] = true;
                $config["reporting-table"]["enabled"] = true;
                $config["shipment-box-list"]["enabled"] = true;
                $config["shipment-manifest"]["enabled"] = true;
                break;
            case "specimen":
                // ensure infrastructure fields are not enabled here
                if (!in_array($field_name, [ "record_id", "box_record_id", "box_position" ])) {
                    $config["specimen-entry-form"]["enabled"] = true;
                    if (!in_array($field_name, [ "specimen_name" ])) {
                        $config["batch-mode"]["enabled"] = true;
                    }
                }
                $config["specimen-list"]["enabled"] = true;
                $config["reporting-table"]["enabled"] = true;
                $config["shipment-manifest"]["enabled"] = true;
                break;
            case "shipment":
                $config["reporting-table"]["enabled"] = true;
                $config["shipment-list"]["enabled"] = true;
                $config["shipment-manifest"]["enabled"] = true;
                break;
        }
        // required based on project and field
        switch ("$project_name.$field_name") {
            // specimen
            case "specimen.specimen_name":
                $config["specimen-entry-form"]["required"] = true;
                $config["specimen-list"]["required"] = true;
                break;
            case "specimen.box_position":
                $config["specimen-list"]["required"] = true;
                break;
            // box
            // ...
            // shipment
            // ...
        }
        
        return $config;
    }

    /**
     * @throws Exception
     */
    private function getFieldConfigForProject($project_name, $module_config): array
    {
        $response = [
            "metadata" => [],
            "config" => []
        ];
        // prep some metadata for building the dataset
        $valTypes = getValTypes();
        $exclude_types = [ "calc", "file", "slider", "descriptive", "sql" ];
        $proj = match ($project_name) {
            "box" => $this->getBoxProject(),
            "specimen" => $this->getSpecimenProject(),
            "shipment" => $this->getShipmentProject(),
            default => throw new Exception("Cannot get field configuration - unknown project name!"),
        };
        foreach ($proj->metadata as $field_name => $f) {
            // exclude unsupported field types
            if ($proj->isFormStatus($field_name) || in_array($f["element_type"], $exclude_types)) continue;
            // load the metadata
            $metadata = $this->getFieldMetadata($project_name, $field_name);
            // pull in validation metadata
            if (!empty($metadata["validation"])) {
                $metadata["validation"] = [
                    "type" => $metadata["validation"],
                    "label" => $valTypes[$metadata["validation"]]["validation_label"],
                    "regex" => $valTypes[$metadata["validation"]]["regex_js"]
                ];
            }
            // initialize default config
            $metadata["config"] = $this->getFieldMetadataConfig($field_name, $project_name);
            // initialize default values, pulling saved values, if they exist
            $field_config = [
                "specimen-entry-form" => $module_config[$field_name]["specimen-entry-form"] ?? false,
                "extras" => [],
                "field-default" => $module_config[$field_name]["field-default"],
                "field-units" => $module_config[$field_name]["field-units"],
                "batch-mode" => $module_config[$field_name]["batch-mode"] ?? false,
                "specimen-list" => $module_config[$field_name]["specimen-list"] ?? false,
                "specimen-dashboard" => $module_config[$field_name]["specimen-dashboard"] ?? false,
                "reporting-table" => $module_config[$field_name]["reporting-table"] ?? false,
                "shipment-list" => $module_config[$field_name]["shipment-list"] ?? false,
                "shipment-box-list" => $module_config[$field_name]["shipment-box-list"] ?? false,
                "shipment-manifest" => $module_config[$field_name]["shipment-manifest"] ?? false,
            ];
            // sync up default selections based on metadata config
            foreach ($metadata["config"] as $mc => $mi) {
                if ($mi["enabled"] && $mi["required"]) {
                    $field_config[$mc] = true;
                }
            }
            // add additional custom validation rules for specimen project
            // be sure to ignore specific infrastructure fields
            if ($project_name === "specimen" && !in_array($field_name, [ "record_id", "box_record_id", "box_position" ])) {
                // custom rules for "text" fields
                if ($metadata["field_type"] === "text") {
                    // [specimen_name] gets a special rule, and other text fields get the rest
                    if ($field_name === "specimen_name") {
                        // specimen match & prefill based on nomenclature named capture groups
                        $field_config["extras"]["matchPrefill"] = [
                            "enabled" => $module_config[$field_name]["extras"]["matchPrefill"]["enabled"] ?? false,
                            "groups" => $module_config[$field_name]["extras"]["matchPrefill"]["groups"] ?? [],
                            "fields" => $module_config[$field_name]["extras"]["matchPrefill"]["fields"] ?? [],
                        ];
                    } else {
                        $field_config["extras"]["confirm"] = [
                            "enabled" => $module_config[$field_name]["extras"]["confirm"]["enabled"] ?? false,
                        ];
                    }
                }
                // custom rules for "datetime" fields
                if (in_array($metadata["field_type"], [ "datetime" ])) {
                    // noFuture
                    $field_config["extras"]["noFuture"] = [
                        "enabled" => $module_config[$field_name]["extras"]["noFuture"]["enabled"] ?? false
                    ];
                    // afterDate
                    $field_config["extras"]["afterDate"] = [
                        "enabled" => $module_config[$field_name]["extras"]["afterDate"]["enabled"] ?? false,
                        "target" => $module_config[$field_name]["extras"]["afterDate"]["target"] ?? "",
                        "minimum" => $module_config[$field_name]["extras"]["afterDate"]["minimum"] ?? "",
                        "maximum" => $module_config[$field_name]["extras"]["afterDate"]["maximum"] ?? "",
                        "warningOnly" => $module_config[$field_name]["extras"]["afterDate"]["warningOnly"] ?? false
                    ];
                }
            }
            // add metadata to response
            $response["metadata"][$field_name] = $metadata;
            // add field config to response
            $response["config"][$field_name] = $field_config;
        }
        return $response;
    }
    
    /**
     * @throws Exception
     */
    function getModuleConfig(): array
    {
        $state = [
            "general" => [],
            "fields" => [
                "box" => [],
                "specimen" => [],
                "shipment" => []
            ]
        ];
        $metadata = [
            "box" => [],
            "specimen" => [],
            "shipment" => []
        ];
        // set the project context to save the hidden project setting
        $project_id = $this->getBoxProject()->project_id;
        // get the saved config
        $module_config = json_decode($this->getProjectSetting("module-config", $project_id) ?? "{}", true);

        // GENERAL
        $state["general"] = [
            "study_name" => $module_config["general"]["study_name"],
            "box_name_regex" => $module_config["general"]["box_name_regex"],
            "specimen_name_regex" => $module_config["general"]["specimen_name_regex"],
            "warning_ack_field" => $module_config["general"]["warning_ack_field"] ?? "",
        ];

        // PROJECT LOOP
        foreach ([ "specimen", "box", "shipment" ] as $project_name) {
            $field_config_specimen = $this->getFieldConfigForProject($project_name, $module_config["fields"][$project_name]);
            // add metadata to response
            $metadata[$project_name] = $field_config_specimen["metadata"];
            // add field config to response
            $state["fields"][$project_name] = $field_config_specimen["config"];
        }

        return [ $metadata, $state ];
    }
}