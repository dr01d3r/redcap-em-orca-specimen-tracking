<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $this */
namespace ORCA\OrcaSpecimenTracking;

use Exception;

trait PlateUtils {

    /**
     * @param string $box_id
     * @return array
     * @throws Exception
     */
    function getBox(string $box_id)
    {
        if (!is_numeric($box_id)) return [];
        return $this->getBoxes($box_id)[$box_id];
    }

    /**
     * @param $box_ids
     * @return array
     * @throws Exception
     */
    function getBoxes($box_ids): array
    {
        if (!is_array($box_ids)) {
            $box_ids = [ $box_ids ];
        }
        $results = [];
        $project = $this->getBoxProject();
        // get all plate info by record
        try {
            $records = \REDCap::getData([
                "project_id" => $project->project_id,
                "records" => $box_ids
            ]);
            // process the records
            foreach ($records as $record_id => $record) {
                $results[$record_id] = $record[$project->firstEventId];
            }
        } catch (Exception $ex) {}
        return $results;
    }

    function parsePlateName($name, $regex) : ?array {
        if (empty($name) || empty($regex)) {
            return null;
        }
        $result = [];
        if (stripos($regex, '/') === false) $regex = "/$regex/";
        if (preg_match($regex, $name, $matches, PREG_UNMATCHED_AS_NULL)) {
            $result = array_filter($matches, function ($v, $k) {
                return !is_numeric($k);
            }, ARRAY_FILTER_USE_BOTH);
        }
        return $result;
    }

    /* REQUEST HANDLERS */

    function handleInitializeBoxDashboard(): array
    {
        $response = [
            "config" => [],
            "errors" => []
        ];
        try {
            // get module config, if it exists
            list($metadata, $state) = $this->getModuleConfig();
            // some config entries should be omitted or given default value based on box_type
            $response["config"] = [
                "general" => $state["general"],
                "save-state" => $state["fields"] ?? [],
                "fields" => $metadata ?? [],
                "validation" => getValTypes(),
                "alphabet" => range('A', 'Z'),
                "shipment_dashboard_base_url" => $this->getUrl("views/shipment.php")
            ];

            // prep new box url
            $new_box_id = \DataEntry::getAutoId($this->getBoxProject()->project_id);
            $new_box_url = APP_PATH_WEBROOT . "DataEntry/index.php?" . http_build_query([
                    "pid" => $this->getBoxProject()->project_id,
                    "id" => $new_box_id,
                    "event_id" => $this->getBoxProject()->firstEventId,
                    "page" => $this->getBoxProject()->firstForm,
                    "auto" => "1"
                ]);
            $response["config"]["new_box_url"] = $new_box_url;
            $this->addTime("initialization finished");
        } catch (Exception $ex) {
            $response["errors"][] = $ex->getMessage();
        }
        // send it back!
        return $response;
    }

    function handleGetBox(array $system_config, $payload): array
    {
        $response = [
            "box" => [],
            "config" => [],
            "errors" => []
        ];
        try {
            // get box context if specified
            if (!empty($payload["id"]) && is_numeric($payload["id"])) {
                // get the box data
                $box = $this->getBox($payload["id"]);
                if (!empty($box)) {
                    $response["box"] = $box;
                    // get the specimen data
                    $response["specimens"] = $this->getSpecimensForBox($payload["id"]);
                }
                // get record_home url
                $box_record_home_url = APP_PATH_WEBROOT . "DataEntry/record_home.php?" . http_build_query([
                        "pid" => $this->getBoxProject()->project_id,
                        "id" => $payload["id"]
                    ]);
                $response["config"]["box_record_home_url"] = $box_record_home_url;
            }
            $this->addTime("initialization finished");
        } catch (Exception $ex) {
            $response["errors"][] = $ex->getMessage();
        }
        return $response;
    }

    function handleGetBoxList(array $system_config): array
    {
        $response = [
            "boxes" => [],
            "errors" => []
        ];
        try {
            $response["boxes"] = $this->getBoxList();
        } catch (Exception $ex) {
            $response["errors"][] = $ex->getMessage();
        }
        // send it back!
        return $response;
    }
    function handleSearchBoxList(array $system_config, array $payload): array
    {
        $response = [
            "search" => $payload["search"],
            "boxes" => [],
            "errors" => []
        ];
        try {
            $response["boxes"] = $this->getBoxList(false, $payload["search"]);
        } catch (Exception $ex) {
            $response["errors"][] = $ex->getMessage();
        }
        // send it back!
        return $response;
    }

    /**
     * @throws Exception
     */
    function getBoxList(bool $exclude_closed = true, $search = null): array
    {
        $boxes = [];
        // get the data_table context
        $dt_d = $this->getBoxProject()->project["data_table"];
        $st_s = $this->getSpecimenProject()->project["data_table"];
        // define some conditional logic
        $sql_joins_1 = "";
        $sql_joins_2 = "";
        $sql_filter_1 = "";
        $sql_filter_2 = "";
        if ($exclude_closed) {
            // box project join for box status
            $sql_joins_1 = "JOIN {$dt_d} d2 ON d1.project_id = d2.project_id AND d1.record = d2.record AND d2.field_name = 'box_status'";
            // where condition for box status
            $sql_filter_1 = "AND d2.value = 'available'";
        }
        if (!empty($search)) {
            // include the specimen project joins
            $sql_joins_2 = "LEFT JOIN {$st_s} s1 ON s1.project_id = ? AND s1.field_name = 'box_record_id' AND d1.record = s1.value
LEFT JOIN {$st_s} s2 ON s1.project_id = s2.project_id AND s1.record = s2.record AND s2.field_name = 'specimen_name'";
            // where condition for box name and specimen name search
            $sql_filter_2 = "AND (d1.value LIKE ? OR s2.value LIKE ?)";
            $sql_params = [
                $this->getSpecimenProject()->project_id,
                $this->getBoxProject()->project_id,
                "%$search%",
                "%$search%"
            ];
        } else {
            $sql_params = [
                $this->getBoxProject()->project_id,
            ];
        }
        // execute the query
        $sql_result = $this->query("SELECT d1.record
FROM {$dt_d} d1
{$sql_joins_1}
{$sql_joins_2}
WHERE d1.project_id = ?
AND d1.field_name = 'box_name'
{$sql_filter_1}
{$sql_filter_2}
GROUP BY d1.record", $sql_params);
        // use the record_ids to grab all the box data
        $records = [];
        while ($r = db_fetch_assoc($sql_result)) {
            $records[] = $r["record"];
        }
        if (empty($search) || count($records) > 0) {
            $boxes = array_values($this->getBoxes($records));
        }
        // send it back!
        return $boxes;
    }
}