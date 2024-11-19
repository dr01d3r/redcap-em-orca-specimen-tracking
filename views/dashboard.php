<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $module */

$module->initializeJavascriptModuleObject();
?>
    <div id="ORCA_SPECIMEN_TRACKING"></div>
    <script>
        const OrcaSpecimenTracking = function() {
            return {
                jsmo: <?=$module->getJavascriptModuleObjectName()?>,
                userid: '<?=defined("USERID") ? USERID : ""?>'
            };
        };
    </script>
    <script type="module" src="<?=$module->getUrl('dist/pages/dashboard.js')?>"></script>
    <link rel="stylesheet" href="<?=$module->getUrl('dist/assets/dashboard.css')?>">
<?php
$module->outputModuleVersionJS();