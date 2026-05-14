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
                "shipment_dashboard_base_url" => $this->getUrl("views/shipment.php"),
                "missing_data_codes" => $this->getMissingDataCodes($this->getSpecimenProject()->project_id)
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
        $dt_d = \Records::getDataTable($this->getBoxProject()->project_id);
        $st_s = \Records::getDataTable($this->getSpecimenProject()->project_id);

        // define some conditional logic
        $box_join_1 = "";
        $box_filter_1 = "";
        $box_filter_2 = "";

        $specimen_cte = "";
        $specimen_join = "";

        if ($exclude_closed) {
            // box project join for box status
            $box_join_1 = "JOIN {$dt_d} b2 ON b1.project_id = b2.project_id AND b1.record = b2.record AND b2.field_name = 'box_status'";
            // where condition for box status
            $box_filter_1 = "AND b2.value = 'available'";
        }
        if (!empty($search)) {
            // specimen project cte
            $specimen_cte = ", a AS (
		SELECT a2.value 'record'
		FROM {$st_s} a1
		JOIN {$st_s} a2 ON a1.project_id = a2.project_id AND a1.record = a2.record AND a2.field_name = 'box_record_id'
		WHERE a1.project_id = ?
		AND a1.field_name = 'specimen_name'
		AND a1.value LIKE ? 
        GROUP BY record
	)";
            // specimen project final join/select
            $specimen_join = "UNION SELECT record FROM a";

            // box filter
            $box_filter_2 = "AND b1.value LIKE ?";

            // updated query params
            $sql_params = [
                $this->getBoxProject()->project_id,
                "%$search%",
                $this->getSpecimenProject()->project_id,
                "%$search%"
            ];
        } else {
            $sql_params = [
                $this->getBoxProject()->project_id,
            ];
        }
        // execute the query
        $sql_result = $this->query("SELECT * FROM (WITH
	b AS (
		SELECT b1.record
		FROM {$dt_d} b1
		{$box_join_1}
		WHERE b1.project_id = ?
		AND b1.field_name = 'box_name'
		{$box_filter_1}
		{$box_filter_2}
    )
    {$specimen_cte}
    SELECT record FROM b
    {$specimen_join}
    GROUP BY record
) as x", $sql_params);
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