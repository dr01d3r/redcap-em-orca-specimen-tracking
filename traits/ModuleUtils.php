<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $this */
namespace ORCA\OrcaSpecimenTracking;

use Exception;

trait ModuleUtils {

    private $_configurations = null;
    private $_project_maps = [];
    private $_current_configuration = null;

    /** @var \Project */
    private $_box_project = null;
    /** @var \Project  */
    private $_specimen_project = null;
    /** @var \Project  */
    private $_shipment_project = null;

    function getFieldTypeForValidation($type, $val)
    {
        switch ($val) {
            case "date_dmy":
            case "date_mdy":
            case "date_ymd":
                $type = "date";
                break;
            case "datetime_dmy":
            case "datetime_mdy":
            case "datetime_ymd":
                $type = "datetime";
                break;
        }
        return $type;
    }

    /**
     * @throws Exception
     */
    function getFieldMetadata($project_name, $field_name): array
    {
        $proj = match ($project_name) {
            "box" => $this->getBoxProject(),
            "specimen" => $this->getSpecimenProject(),
            "shipment" => $this->getShipmentProject(),
            default => throw new Exception("Cannot get field configuration - unknown project name!"),
        };
        $dd_field = $this->getDataDictionary($proj->project_id)[$field_name];
        $field_type = $dd_field["field_type"];
        $field_val = $dd_field["text_validation_type_or_show_slider_number"];
        $field_type = $this->getFieldTypeForValidation($field_type, $field_val);
        return [
            "field_name" => $field_name,
            "project_name" => $project_name,
            "field_label" => trim($dd_field["field_label"]),
            "field_type" => $field_type,
            "required" => $dd_field["required_field"] === "y",
            "choices" => $this->getDictionaryValuesFor($proj->project_id, $field_name),
            "validation" => $field_val
        ];
    }

    /**
     * @throws Exception
     */
    function initConfigurations(): void
    {
        if ($this->_configurations !== null) {
            return;
        }
        $enabled_projects = array_flip($this->getProjectsWithModuleEnabled());
        $system_settings = [];
        // parent
        $system_settings["project_configs"] = $this->getSystemSetting("project_configs");
        // sub_settings
        $system_settings["box_project_id"] = $this->getSystemSetting("box_project_id");
        $system_settings["specimen_project_id"] = $this->getSystemSetting("specimen_project_id");
        $system_settings["shipment_project_id"] = $this->getSystemSetting("shipment_project_id");
        // process each configuration
        foreach ($system_settings["project_configs"] as $k => $v) {
            // project_id's
            $box_project_id = $system_settings["box_project_id"][$k];
            $specimen_project_id = $system_settings["specimen_project_id"][$k];
            $shipment_project_id = $system_settings["shipment_project_id"][$k];
            // get the saved config
            $module_config = json_decode($this->getProjectSetting("module-config", $box_project_id) ?? "{}", true);
            // build the config
            $config = [
                "box_project_id" => $box_project_id,
                "specimen_project_id" => $specimen_project_id,
                "shipment_project_id" => $shipment_project_id,
                "plate_project_enabled" => isset($enabled_projects[$box_project_id]),
                "specimen_project_enabled" => isset($enabled_projects[$specimen_project_id]),
                "shipment_project_enabled" => isset($enabled_projects[$shipment_project_id]),
                // TODO this should be pulled from the module config
                "study_name" => $module_config["general"]["study_name"],
                "box_name_regex" => $module_config["general"]["box_name_regex"],
                "specimen_name_regex" => $module_config["general"]["specimen_name_regex"],
                "errors" => []
            ];

            // do some internal validation
            $projects = array_filter([ $box_project_id, $specimen_project_id, $shipment_project_id ]);
            $distinct_projects = array_unique($projects);
            if (count($projects) !== count($distinct_projects)) {
                $config["errors"][] = "A project is used more than once within this configuration!";
            }
            // ensure projects are selected and module is enabled on each
            if (empty($config["box_project_id"])) {
                $config["errors"][] = "The Box Project configuration is missing!";
            } else if ($config["plate_project_enabled"] == false) {
                $config["errors"][] = "The module has not been enabled on the [<code>$box_project_id</code>] Box Project.";
            }
            if (empty($config["specimen_project_id"])) {
                $config["errors"][] = "The Specimen Project configuration is missing!";
            } else if ($config["specimen_project_enabled"] == false) {
                $config["errors"][] = "The module has not been enabled on the [<code>$specimen_project_id</code>] Specimen Project.";
            }
            if (empty($config["shipment_project_id"])) {
                $config["errors"][] = "The Shipment Project configuration is missing!";
            } else if ($config["shipment_project_enabled"] == false) {
                $config["errors"][] = "The module has not been enabled on the [<code>$shipment_project_id</code>] Shipment Project.";
            }
            // TODO validate somewhere else further downstream to not interrupt the config page itself!
//            if (empty($config["study_name"])) {
//                $config["errors"][] = "Configuration value missing: <code>Study Name</code>";
//            }
//            if (empty($config["box_name_regex"])) {
//                $config["errors"][] = "Configuration value missing: <code>Box Name RegEx</code>";
//            }
//            if (empty($config["specimen_name_regex"])) {
//                $config["errors"][] = "Configuration value missing: <code>Specimen Name RegEx</code>";
//            }

            // build lookup values for later
            if (!empty($config["box_project_id"])) {
                $this->_project_maps[$config["box_project_id"]][$k] = "box_project_id";
            }
            if (!empty($config["specimen_project_id"])) {
                $this->_project_maps[$config["specimen_project_id"]][$k] = "specimen_project_id";
            }
            if (!empty($config["shipment_project_id"])) {
                $this->_project_maps[$config["shipment_project_id"]][$k] = "shipment_project_id";
            }

            // add config to main dataset
            $this->_configurations[$k] = $config;
        }

        // additional loop for cross-config validation
        foreach ($this->_configurations as $k => $config) {
            $ppid = $config["box_project_id"];
            $spid = $config["specimen_project_id"];
            $shid = $config["shipment_project_id"];
            if ($ppid !== null && count($this->_project_maps[$ppid]) > 1) {
                $this->_configurations[$k]["errors"][] =
                    "The Project (id={$ppid}) is referenced in too many configurations."
                ;
            }
            if ($spid !== null && count($this->_project_maps[$spid]) > 1) {
                $this->_configurations[$k]["errors"][] =
                    "The Project (id={$spid}) is referenced in too many configurations."
                ;
            }
            if ($shid !== null && count($this->_project_maps[$shid]) > 1) {
                $this->_configurations[$k]["errors"][] =
                    "The Project (id={$shid}) is referenced in too many configurations."
                ;
            }
        }

        if (empty($this->_configurations) || count($this->_configurations) === 0) {
            throw new Exception("No configurations exist.");
        }
    }

    /**
     * @param array $config
     * @throws Exception
     */
    function setConfigProjectContext(array $config) {
        if ($this->_current_configuration != null) {
            throw new Exception("Configuration context was already set and cannot be changed.");
        }
        // only set context if the configuration is valid
        if (count($config["errors"]) > 0
            || $config["plate_project_enabled"] !== true
            || $config["specimen_project_enabled"] !== true
            || $config["shipment_project_enabled"] !== true
        ) {
            throw new Exception("Cannot set configuration context using an invalid configuration.");
        }
        $this->_current_configuration = $config;
        $this->_box_project = new \Project($config["box_project_id"]);
        $this->_specimen_project = new \Project($config["specimen_project_id"]);
        $this->_shipment_project = new \Project($config["shipment_project_id"]);
    }

    /**
     * @param int $project_id
     * @return array
     * @throws Exception
     */
    function getConfiguration(int $project_id) : array {
        $this->initConfigurations();
        $result = [];
        $maps = $this->_project_maps[$project_id] ?? [];

        if (empty($maps)) {
            // no usage
            $result["errors"][] = "This Project [id=$project_id] is not referenced in any configurations for use in this module.";
        } else if (count($maps) > 1) {
            // too much usage!
            $result["errors"][] = "This Project [id=$project_id] is referenced in more than one configuration.";
        } else {
            // just right :)
            reset($maps);
            $k = key($maps);
            $result = $this->_configurations[$k];
            // any other errors (i.e. if the partner project is referenced too many times)
            // will already exist in the config's errors array
        }
        return $result;
    }

    /**
     * Get the Box Project, if context has been established. Should never be called before
     * $module->setConfigProjectContext()
     * @return \Project
     * @throws Exception
     */
    function getBoxProject(): \Project
    {
        if ($this->_box_project === null) {
            throw new Exception("Box project context has not yet been established");
        }
        return $this->_box_project;
    }

    /**
     * Get the Specimen Project, if context has been established. Should never be called before
     * $module->setConfigProjectContext()
     * @return \Project
     * @throws Exception
     */
    function getSpecimenProject(): \Project
    {
        if ($this->_specimen_project === null) {
            throw new Exception("Specimen project context has not yet been established");
        }
        return $this->_specimen_project;
    }

    /**
     * Get the Shipment Project, if context has been established. Should never be called before
     * $module->setConfigProjectContext()
     * @return \Project
     * @throws Exception
     */
    function getShipmentProject(): \Project
    {
        if ($this->_shipment_project === null) {
            throw new Exception("Shipment project context has not yet been established");
        }
        return $this->_shipment_project;
    }

}
