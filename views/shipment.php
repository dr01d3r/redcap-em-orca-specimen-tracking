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
    <script src="<?= $module->getUrl('dist/pages/shipment/app.js') ?>"></script>
    <link rel="stylesheet" href="<?= $module->getUrl('dist/pages/shipment/style.css') ?>">
<?php
$module->outputModuleVersionJS();