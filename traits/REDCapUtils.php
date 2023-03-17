<?php

namespace ORCA\OrcaSpecimenTracking;

trait REDCapUtils {

    private $_dataDictionary = [];
    private $_dictionaryValues = [];
    private $_metadata = [];
    private $timers = [];

    /**
     * Cache user-friendly metadata by project.
     * @param $project_id
     * @return mixed
     * @throws \Exception
     */
    public function getMyMetadata($project_id) {
        if (empty($this->_metadata[$project_id])) {
            $project = new \Project($project_id);
            $metadata = [
                "fields" => [],
                "forms" => [],
                "form_statuses" => [
                    0 => "Incomplete",
                    1 => "Unverified",
                    2 => "Complete"
                ],
                "date_field_formats" => [
                    "date_mdy" => "m/d/Y",
                    "datetime_mdy" => "m/d/Y H:i"
                ],
                "unstructured_field_types" => [
                    "text",
                    "textarea"
                ],
                "custom_dictionary_values" => [
                    "yesno" => [
                        "1" => "Yes",
                        "0" => "No"
                    ],
                    "truefalse" => [
                        "1" => "True",
                        "0" => "False"
                    ]
                ]
            ];

            foreach ($project->forms as $form_name => $form_data) {
                $metadata["forms"][$form_name] = [
                    "event_id" => null,
                    "repeating" => false
                ];
                foreach ($form_data["fields"]  as $field_name => $field_label) {
                    $metadata["fields"][$field_name] = [
                        "form" => $form_name,
                        "label" => $project->metadata[$field_name]["element_label"],
                        "misc" => $project->metadata[$field_name]["misc"],
                        "required" => $project->metadata[$field_name]["field_req"]
                    ];
                }
            }
            foreach ($project->eventsForms as $event_id => $event_forms) {
                foreach ($event_forms as $form_index => $form_name) {
                    $metadata["forms"][$form_name]["event_id"] = $event_id;
                }
            }
            if ($project->hasRepeatingForms()) {
                foreach ($project->getRepeatingFormsEvents() as $event_id => $event_forms) {
                    foreach ($event_forms as $form_name => $value) {
                        $metadata["forms"][$form_name]["repeating"] = true;
                    }
                }
            }
            $this->_metadata[$project_id] = $metadata;
        }
        return $this->_metadata[$project_id];
    }

    /**
     * Pulled from AbstractExternalModule
     * For broad REDCap version compatibility
     * @return string|null
     */
    public function getPID() {
        $pid = @$_GET['pid'];

        // Require only digits to prevent sql injection.
        if (ctype_digit($pid)) {
            return $pid;
        } else {
            return null;
        }
    }

    /**
     * Pulled from AbstractExternalModule
     * For broad REDCap version compatibility
     * @return string|null
     */
    public function getID()
    {
        $id = @$_GET['id'];

        // Require only digits to prevent sql injection.
        if (ctype_digit($id)) {
            return $id;
        } else {
            return null;
        }
    }

    /**
     * @param $project_id
     * @param string $format
     * @return mixed
     * @throws \Exception
     * @since 1.0.0 Initial release.
     * @since 1.0.1 Now uses project context.
     */
    public function getDataDictionary($project_id, $format = 'array') {
        if (!array_key_exists($project_id, $this->_dataDictionary)) {
            $this->_dataDictionary[$project_id] = [];
        }
        if (!array_key_exists($format, $this->_dataDictionary[$project_id])) {
            $this->_dataDictionary[$project_id][$format] = \REDCap::getDataDictionary($project_id, $format);
        }
        return $this->_dataDictionary[$project_id][$format];
    }

    public function getFieldValidationTypeFor($project_id, $field_name) {
        $result = $this->getDataDictionary($project_id)[$field_name]['text_validation_type_or_show_slider_number'];
        if (empty($result)) {
            return null;
        }
        return $result;
    }

    public function getDictionaryLabelFor($project_id, $key) {
        $label = $this->getDataDictionary($project_id)[$key]['field_label'];
        if (empty($label)) {
            return $key;
        }
        return $label;
    }

    /**
     * @param $project_id
     * @param $key
     * @return mixed Key/value pair of field options
     * @since 1.0.0 Initial release.
     * @since 1.0.1 Now uses project context.
     */
    public function getDictionaryValuesFor($project_id, $key) {
        // TODO consider using $this->getChoiceLabels()
        if (!array_key_exists($project_id, $this->_dictionaryValues)) {
            $this->_dictionaryValues[$project_id] = [];
        }
        if (!array_key_exists($key, $this->_dictionaryValues[$project_id])) {
            $this->_dictionaryValues[$project_id][$key] =
                $this->flatten_type_values($this->getDataDictionary($project_id)[$key]['select_choices_or_calculations']);
        }
        return $this->_dictionaryValues[$project_id][$key];
    }

    public function comma_delim_to_key_value_array($value) {
        $arr = explode(', ', trim($value));
        $sliced = array_slice($arr, 1, count($arr) - 1, true);
        return array($arr[0] => implode(', ', $sliced));
    }

    public function array_flatten($array) {
        $return = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $return = $return + $this->array_flatten($value);
            } else {
                $return[$key] = $value;
            }
        }
        return $return;
    }

    public function flatten_type_values($value) {
        $split = explode('|', $value);
        $mapped = array_map(function ($value) {
            return $this->comma_delim_to_key_value_array($value);
        }, $split);
        $result = $this->array_flatten($mapped);
        return $result;
    }

    /**
     * Splice an associative array to preserve the replacement array keys.
     *
     * Removes the elements designated by offset & length and replaces them
     * with the elements of replacement array.
     * @param $array array
     * @param $offset int
     * @param $length int
     * @param $replacement array
     * @return array Returns an array consisting of the extracted elements.
     */
    function array_splice_assoc(array &$array, int $offset, int $length, array $replacement = []): array
    {
        // let's grab the removed elements to mirror the normal array_splice output
        $removed = array_slice($array, $offset, $length, true);
        // the 'before' slice, before our $offset
        $before_slice = array_slice($array, 0, $offset, true);
        // the 'after' slice, after our $offset
        $after_slice = array_slice($array, $offset+$length, count($array), true);
        // merge our replacement in-between the 2 slices
        $array = array_merge($before_slice, $replacement, $after_slice);
        return $removed;
    }

    /**
     * @param $Proj \Project
     * @param $field_name string
     * @param $field_value string|array
     * @return array
     * @throws \Exception
     */
    public function getFieldDisplayValue($Proj, $field_name, $field_value) {

        $field_result = [
            "value" => $field_value
        ];
        $metadata = $this->getMyMetadata($Proj->project_id);

        // if I can't find the field in the project, just return the original value
        if (!isset($metadata["fields"][$field_name])) {
            return $field_result;
        }

        // initialize some helper variables/arrays
        $field_type = $Proj->metadata[$field_name]["element_type"];

        // record-level sorting
        if ($field_name === $Proj->table_pk) {
            $parts = explode("-", $field_value);
            if (count($parts) > 1) {
                $field_result["__SORT__"] = implode(".", [$parts[0], str_pad($parts[1], 10, "0", STR_PAD_LEFT)]);
            } else {
                $field_result["__SORT__"] = $field_value;
            }
        }

        if ($Proj->isFormStatus($field_name)) {
            // special value handling for form statuses
            $field_value = $metadata["form_statuses"][$field_value];
        } else if (!in_array($field_type, $metadata["unstructured_field_types"])) {
            switch ($field_type) {
                case "select":
                case "radio":
                    $field_value_dd = $this->getDictionaryValuesFor($Proj->project_id, $field_name);
                    $field_value = $field_value_dd[$field_value];
                    break;
                case "checkbox":
                    $temp_field_array = [];
                    $field_value_dd = $this->getDictionaryValuesFor($Proj->project_id, $field_name);
                    foreach ($field_value as $field_value_key => $field_value_value) {
                        if ($field_value_value === "1") {
                            $temp_field_array[$field_value_key] = $field_value_dd[$field_value_key];
                        }
                    }
                    $field_value = $temp_field_array;
                    break;
                case "yesno":
                case "truefalse":
                    $field_value = $metadata["custom_dictionary_values"][$Proj->metadata[$field_name]["element_type"]][$field_value];
                    break;
                case "sql":
                    if (isset($metadata["custom_dictionary_values"][$field_name][$field_value])) {
                        $field_value = $metadata["custom_dictionary_values"][$field_name][$field_value];
                    } else if ($field_value !== null && $field_value != '') {
                        // we don't want to show the raw value if a match is not found
                        $field_value = "";
                    }
                    break;
                default: break;
            }
        }

        // update field value if this is a known date format
        $element_validation_type = $Proj->metadata[$field_name]["element_validation_type"];
        if (array_key_exists($element_validation_type, $metadata["date_field_formats"]) && !empty($field_value)) {
            $field_result["__SORT__"] = strtotime($field_value);
            $field_value = date_format(date_create($field_value), $metadata["date_field_formats"][$element_validation_type]);
        }

        // set the final field value
        $field_result["value"] = $field_value;

        return $field_result;
    }

    public function preout($content) {
        if (is_array($content) || is_object($content)) {
            echo "<pre>" . print_r($content, true) . "</pre>";
        } else {
            echo "<pre>$content</pre>";
        }
    }

    /**
     * Outputs the module directory folder name into the page footer, for easy reference.
     * @return void
     */
    public function outputModuleVersionJS() {
        $module_info = $this->getModuleName() . " (" . $this->VERSION . ")";
        echo "<script>$(function() { $('div#south table tr:first td:last, #footer').prepend('<span>$module_info</span>&nbsp;|&nbsp;'); });</script>";
    }

    public function addTime($key = null) {
        if ($key == null) {
            $key = "STEP " . count($this->timers);
        }
        $this->timers[] = [
            "label" => $key,
            "value" => microtime(true)
        ];
    }

    public function outputTimerInfo($showAll = false, $return = false) {
        $initTime = null;
        $preTime = null;
        $curTime = null;
        $output = [];
        foreach ($this->timers as $index => $timeInfo) {
            $curTime = $timeInfo;
            if ($preTime == null) {
                $initTime = $timeInfo;
            } else {
                $calcTime = round($curTime["value"] - $preTime["value"], 4);
                if ($showAll) {
                    if ($return === true) {
                        $output[] = "{$timeInfo["label"]}: {$calcTime}";
                    } else {
                        echo "<p><i>{$timeInfo["label"]}: {$calcTime}</i></p>";
                    }
                }
            }
            $preTime = $curTime;
        }
        $calcTime = round($curTime["value"] - $initTime["value"], 4);
        if ($return === true) {
            $output[] = "Total Processing Time: {$calcTime} seconds";
            return $output;
        } else {
            echo "<p><i>Total Processing Time: {$calcTime} seconds</i></p>";
        }
    }

    public function generateTempFileName($timestampXmin=0)
    {
        $xMinFromNow = date("YmdHis", mktime(date("H"),date("i")+$timestampXmin,date("s"),date("m"),date("d"),date("Y")));
        return APP_PATH_TEMP . $xMinFromNow . "_" . substr(sha1(rand()), 0, 10);
    }

    /**
     * @param $file_path string The physical path to the file
     * @param null $filename string (Optional) Filename given to the file during download
     * @throws \Exception
     * @since 1.0.1
     */
    public function downloadFile($file_path, $filename = null)
    {
        // ensure the file exists
        if(!file_exists($file_path)) {
            throw new \Exception("Error file '{$file_path}' not found", 400);
        }
        // use file path if filename is not provided
        if ($filename === null) {
            $filename = $file_path;
        }
        // prep output headers
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($filename).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        // output file to client
        readfile($file_path);
        // delete file
        unlink($file_path);
    }

    public function getCSV($file) {
        $lines = [];
        if (($handle = fopen($file, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                $lines[] = $data;
            }
            fclose($handle);
        }
        return $lines;
    }

    public function getFileContents($file) {
        $lines = [];
        $handle = @fopen($file, "r");
        if ($handle) {
            while (($buffer = fgets($handle)) !== false) {
                $lines[] = $buffer;
            }
            if (!feof($handle)) {
                echo "Error: unexpected fgets() fail\n";
            }
            fclose($handle);
        }
        return $lines;
    }

    public function validatePath($path) {
        $path2 = str_replace("\\", "/", $path);
        return [
            "original_path" => $path,
            "normalized_path" => $path2,
            "is_dir" => is_dir($path2),
            "is_file" => is_file($path2),
            "is_writeable" => is_writeable($path2)
        ];
    }
}