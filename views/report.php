<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $module */

$module->addTime();
?>
    <div id="ORCA_SPECIMEN_TRACKING"></div>
    <script>
        const OrcaSpecimenTracking = function() {
            return {
                url: '<?= $module->getBaseUrl() ?>',
                redcap_csrf_token: <?= json_encode($module->getCSRFToken()) ?>
            }
        };
    </script>

    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap4.css"/>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap4.css"/>

    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/2.5.0/jszip.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap4.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.bootstrap4.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.js"></script>

    <script src="<?= $module->getUrl('dist/pages/report/app.js') ?>"></script>
    <link rel="stylesheet" href="<?= $module->getUrl('dist/pages/report/style.css') ?>">
<?php
$module->outputModuleVersionJS();