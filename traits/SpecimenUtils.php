<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $this */
namespace ORCA\OrcaSpecimenTracking;

use Exception;

trait SpecimenUtils {
    private $_allowed_specimen_name_parts = [
        "year"              => "__YEAR__",
        "participant_id"    => "__PARTICIPANT_ID__",
        "visit"             => 0,
        "sample_type"       => "__SAMPLE_TYPE__",
        "aliquot_number"    => "__ALIQUOT_NUMBER__"
    ];

    /**
     * @param $plate_id
     * @param string $regex
     * @return array
     * @throws Exception
     */
    function getSpecimens($plate_id, string $regex = null) {
        if (!is_numeric($plate_id)) return [];
        $specimen_project = $this->getSpecimenProject();
        // get all plate info by record
        $records = \REDCap::getData([
            "project_id" => $specimen_project->project_id,
            "filterLogic" => "[box_record_id] = $plate_id"
        ]);
        return array_values(array_map(function($record) use ($specimen_project, $regex) {
            $tmp = $record[$specimen_project->firstEventId];
            $tmp["name_parsed"] = $this->parseSpecimenName($tmp["name"], $regex);
            return $tmp;
        }, $records));
    }

    function parseSpecimenName($name, $regex) : array {
        $result = [];
        if (stripos($regex, '/') === false) $regex = "/$regex/";
        if (preg_match($regex, $name, $matches, PREG_UNMATCHED_AS_NULL)) {
            $result = array_intersect_key($matches, $this->_allowed_specimen_name_parts);
        }
        return $result;
    }

    function getSpecimenFields($exclude_system_fields = true) {
        $fields = array_column($this->getSpecimenProject()->metadata, "element_label", "field_name");
        if ($exclude_system_fields === true) {
            $fields = array_filter($fields,
                function($k) {
                    return !in_array($k, [ "record_id", "box_record_id", "box_position", "name_parsed" ]) && !$this->getSpecimenProject()->isFormStatus($k);
                }, ARRAY_FILTER_USE_KEY);
        }
        return $fields;
    }

    /**
     * @param array $system_config
     * @return array
     * @throws Exception
     */
    function getAllSpecimens(array $system_config) {
        $result = [];
        // get basic box info
        $box_data = \REDCap::getData([
            "project_id" => $this->getPlateProject()->project_id,
            "records" => [],
            "fields" => [
                "box_name"
            ]
        ]);
        // get the specimen info
        $specimen_data = \REDCap::getData([
            "project_id" => $this->getSpecimenProject()->project_id,
            "records" => []
        ]);
        foreach ($specimen_data as $record_id => $data) {
            $specimen = $data[$this->getSpecimenProject()->firstEventId];
//            $specimen["name_parsed"] = $this->parseSpecimenName($specimen["name"], $system_config["specimen_name_regex"]);
            // get box_name, if we can find it
            if (!empty($specimen["box_record_id"]) && isset($box_data[$specimen["box_record_id"]])) {
                $specimen["box_name"] = $box_data[$specimen["box_record_id"]][$this->getPlateProject()->firstEventId]["box_name"];
            }
            foreach ($specimen as $field_name => $field_value) {
                $specimen[$field_name] = $this->getFieldDisplayValue($this->getSpecimenProject(), $field_name, $field_value);
            }
            $result[] = $specimen;
        }
        return $result;
    }


    /**
     * @param string $specimen The full specimen name that wants to use the CSID
     * @param string $csid The CSID value being scanned/checked
     * @param array $system_config
     * @return array|bool Returns TRUE if the CSID can be used with the provided specimen name, FALSE otherwise.
     * @throws Exception
     */
    function validateCSID(string $specimen, string $csid, array $system_config) {
        if (empty($csid)) return true;
        if (empty($specimen)) {
            throw new Exception("Unable to validate CSID due to missing specimen name.");
        }

        $is_valid = true;
        $errors = [];

        // get the baseline specimen we want to ensure is valid
        $s = $this->parseSpecimenName($specimen, $system_config["specimen_name_regex"]);

        /* ===========================
           CHECK #1 - ALIGNED SIBLINGS
           =========================== */
        $patterns = [];
        $replacements = [];
        // grab siblings of the scanned specimen, and ensure CSID is a match
        foreach ($s as $k => $v) {
            // all parts except aliquot_number must match to be a 'sibling'
            if ($k === "aliquot_number") continue;
            // # delimits the pattern since we need to treat the () as literals
            $patterns[] = "#\(\?\<$k\>.*?\)#";
            $replacements[] = $v;
        }
        // https://www.php.net/manual/en/function.preg-replace
        // If there are fewer elements in the replacement array than in the pattern array, any extra patterns will be replaced by an empty string.
        // the last pattern clears out any remaining capture group syntax that isn't sql compatible
        // this will not have a matching replacement element as we just want it cleared out
        $patterns[] = "(\?\<\w*\>)";

        $sql_name_regex = preg_replace($patterns, $replacements, $system_config["specimen_name_regex"]);
        $sql_1 = "SELECT d1.record, d1.value 'name', d2.value 'csid'
FROM redcap_data d1
JOIN redcap_data d2 ON d1.project_id = d2.project_id AND d1.record = d2.record AND d2.field_name = 'csid'
WHERE d1.project_id = ?
AND d1.field_name = 'name'
AND d1.value <> ?
AND d1.value REGEXP ?";
        $qr_1 = $this->query($sql_1, [
            $this->getSpecimenProject()->project_id,
            $specimen,
            $sql_name_regex
        ]);
        // loop through existing sibling specimens to ensure csid is consistent
        // if anything is not a full match, then this specimen cannot use this CSID
        while($row = db_fetch_assoc($qr_1)) {
            if ($row["csid"] !== $csid) {
                $is_valid = false;
                $errors[] = "A different CSID is used by one or more siblings of this specimen.";
                break;
            }
        }

        /* ===========================
           CHECK #2 - OTHER SPECIMENS
           =========================== */

        // grab any specimen names that match csid exactly
        $sql_2 = "SELECT r1.record
, r2.value
FROM redcap_data r1
JOIN redcap_data r2 ON r1.project_id = r2.project_id AND r1.record = r2.record AND r2.field_name = 'name'
WHERE r1.project_id = ?
AND r1.field_name = 'csid'
AND r1.value = ?";
        $qr_2 = $this->query($sql_2, [
            $this->getSpecimenProject()->project_id,
            $csid
        ]);
        // loop through existing specimens to ensure full match
        // if anything is not a full match, then this specimen cannot use this CSID
        while($row = db_fetch_assoc($qr_2)) {
            $r = $this->parseSpecimenName($row["value"], $system_config["specimen_name_regex"]);
            // lookup => participant_id -> visit -> sample_type
            if ($r["participant_id"] !== $s["participant_id"]
                && $r["visit"] !== $s["visit"]
                && $r["sample_type"] !== $s["sample_type"]) {
                // invalidate and provide an error message
                $is_valid = false;
                $errors[] = "CSID already assigned to [" . $row["value"] . "]";
            }
        }
        return [ $is_valid, $errors ];
    }

    /**
     * A CUID must be 100% unique in the entire project.
     * @param string $cuid
     * @return array|bool TRUE if no match found, FALSE otherwise
     */
    function validateCUID(string $cuid) {
        if (empty($cuid)) return true;

        $errors = [];

        $sql = "SELECT 1
FROM redcap_data
WHERE project_id = ?
AND field_name = 'cuid'
AND value = ?";
        $qr = $this->query($sql, [
            $this->getSpecimenProject()->project_id,
            $cuid
        ]);
        $is_valid = db_num_rows($qr) === 0;

        if ($is_valid !== true) {
            $errors[] = "CUID value already taken!";
        }
        return [ $is_valid, $errors ];
    }

    /* REQUEST HANDLERS */

    function handleGetSpecimen(string $record_id, array $system_config) {
        if ($record_id === null || !is_numeric($record_id)) {
            $this->sendError("Missing or invalid record_id specified.");
        }
        try {
            // get the specimen info
            $specimen_data = \REDCap::getData([
                "project_id" => $this->getSpecimenProject()->project_id,
                "records" => [ $record_id ]
            ]);
            $specimen = $specimen_data[$record_id][$this->getSpecimenProject()->firstEventId];
            $specimen["name_parsed"] = $this->parseSpecimenName($specimen["name"], $system_config["specimen_name_regex"]);
            $this->sendResponse($specimen);
        } catch (Exception $ex) {
            $this->sendError($ex->getMessage());
        }
    }

    function handleSearchSpecimen(string $search_value, array $system_config) {
        if (empty($search_value)) {
            $this->sendError("No search value provided.");
        }
        try {
            $tmp = [
                "specimens" => [],
                "boxes" => [],
                "lookup" => []
            ];
            // prepare the response
            $response = [
                "search_value" => $search_value,
                "parsed_value" => $this->parseSpecimenName($search_value, $system_config["specimen_name_regex"]),
                "match_type" => null,
                "max_visit" => null
            ];
            // use raw sql initially for fast search by participant_id
            $sql = "SELECT d1.record, d1.value 'name', d2.value 'box_record_id'
FROM redcap_data d1
JOIN redcap_data d2 ON d1.project_id = d2.project_id AND d1.record = d2.record AND d2.field_name = 'box_record_id'
WHERE d1.project_id = ?
AND d1.field_name = 'name'
AND d1.value REGEXP ?";
            $participant_id = $response["parsed_value"]["participant_id"];
            // # delimits the pattern since we need to treat the () as literals
            // the 2nd pattern clears out any remaining capture group syntax that isn't sql compatible
            $patterns = [ "#\(\?\<participant_id\>.*?\)#", "(\?\<\w*\>)" ];
            $replacements = [ $participant_id, "" ];
            $sql_name_regex = preg_replace($patterns, $replacements, $system_config["specimen_name_regex"]);
            // debug
//            $response["specimen_name_regex"] = $system_config["specimen_name_regex"];
//            $response["sql_name_regex"] = $sql_name_regex;

            $specimen_query_result = $this->query($sql,
                [
                    $this->getSpecimenProject()->project_id,
                    $sql_name_regex
                ]
            );
            // rebuild result into temporary dataset
            while($r = db_fetch_assoc($specimen_query_result)) {
                // specimen lookup by name
                $tmp["specimens"][$r["name"]] = $r["record"];
                // parsed specimen name lookup
                $r["parsed"] = $this->parseSpecimenName($r["name"], $system_config["specimen_name_regex"]);
                // lookup => year | participant_id | sample_type | visit
                $tmp_key_1 = $r["parsed"]["year"] ?? $this->_allowed_specimen_name_parts["year"];
                $tmp_key_2 = $r["parsed"]["participant_id"] ?? $this->_allowed_specimen_name_parts["participant_id"];
                $tmp_key_3 = $r["parsed"]["sample_type"] ?? $this->_allowed_specimen_name_parts["sample_type"];
                $tmp_key_4 = $r["parsed"]["visit"] ?? $this->_allowed_specimen_name_parts["visit"];
                // add record to lookup array
                $tmp["lookup"][$tmp_key_1][$tmp_key_2][$tmp_key_3][$tmp_key_4][] = $r["record"];
                // box lookup by specimen record_id
                // only matters if we're working with temporary "00" boxes
                if ($system_config["use_temp_box_type"] === true) {
                    $tmp["boxes"][$r["record"]] = $r["box_record_id"];
                }
            }
            // determine if an exact match was found
            if (isset($tmp["specimens"][$search_value])) {
                $response["match_type"] = "exact";
                $specimen_record_id = $tmp["specimens"][$search_value];
                // we don't want to consider the exact specimen's box in downstream logic
                unset($tmp["boxes"][$specimen_record_id]);
            } else if (count($tmp["specimens"]) > 0) {
                // attempt to find a full match
                // ---
                // year
                $tmp_match_1 = $tmp["lookup"][$response["parsed_value"]["year"] ?? $this->_allowed_specimen_name_parts["year"]] ?? [];
                // sample_types by participant_id
                // year | participant_id
                $tmp_match_2 = $tmp_match_1[$response["parsed_value"]["participant_id"] ?? $this->_allowed_specimen_name_parts["participant_id"]] ?? [];
                // visits by sample_type
                // year | participant_id | sample_type
                $tmp_match_3 = $tmp_match_2[$response["parsed_value"]["sample_type"] ?? $this->_allowed_specimen_name_parts["sample_type"]] ?? [];
                // records by visit
                // year | participant_id | sample_type | visit
                $tmp_match_4 = $tmp_match_3[$response["parsed_value"]["visit"] ?? $this->_allowed_specimen_name_parts["visit"]] ?? [];
                // if the final match group has at least one entry, we've found a 'full' match
                $specimen_record_id = reset($tmp_match_4);
                if (!empty($specimen_record_id)) {
                    $response["match_type"] = "full";
                } else if (!empty($tmp_match_3)) {
                    // grab the highest visit number
                    $max_visit = (int)max(array_keys($tmp_match_3));
                    $response["max_visit"] = $max_visit;
                    // grab the first record that matches only participant_id
                    // ---
                    $tmp_match_3a = reset($tmp_match_3) ?? [];
                    // this will be a record_id or null
                    $specimen_record_id = reset($tmp_match_3a);
                    // if it isn't empty, then we found a 'participant' match
                    if (!empty($specimen_record_id)) {
                        $response["match_type"] = "participant";
                    }
                } else if (!empty($tmp_match_2)) {
                    // grab the first record that matches only participant_id
                    // ---
                    // visits
                    $tmp_match_2a = reset($tmp_match_2) ?? [];
                    // records
                    $tmp_match_2b = reset($tmp_match_2a) ?? [];
                    // this will be a record_id or null
                    $specimen_record_id = reset($tmp_match_2b);
                    // if it isn't empty, then we found a 'participant' match
                    if (!empty($specimen_record_id)) {
                        $response["match_type"] = "participant";
                    }
                }
            }

            // get the rest of the specimen info
            if (!empty($specimen_record_id) && is_numeric($specimen_record_id)) {
                $specimen_data = \REDCap::getData([
                    "project_id" => $this->getSpecimenProject()->project_id,
                    "records" => [ $specimen_record_id ]
                ]);
                $response["specimen"] = $specimen_data[$specimen_record_id][$this->getSpecimenProject()->firstEventId];
                // get the box of the closest matching specimen
                $response["plate"] = $this->getPlate($response["specimen"]["box_record_id"], $system_config["box_name_regex"]);
            }
            // if this project uses temp "00" boxes
            // get the 'other_boxes' associated with this specimen
            if ($system_config["use_temp_box_type"] === true && !empty($tmp["boxes"])) {
                // get the plates of all 'family' specimens
                $other_boxes = $this->getPlates(array_unique($tmp["boxes"]), $system_config["box_name_regex"]);
                // downstream validation only cares if the box is temporary "00" AND is a valid destination for the scanned specimen
                // basic filter by sample_type
                $response["other_boxes"] = array_filter($other_boxes, function($p) use($response) {
                    return $p["box_type"] === "00" && $p["sample_type"] == $response["parsed_value"]["sample_type"];
                }) ?? [];
            }
            $this->sendResponse($response);
        } catch (Exception $ex) {
            $this->sendError($ex->getMessage());
        }
    }

    function handleSaveSpecimen(array $specimen, array $system_config) {
        if ($specimen === null || empty($specimen)) {
            $this->sendError("Cannot create/update specimen.  No specimen value provided.");
        }
        try {
            $save_data = [];
            if (empty($specimen["record_id"])) {
                // assign new record_id from specimen project
                $specimen["record_id"] = (string)\REDCap::reserveNewRecordId(
                    $this->getSpecimenProject()->project_id
                );
            }
            // add to save dataset
            $save_data[$specimen["record_id"]][$this->getSpecimenProject()->firstEventId] = $specimen;
            // save it
            $save_result = \REDCap::saveData(
                $this->getSpecimenProject()->project_id,
                "array",
                $save_data,
                "overwrite"
            );
            // handle any errors from the save attempt
            if (!empty($save_result["errors"])) {
                $this->sendError($save_result["errors"]);
            }
            $specimen["name_parsed"] = $this->parseSpecimenName($specimen["name"], $system_config["specimen_name_regex"]);
            $this->sendResponse($specimen);
        } catch (Exception $ex) {
            $this->sendError($ex->getMessage());
        }
    }

    function handleDeleteSpecimen(string $record_id) {
        if ($record_id === null || !is_numeric($record_id)) {
            $this->sendError("Missing or invalid record_id specified.");
        }
        try {
            // delete the specimen record
            $response = \REDCap::deleteRecord($this->getSpecimenProject()->project_id, $record_id);
            if ($response === null) {
                $response = "Delete action failed - One or more parameters were invalid or record does not exist!";
            }
            $this->sendResponse($response);
        } catch (Exception $ex) {
            $this->sendError($ex->getMessage());
        }
    }

    function handleValidateCSID(string $specimen, string $csid, $system_config) {
        try {
            [ $isValid, $errors ] = $this->validateCSID($specimen, $csid, $system_config);
            $result = [
                "isValid" => $isValid,
                "errors" => $errors ?? []
            ];
            $this->sendResponse($result);
        } catch (Exception $ex) {
            $this->sendError($ex->getMessage());
        }
    }

    function handleValidateCUID(string $cuid) {
        try {
            [ $isValid, $errors ] = $this->validateCUID($cuid);
            $result = [
                "isValid" => $isValid,
                "errors" => $errors ?? []
            ];
            $this->sendResponse($result);
        } catch (Exception $ex) {
            $this->sendError($ex->getMessage());
        }
    }
}