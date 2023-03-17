<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $this */
namespace ORCA\OrcaSpecimenTracking;

trait RequestHandlers {
    /**
     * Gets Base URL to Request Handler
     *
     * @return string
     * @since 1.0.0
     */
    public function getBaseUrl(): string
    {
        return $this->getUrl("requestHandler.php");
    }

    /**
     * Echoes successful JSON response
     *
     * @param mixed $response
     * @return void
     * @since 1.0.0
     */
    public function sendResponse($response) : void
    {
        header('Content-Type: application/json; charset=UTF-8');
        exit(json_encode($response));
    }

    /**
     * Echoes error JSON response
     *
     * @param mixed $error Optional error details.  Will be JSON encoded.
     * @return void
     * @since 1.0.0
     */
    public function sendError($error = "") : void
    {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(400);
        exit(json_encode($error));
    }

    /**
     * @param string $search_value
     * @param array $system_config
     * @param bool $include_specimens Include specimens (default: true)
     * @return void
     * @since 1.0.1
     */
    public function searchPlate(string $search_value, array $system_config, bool $include_specimens = true) {
        $this->handleSearchPlate($search_value, $system_config, $include_specimens);
    }

    /**
     * @param string $record_id
     * @param array $system_config
     * @return void
     * @since 1.0.1
     */
    public function getSpecimen(string $record_id, array $system_config) {
        $this->handleGetSpecimen($record_id, $system_config);
    }

    /**
     * @param string $search_value
     * @param array $system_config
     * @return void
     * @since 1.0.1
     */
    public function searchSpecimen(string $search_value, array $system_config) {
        $this->handleSearchSpecimen($search_value, $system_config);
    }

    /**
     * @param array $specimen
     * @param array $system_config
     * @since 1.0.1
     */
    public function saveSpecimen(array $specimen, array $system_config) {
        $this->handleSaveSpecimen($specimen, $system_config);
    }

    public function deleteSpecimen(string $record_id) {
        $this->handleDeleteSpecimen($record_id);
    }

    /**
     * Initialize the main dashboard interface
     *
     * @param $system_config
     * @return void
     * @since 1.0.1
     */
    public function initializeBoxDashboard($system_config) {
        $this->handleInitializeBoxDashboard($system_config);
    }

    /**
     * Initialize the shipment dashboard interface
     *
     * @param $system_config
     * @return void
     * @since 1.0.1 Initial release.
     */
    public function initializeShipmentDashboard($system_config) {
        $this->handleInitializeShipmentDashboard($system_config);
    }

    /**
     * Initialize the reporting dashboard interface
     *
     * @param $system_config
     * @return void
     * @since 1.1.0 Initial release.
     */
    public function initializeReportDashboard($system_config) {
        $this->handleInitializeReportDashboard($system_config);
    }

    /**
     * Returns a list of shipments.
     * @since 1.0.1
     */
    public function searchShipments() {
        $this->handleSearchShipments();
    }

    /**
     * @param $box_record_id
     * @param $shipment_record_id
     * @param $system_config
     * @since 1.0.1 Initial release.
     */
    public function updateBoxShipment($box_record_id, $shipment_record_id, $system_config) {
        $this->handleUpdateBoxShipment($box_record_id, $shipment_record_id, $system_config);
    }
}