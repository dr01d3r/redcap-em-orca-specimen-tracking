<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $this */
namespace ORCA\OrcaSpecimenTracking;

use Exception;

trait SpecimenUtils {

    /**
     * @param $box_record_id
     * @return array
     * @throws Exception
     */
    function getSpecimensForBox($box_record_id): array
    {
        if (!is_numeric($box_record_id)) return [];
        $specimen_project = $this->getSpecimenProject();
        // get all plate info by record
        $records = \REDCap::getData([
            "project_id" => $specimen_project->project_id,
            "filterLogic" => "[box_record_id] = $box_record_id"
        ]);
        return array_values(array_map(function($record) use ($specimen_project) {
            return $record[$specimen_project->firstEventId];
        }, $records));
    }

    /**
     * @throws Exception
     */
    function getSpecimensForBoxes(array $box_record_ids): array
    {
        $result = [];
        // use raw SQL to quickly identify all specimens associated with these boxes
        $dt_specimen = $this->getDataTable($this->getSpecimenProject()->project_id);
        $specimen_query = $this->createQuery();
        $specimen_query->add("
SELECT record 'record_id', value 'box_record_id'
FROM $dt_specimen
WHERE project_id = ?
AND field_name = 'box_record_id'
",
            [
                $this->getSpecimenProject()->project_id
            ]
        );
        $specimen_query->add('AND')->addInClause('value', $box_record_ids);
        $specimen_query_result = $specimen_query->execute();
        // get all the specimen record_ids
        $specimen_records = [];
        while ($r = db_fetch_assoc($specimen_query_result)) {
            $specimen_records[] = $r["record_id"];
        }
        unset($specimen_query_result);

        // exit early if no specimens were found
        if (empty($specimen_records)) return [];

        $specimen_project = $this->getSpecimenProject();
        // get all plate info by record
        $records = \REDCap::getData([
            "project_id" => $specimen_project->project_id,
            "records" => $specimen_records
        ]);

        foreach ($records as $record_id => $record) {
            $specimen = $record[$specimen_project->firstEventId];
            $result[$specimen["box_record_id"]][$record_id] = $specimen;
        }

        return $result;
    }

    function parseSpecimenName($name, $regex) : array {
        $result = [];
        if (stripos($regex, '/') === false) $regex = "/$regex/";
        if (preg_match($regex, $name, $matches, PREG_UNMATCHED_AS_NULL)) {
            $result = array_filter($matches, function($v, $k) {
                return !is_numeric($k);
            }, ARRAY_FILTER_USE_BOTH);
        }
        return $result;
    }

    /* REQUEST HANDLERS */

    function handleGetSpecimen(string $record_id, array $system_config): array
    {
        $response = [
            "specimen" => null,
            "errors" => []
        ];
        if (!is_numeric($record_id)) {
            $response["errors"][] = "Missing or invalid record_id specified.";
        } else {
            try {
                // get the specimen info
                $specimen_data = \REDCap::getData([
                    "project_id" => $this->getSpecimenProject()->project_id,
                    "records" => [ $record_id ]
                ]);
                $specimen = $specimen_data[$record_id][$this->getSpecimenProject()->firstEventId];
                $response["specimen"] = $this->escape($specimen);
            } catch (Exception $ex) {
                $response["errors"][] = $ex->getMessage();
            }
        }
        // send it back!
        return $response;
    }

    function handleSearchSpecimen(string $search_value, array $system_config): array
    {
        $response = [
            "errors" => []
        ];
        if (empty($search_value)) {
            $response["errors"][] = "No search value provided.";
        } else {
            try {
                // get module config, if it exists
                list($metadata, $state) = $this->getModuleConfig();

                // prepare the response
                $response["search_value"] = $search_value;
                $response["parsed_value"] = $this->parseSpecimenName($search_value, $state["general"]["specimen_name_regex"]);
                $response["match_type"] = null;

                // initialize the sql condition and value for exact match only
                // these will change to REGEXP if matchPrefill is enabled and configured
                $sql_condition_1 = "AND d1.value = ?";
                $sql_value_1 = $search_value;

                // if the matchPrefill feature is enabled, alter the REGEX based on selected criteria
                $match_config = $state["fields"]["specimen"]["specimen_name"]["extras"]["matchPrefill"];
                if (!empty($match_config) && $match_config["enabled"] === true) {
                    if (!empty($match_config["groups"]) && !empty($match_config["fields"])) {
                        // leverage the specimen_name_regex to build the REGEX for specimen search
                        $replacements = [];
                        $patterns = [];
                        // swap the condition to REGEXP
                        $sql_condition_1 = "AND d1.value REGEXP ?";
                        foreach ($match_config["groups"] as $group) {
                            if (isset($response["parsed_value"][$group])) {
                                // fill the list to replace the capture groups we want to match against
                                $replacements[] = $response["parsed_value"][$group];
                                // one pattern for each replacement
                                // # delimits the pattern since we need to treat the () as literals
                                $patterns[] = "#\(\?\<$group\>.*?\)#";
                            }
                        }
                        // the 2nd pattern clears out any remaining capture group syntax that isn't sql compatible
                        // this will leave the regex clean of any capture group syntax
                        $patterns[] = "(\?\<\w*\>)";
                        $replacements[] = "";
                        // use the regex value instead of the raw search value
                        $sql_value_1 = preg_replace($patterns, $replacements, $state["general"]["specimen_name_regex"]);
                    } else {
                        $response["warnings"][] = "Pre-fill by Nomenclature enabled but ignored, due to incomplete configuration.";
                    }
                }

                // use raw sql initially for fast search by participant_id
                $dt_specimen = $this->getDataTable($this->getSpecimenProject()->project_id);
                $sql = "SELECT d1.record, d1.value 'specimen_name', d2.value 'box_record_id'
FROM $dt_specimen d1
JOIN $dt_specimen d2 ON d1.project_id = d2.project_id AND d1.record = d2.record AND d2.field_name = 'box_record_id'
WHERE d1.project_id = ?
AND d1.field_name = 'specimen_name'
{$sql_condition_1}";

                // execute the sql query
                $specimen_query_result = $this->query($sql,
                    [
                        $this->getSpecimenProject()->project_id,
                        $sql_value_1
                    ]
                );

                // rebuild result into temporary dataset
                $tmp = [];
                while($r = db_fetch_assoc($specimen_query_result)) {
                    // specimen lookup by name
                    $tmp[$r["specimen_name"]] = $r["record"];
                }

                // determine if an exact match was found
                $specimen_record_id = null;
                if (isset($tmp[$search_value])) {
                    $response["match_type"] = "exact";
                    $specimen_record_id = $tmp[$search_value];
                } else if (count($tmp) > 0) {
                    // if we're here, then a partial match was found
                    // we will want to grab the first of these matches for prefill purposes
                    $response["match_type"] = "partial";
                    $specimen_record_id = reset($tmp);
                } else {
                    // no match - do nothing
                    $response["match_type"] = "none";
                }

                // get the rest of the specimen info
                if (!empty($specimen_record_id) && is_numeric($specimen_record_id)) {
                    $specimen_data = \REDCap::getData([
                        "project_id" => $this->getSpecimenProject()->project_id,
                        "records" => [ $specimen_record_id ]
                    ]);
                    $response["specimen"] = $specimen_data[$specimen_record_id][$this->getSpecimenProject()->firstEventId];
                    // get the box of the closest matching specimen
                    $response["box"] = $this->getBox($response["specimen"]["box_record_id"]);
                }
            } catch (Exception $ex) {
                $response["errors"][] = $ex->getMessage();
            }
        }
        // send it back!
        return $this->escape($response);
    }

    function handleSaveSpecimen(array $specimen) {
        $response = [
            "errors" => []
        ];
        if (empty($specimen)) {
            $response["errors"][] = "Cannot create/update specimen.  No specimen value provided.";
        } else {
            try {
                $form_complete = $this->getSpecimenProject()->firstForm . "_complete";
                $save_data = [];
                if (empty($specimen["record_id"])) {
                    // assign new record_id from specimen project
                    $specimen["record_id"] = (string)\REDCap::reserveNewRecordId(
                        $this->getSpecimenProject()->project_id
                    );
                }
                // ensure we always have a form complete value
                $specimen[$form_complete] = $specimen[$form_complete] ?? "0";
                // add to save dataset
                $save_data[$specimen["record_id"]][$this->getSpecimenProject()->firstEventId] = array_intersect_key($specimen,
                    $this->getSpecimenProject()->metadata
                );
                // save it
                $save_result = \REDCap::saveData(
                    $this->getSpecimenProject()->project_id,
                    "array",
                    $save_data,
                    "overwrite"
                );
                // handle any errors from the save attempt
                if (!empty($save_result["errors"])) {
                    array_push($response["errors"], ...(is_array($save_result["errors"])?$save_result["errors"]:[$save_result["errors"]]));
                } else {
                    $response = $this->escape($specimen);
                }
            } catch (Exception $ex) {
                $response["errors"][] = $ex->getMessage();
            }
        }
        // send it back!
        return $response;
    }

    function handleDeleteSpecimen(string $record_id) {
        $response = [
            "errors" => []
        ];
        if (!is_numeric($record_id)) {
            $response["errors"][] = "Missing or invalid record_id specified.";
        } else {
            try {
                // delete the specimen record
                $delete_result = \REDCap::deleteRecord($this->getSpecimenProject()->project_id, $record_id);
                if ($delete_result === null) {
                    $response["errors"][] = "Delete action failed - One or more parameters were invalid or record does not exist!";
                } else {
                    $response = $this->escape($delete_result);
                }
            } catch (Exception $ex) {
                $response["errors"][] = $ex->getMessage();
            }
        }
        // send it back!
        return $response;
    }
}