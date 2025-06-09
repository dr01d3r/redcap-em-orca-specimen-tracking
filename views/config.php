<?php
/** @var \ORCA\OrcaSpecimenTracking\OrcaSpecimenTracking $module */

$module->initializeJavascriptModuleObject();
$b = new \Browser();
$cmdKey = ( $b->getPlatform() == "Apple" ? "&#8984;" : "Ctrl" );
?>
    <div id="ORCA_SPECIMEN_TRACKING"></div>
    <script>
        const OrcaSpecimenTracking = function() {
            return {
                jsmo: <?=$module->getJavascriptModuleObjectName()?>,
                userid: '<?=defined("USERID") ? USERID : ""?>',
                cmdKey: '<?=$cmdKey?>'
            };
        };
    </script>
    <script type="module" src="<?=$module->getUrl('dist/pages/config.js')?>"></script>
    <link rel="stylesheet" href="<?=$module->getUrl('dist/assets/config.css')?>">
<?php
$module->outputModuleVersionJS();