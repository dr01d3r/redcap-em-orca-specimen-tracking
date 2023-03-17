<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $this */
namespace ORCA\OrcaSpecimenTracking;

use Exception;

trait ModuleUtils {

    private $_configurations = null;
    private $_project_maps = [];

    private $_enabled_projects = null;
    private $_current_configuration = null;

    /** @var \Project */
    private $_plate_project = null;
    /** @var \Project  */
    private $_specimen_project = null;
    /** @var \Project  */
    private $_shipment_project = null;

    /**
     * @return array
     * @throws Exception
     */
    function getAllConfigurations() : array {
        if ($this->_configurations == null) {
            $this->initConfigurations();
        }
        return $this->_configurations;
    }

    /**
     * @throws Exception
     */
    function initConfigurations() {
        if ($this->_configurations !== null) {
            return;
        }
        $this->_enabled_projects = array_flip($this->getProjectsWithModuleEnabled());
        $system_settings = [];
        // parent
        $system_settings["project_configs"] = $this->getSystemSetting("project_configs");
        // sub_settings
        $system_settings["study_name"] = $this->getSystemSetting("study_name");
        $system_settings["plate_project_id"] = $this->getSystemSetting("plate_project_id");
        $system_settings["specimen_project_id"] = $this->getSystemSetting("specimen_project_id");
        $system_settings["shipment_project_id"] = $this->getSystemSetting("shipment_project_id");
        $system_settings["plate_size"] = $this->getSystemSetting("plate_size");
        $system_settings["use_temp_box_type"] = $this->getSystemSetting("use_temp_box_type");
        $system_settings["num_visits"] = $this->getSystemSetting("num_visits");
        $system_settings["num_specimens"] = $this->getSystemSetting("num_specimens");
        $system_settings["default_volume"] = $this->getSystemSetting("default_volume");
        $system_settings["datetime_format"] = $this->getSystemSetting("datetime_format");
        $system_settings["box_name_regex"] = $this->getSystemSetting("box_name_regex");
        $system_settings["specimen_name_regex"] = $this->getSystemSetting("specimen_name_regex");
        $system_settings["collected_to_processed_minutes_max"] = $this->getSystemSetting("collected_to_processed_minutes_max");
        // process each configuration
        foreach ($system_settings["project_configs"] as $k => $v) {
            // build initial config entry
            $study_name = $system_settings["study_name"][$k];
            $plate_project_id = $system_settings["plate_project_id"][$k];
            $specimen_project_id = $system_settings["specimen_project_id"][$k];
            $shipment_project_id = $system_settings["shipment_project_id"][$k];
            $config = [
                "study_name" => $study_name,
                "plate_project_id" => $plate_project_id,
                "specimen_project_id" => $specimen_project_id,
                "shipment_project_id" => $shipment_project_id,
                "plate_project_enabled" => isset($this->_enabled_projects[$plate_project_id]),
                "specimen_project_enabled" => isset($this->_enabled_projects[$specimen_project_id]),
                "shipment_project_enabled" => isset($this->_enabled_projects[$shipment_project_id]),
                "plate_size" => $system_settings["plate_size"][$k],
                "default_volume" => $system_settings["default_volume"][$k],
                "datetime_format" => $system_settings["datetime_format"][$k],
                "box_name_regex" => $system_settings["box_name_regex"][$k],
                "specimen_name_regex" => $system_settings["specimen_name_regex"][$k],
                "use_temp_box_type" => $system_settings["use_temp_box_type"][$k] === true,
                "collected_to_processed_minutes_max" => $system_settings["collected_to_processed_minutes_max"][$k],
                "errors" => []
            ];

            // do some internal validation
            $projects = array_filter([ $plate_project_id, $specimen_project_id, $shipment_project_id ]);
            $distinct_projects = array_unique($projects);
            if (count($projects) !== count($distinct_projects)) {
                $config["errors"][] = "A project is used more than once within this configuration!";
            }
            if (empty($config["study_name"])) {
                $config["errors"][] = "Configuration value missing: <code>Study Name</code>";
            }
            // ensure projects are selected and module is enabled on each
            if (empty($config["plate_project_id"])) {
                $config["errors"][] = "The Box Project configuration is missing!";
            } else if ($config["plate_project_enabled"] == false) {
                $config["errors"][] = "The module has not been enabled on the [<code>$plate_project_id</code>] Box Project.";
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
            if (empty($config["plate_size"])) {
                $config["errors"][] = "Configuration value missing: <code>Box Size</code>";
            }
            if (!empty($config["collected_to_processed_minutes_max"])) {
                if (!is_numeric($config["collected_to_processed_minutes_max"]) || $config["collected_to_processed_minutes_max"] <= 0) {
                    $config["errors"][] = "Configuration value <code>[collected_to_processed_minutes_max]</code> invalid.  Must be numeric and greater than zero!";
                }
            }
            // only include/validate these fields if [use_temp_box_type] was checked
            if ($config["use_temp_box_type"]) {
                $config["num_visits"] = $system_settings["num_visits"][$k];
                $config["num_specimens"] = $system_settings["num_specimens"][$k];
                if (empty($config["num_visits"])) {
                    $config["errors"][] = "Configuration value missing: <code>Total Visits</code>";
                }
                if (empty($config["num_specimens"])) {
                    $config["errors"][] = "Configuration value missing: <code>Specimens Per Visit</code>";
                }
            }
            if (empty($config["box_name_regex"])) {
                $config["errors"][] = "Configuration value missing: <code>Box Name RegEx</code>";
            }
            if (empty($config["specimen_name_regex"])) {
                $config["errors"][] = "Configuration value missing: <code>Specimen Name RegEx</code>";
            }

            // build lookup values for later
            if (!empty($config["plate_project_id"])) {
                $this->_project_maps[$config["plate_project_id"]][$k] = "plate_project_id";
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
            $ppid = $config["plate_project_id"];
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
        $this->_plate_project = new \Project($config["plate_project_id"]);
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

            // data dictionary validation
            if (empty($result["errors"])) {
                // fields exist and are correct field types
                // TODO array_diff required fields array with project fields
            }
        }
        return $result;
    }

    /**
     * Get the Plate Project, if context has been established. Should never be called before
     * $module->setConfigProjectContext()
     * @return \Project
     * @throws Exception
     */
    function getPlateProject() {
        if ($this->_plate_project === null) {
            throw new Exception("Plate project context has not yet been established");
        }
        return $this->_plate_project;
    }

    /**
     * Get the Specimen Project, if context has been established. Should never be called before
     * $module->setConfigProjectContext()
     * @return \Project
     * @throws Exception
     */
    function getSpecimenProject() {
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
    function getShipmentProject() {
        if ($this->_shipment_project === null) {
            throw new Exception("Shipment project context has not yet been established");
        }
        return $this->_shipment_project;
    }

}
