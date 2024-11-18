<script setup>
import {ref, onMounted, computed, watch} from 'vue';
import { isEmpty, isNotEmpty } from '@primeuix/utils/object';
import {useToast} from 'primevue/usetoast';
import ModuleUtils from '../ModuleUtils';

// Vuelidate
import useVuelidate from '@vuelidate/core'
import {
    helpers,
    required
} from '@vuelidate/validators'

const toast = useToast();
const showToast = (type, summary, detail) => {
    toast.add({
        severity: type,
        summary: summary,
        detail: detail,
        life: 3000
    });
};

const userid = ref(OrcaSpecimenTracking().userid);

const popRegex = ref();
const toggleRegexPop = (event) => {
    popRegex.value.toggle(event);
}

const debug = ref();
const errors = ref([]);
const isLoading = ref(false);
const isModified = ref(false);

const extrasDialogVisible = ref(false);
const fieldConfigDialogVisible = ref(false);
/**
 * Selected Extras Field Metadata
 * @type {Ref<any>}
 */
const selectedEFM = ref();
const rulesDialogHeader = computed(() => {
    if (isNotEmpty(selectedEFM.value)) {
        return selectedEFM.value['field_label'];
    }
    return 'Extras';
});

const metadata = ref();
const projects = ref();

const collapseAll = ref(false); 
const collapseTable = ref({
    specimen: false,
    box: false,
    shipment: false
});

// VUELIDATE
const state = ref({
    general: {},
    fields: {}
});
const rules = computed(() => {
    let myRules = {
        fields: {
            specimen: {}
        }
    };
    if (isNotEmpty(metadata.value) && isNotEmpty(state.value)) {
        // let k = null;
        for (let k in state.value['fields']['specimen']) {
            // ignore fields not enabled on specimen form
            if (!state.value['fields']['specimen'][k]['specimen-entry-form']) continue;
            // field rules
            let fr = {
                'field-default': {}
            };
            // default rule behaviors
            let fv = metadata.value['specimen'][k];
            // leverage built-in redcap validation
            // be sure to trim the leading/trailing slashes, otherwise the match will fail
            if (isNotEmpty(fv['validation'])) {
                //if (fv['field_type'] !== 'text') continue;
                // get the validation info
                let val_info = fv['validation'];
                // apply validation to the rules
                fr['field-default'][val_info['type']] = helpers.withMessage(val_info['label'],
                    (value) => isEmpty(value) || value.match(val_info['regex'].replace(/^\/|\/$/g, ''))
                );
            }
            if (isNotEmpty(fr)) {
                myRules['fields']['specimen'][k] = fr;
            }
        }
    }
    return myRules;
});

const v$ = useVuelidate(rules, state, {
    $lazy: true,
    $autoDirty: true
});

watch(state, async (newVal, oldVal) => {
    if (!isLoading.value) {
        isModified.value = true;
    }
}, { deep: true });

const specimenNameNomenclatureGroups = computed(() => {
    return ModuleUtils.getRegExpGroups(state.value?.general?.specimen_name_regex);
});

const canHaveExtras = (p, k) => {
    return p && k &&
        metadata.value[p][k]['config']['specimen-entry-form']['enabled'] &&
        isNotEmpty(state.value['fields'][p][k]['extras'])
    ;
};
const hasEnabledExtras = (p, k) => {
    if (p && k) {
        return Object.values(state.value['fields'][p][k]['extras']).some((o) => {
            return o['enabled'];
        });
    }
    return false;
}
const editExtras = (p, k) => {
    if (p && k && state.value['fields'][p][k]) {
        selectedEFM.value = metadata.value[p][k];
        extrasDialogVisible.value = true;
    }
};
const afterDatePreviewMessage = computed(() => {
    if (selectedEFM.value) {
        let extras = state.value['fields'][selectedEFM.value['project_name']][selectedEFM.value['field_name']]['extras']['afterDate'];
        let fn1 = selectedEFM.value['field_label'];
        let fn2 = extras['target'];
        if (isNotEmpty(fn2)) {
            fn2 = metadata.value['specimen'][extras['target']]['field_label'];
        }
        return ModuleUtils.afterDateTimeErrorMessage(fn1, fn2, extras);
    }
    return null;
});

const toggleConfigSections = (p) => {
    if (p === 'all') {
        collapseAll.value = !collapseAll.value;
        for (const k in collapseTable.value) {
            collapseTable.value[k] = collapseAll.value;
        }
    } else {
        collapseTable.value[p] = !collapseTable.value[p];
    }
};

const toggleIcon = (v) => {
    if (v === true) {
        return 'fas fa-toggle-on text-success';
    }
    return 'fas fa-toggle-off text-dark';
};

const toggleSpecimenEntry = (p, f) => {
    let newVal = !state.value['fields'][p][f]['specimen-entry-form'];
    state.value['fields'][p][f]['specimen-entry-form'] = newVal;
    if (!newVal) {
        // state.value['fields'][p][f]['field-default'] = null;
        // state.value['fields'][p][f]['field-units'] = null;
        // state.value['fields'][p][f]['batch-mode'] = false;
    }
};

const saveModuleConfig = () => {
    isLoading.value = true;
    OrcaSpecimenTracking().jsmo.ajax('save-module-config', state.value)
    .then(response => {
        // look for possible errors during save result
        if (isNotEmpty(response.errors)) {
            if (Array.isArray(response.errors)) {
                errors.value.push(...response.errors);
            } else {
                errors.value.push(response.errors);
            }
        } else {
            // success
            showToast(
                'success',
                'Success',
                'Module Configuration Saved!'
            );
            isModified.value = false;
            // debug
            // debug.value = response;
        }
    })
    .catch(err => {
        debug.value = err;
    })
    .finally(() => {
        isLoading.value = false;
    });
}

const initializeDashboard = () => {
    isLoading.value = true;
    OrcaSpecimenTracking().jsmo.ajax('initialize-config-dashboard', {})
        .then(response => {
            // look for possible errors during save result
            if (isNotEmpty(response.errors)) {
                if (Array.isArray(response.errors)) {
                    errors.value.push(...response.errors);
                } else {
                    errors.value.push(response.errors);
                }
            } else {
                if (response) {
                    // pull in the full config
                    state.value = response['state'];
                    metadata.value = response['metadata'];
                    projects.value = response['projects'];
                    // debug
                    // debug.value = v$;
                } else {
                    debug.value = response;
                }
            }
        })
        .catch(err => {
            debug.value = err;
        })
        .finally(() => {
            isLoading.value = false;
        });
};

onMounted(() => {
    initializeDashboard();
});
</script>

<template>
    <div class="projhdr">
        <i class="fas fa-cog text-dark"></i>&nbsp;Dashboard Configuration
    </div>
    <BlockUI :blocked="isLoading">
        <template v-if="isNotEmpty(errors)">
            <div class="alert alert-danger p-4">
                <h1 class="display-4">Critical Errors Exist!</h1>
                <p class="lead mb-0">This dashboard has been disabled until all critical errors have been resolved.</p>
                <hr/>
                <ul>
                    <template v-for="error in errors">
                        <li v-html="error"></li>
                    </template>
                </ul>
            </div>
        </template>

        <div class="card module-config" :class="{ modified: isModified }">
            <div class="card-body">
                <template v-if="isEmpty(state)">
                    <!-- LOADING PLACEHOLDER -->
                </template>
                <template v-else>
                    <!-- GENERAL CONFIGURATION -->
                    <h4 class="mb-2 d-flex gap-2 align-items-center pb-1 border-dark border-bottom border-3">
                        <span>General Configuration</span>
                    </h4>

                    <!-- projects -->
                    <template v-if="isNotEmpty(projects)">
                        <div class="d-flex gap-2">
                            <!-- specimen project -->
                            <div class="flex-fill">
                                <label class="form-label fw-bold mb-1">Specimen Project</label>
                                <div class="input-group mb-2">
                                    <span class="input-group-text fw-bold font-monospace bg-primary text-light">{{ projects['specimen']['project_id'] }}</span>
                                    <input type="text" class="form-control bg-primary-subtle" v-model="projects['specimen']['app_title']" disabled="disabled" />
                                </div>
                            </div>
                            <!-- box project -->
                            <div class="flex-fill">
                                <label class="form-label fw-bold mb-1">Box Project</label>
                                <div class="input-group mb-2">
                                    <span class="input-group-text fw-bold font-monospace bg-primary text-light">{{ projects['box']['project_id'] }}</span>
                                    <input type="text" class="form-control bg-primary-subtle" v-model="projects['box']['app_title']" disabled="disabled" />
                                </div>
                            </div>
                            <!-- shipment project -->
                            <div class="flex-fill">
                                <label class="form-label fw-bold mb-1">Shipment Project</label>
                                <div class="input-group mb-2">
                                    <span class="input-group-text fw-bold font-monospace bg-primary text-light">{{ projects['shipment']['project_id'] }}</span>
                                    <input type="text" class="form-control bg-primary-subtle" v-model="projects['shipment']['app_title']" disabled="disabled" />
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- study_name -->
                    <label class="form-label fw-bold mb-1">Study Name&nbsp;<i class="fas fa-info-circle text-primary" v-tooltip="'Included in report and shipment manifest exports and used as part of the export file names.'"></i></label>
                    <input class="form-control mb-3 text-muted" type="text" v-model="state['general']['study_name']" />

                    <!-- box_name_regex -->
                    <div class="form-label fw-bold mb-1 d-inline-block" @click="toggleRegexPop">Box Name Nomenclature
                        <button class="btn m-0 p-0"><i class="fas fa-question-circle text-primary"></i></button>
                    </div>
                    <input class="form-control mb-3 font-monospace text-muted" type="text" v-model="state['general']['box_name_regex']" />

                    <!-- specimen_name_regex -->
                    <div class="form-label fw-bold mb-1 d-inline-block" @click="toggleRegexPop">Specimen Name Nomenclature
                        <button class="btn m-0 p-0" ><i class="fas fa-question-circle text-primary"></i></button>
                    </div>
                    <input class="form-control mb-3 font-monospace text-muted" type="text" v-model="state['general']['specimen_name_regex']" />

                    <!-- configuration sections -->
                    <template v-if="isNotEmpty(metadata)">
                        <h4 class="mb-0 d-flex align-items-center pb-1 border-dark border-bottom border-3">
                            <span @click="fieldConfigDialogVisible = !fieldConfigDialogVisible">Field Configurations
                                <button class="btn m-0 p-0"><i class="fas fa-question-circle text-primary"></i></button>
                            </span>
                            <button class="btn btn-primary btn-xs ms-auto" @click="toggleConfigSections('all')">
                                <template v-if="collapseAll">
                                    <i class="fas fa-chevron-down"></i>&nbsp;Expand All
                                </template>
                                <template v-else>
                                    <i class="fas fa-chevron-up"></i>&nbsp;Collapse All
                                </template>
                            </button>
                        </h4>
                        <table class="table table-hover table-bordered align-middle">
                            <tbody>
                                <template v-for="p in [ 'specimen', 'box', 'shipment' ]">
                                    <tr class="table-dark">
                                        <td colspan="12" class="border-2 border-secondary-subtle">
                                            <div class="d-flex">
                                                <strong>{{ projects[p]['app_title'] }}</strong>
                                                <button class="btn btn-primary btn-xs ms-auto" @click="toggleConfigSections(p)">
                                                    <template v-if="collapseTable[p]">
                                                        <i class="fas fa-chevron-down"></i>&nbsp;Expand
                                                    </template>
                                                    <template v-else>
                                                        <i class="fas fa-chevron-up"></i>&nbsp;Collapse
                                                    </template>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="text-center" v-if="!collapseTable[p]">
                                        <th class="border-bottom text-start">Field Name</th>
                                        <th class="border-bottom">Specimen Entry Form</th>
                                        <th class="border-bottom">Extras</th>
                                        <th class="border-bottom">Default Value</th>
                                        <th class="border-bottom">Units Label</th>
                                        <th class="border-bottom">Batch Mode</th>
                                        <th class="border-bottom">Specimen List</th>
                                        <th class="border-bottom">Box Preview and Details</th>
                                        <th class="border-bottom">Reporting Dashboard</th>
                                        <th class="border-bottom">Shipment List</th>
                                        <th class="border-bottom">Shipment Box List</th>
                                        <th class="border-bottom">Shipment Manifest</th>
                                    </tr>
                                    <tr v-for="(v, k) in metadata[p]" v-if="!collapseTable[p]">
                                        <th class="font-monospace border-0 border-top border-bottom border-secondary">
                                            <div>[<span class="text-danger">{{ k }}</span>]</div>
                                        </th>
                                        <!-- specimen-entry-form -->
                                        <td class="p-0 align-middle table-primary border-0 border-top border-bottom border-primary">
                                            <button v-if="v['config']['specimen-entry-form']['enabled']" class="fs-4 btn p-0 m-0 border-0 rounded-0 w-100 p-0" type="button"
                                                    @click="toggleSpecimenEntry(p, k)"
                                                    :disabled="v['config']['specimen-entry-form']['required']">
                                                <i :class="toggleIcon(state['fields'][p][k]['specimen-entry-form'])"></i>
                                            </button>
                                        </td>
                                        <!-- extras -->
                                        <td class="p-1 align-middle table-primary border-0 border-top border-bottom border-primary text-center">
                                            <button v-if="canHaveExtras(p, k)" @click="editExtras(p, k)"
                                                    class="btn py-0 m-0" type="button"
                                                    :class="[ hasEnabledExtras(p, k) ? 'btn-success' : 'btn-outline-dark' ]"
                                                    :disabled="!state['fields'][p][k]['specimen-entry-form']">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                        <!-- field-default -->
                                        <td class="p-1 align-middle table-primary border-0 border-top border-bottom border-primary">
                                            <input type="text" class="form-control form-control-sm rounded-0"
                                                   v-if="v['config']['specimen-entry-form']['enabled'] && v['field_type'] === 'text'"
                                                   v-model="state['fields'][p][k]['field-default']"
                                                   :disabled="!state['fields'][p][k]['specimen-entry-form']"
                                                   :class="{ invalid: state['fields'][p][k]['specimen-entry-form'] && v$['fields'][p][k].$error }"
                                            />
                                        </td>
                                        <!-- field-units -->
                                        <td class="p-1 align-middle table-primary border-0 border-top border-bottom border-primary">
                                            <input type="text" class="form-control form-control-sm rounded-0"
                                                   v-if="v['config']['specimen-entry-form']['enabled'] && v['field_type'] === 'text'"
                                                   v-model="state['fields'][p][k]['field-units']"
                                                   :disabled="!state['fields'][p][k]['specimen-entry-form']"
                                            />
                                        </td>
                                        <!-- batch-mode -->
                                        <td class="p-0 align-middle table-primary border-0 border-top border-bottom border-primary">
                                            <button v-if="v['config']['batch-mode']['enabled']" class="fs-4 btn p-0 m-0 border-0 rounded-0 w-100 p-0" type="button"
                                                    @click="() => state['fields'][p][k]['batch-mode'] = !state['fields'][p][k]['batch-mode']"
                                                    :disabled="!state['fields'][p][k]['specimen-entry-form']">
                                                <i :class="toggleIcon(state['fields'][p][k]['batch-mode'])"></i>
                                            </button>
                                        </td>
                                        <!-- specimen-list -->
                                        <td class="p-0 align-middle table-warning border-0 border-top border-bottom border-warning">
                                            <button v-if="v['config']['specimen-list']['enabled']" class="fs-4 btn p-0 m-0 border-0 rounded-0 w-100 p-0" type="button"
                                                    @click="() => state['fields'][p][k]['specimen-list'] = !state['fields'][p][k]['specimen-list']"
                                                    :disabled="v['config']['specimen-list']['required']">
                                                <i :class="toggleIcon(state['fields'][p][k]['specimen-list'])"></i>
                                            </button>
                                        </td>
                                        <!-- specimen-dashboard -->
                                        <td class="p-0 align-middle table-danger border-0 border-top border-bottom border-danger">
                                            <button v-if="v['config']['specimen-dashboard']['enabled']" class="fs-4 btn p-0 m-0 border-0 rounded-0 w-100 p-0" type="button"
                                                    @click="() => state['fields'][p][k]['specimen-dashboard'] = !state['fields'][p][k]['specimen-dashboard']"
                                                    :disabled="v['config']['specimen-dashboard']['required']">
                                                <i :class="toggleIcon(state['fields'][p][k]['specimen-dashboard'])"></i>
                                            </button>
                                        </td>
                                        <!-- reporting-table -->
                                        <td class="p-0 align-middle table-success border-0 border-top border-bottom border-success">
                                            <button v-if="v['config']['reporting-table']['enabled']" class="fs-4 btn p-0 m-0 border-0 rounded-0 w-100 p-0" type="button"
                                                    @click="() => state['fields'][p][k]['reporting-table'] = !state['fields'][p][k]['reporting-table']"
                                                    :disabled="v['config']['reporting-table']['required']">
                                                <i :class="toggleIcon(state['fields'][p][k]['reporting-table'])"></i>
                                            </button>
                                        </td>
                                        <!-- shipment-list -->
                                        <td class="p-0 align-middle table-info border-0 border-top border-bottom border-info">
                                            <button v-if="v['config']['shipment-list']['enabled']" class="fs-4 btn p-0 m-0 border-0 rounded-0 w-100 p-0" type="button"
                                                    @click="() => state['fields'][p][k]['shipment-list'] = !state['fields'][p][k]['shipment-list']"
                                                    :disabled="v['config']['shipment-list']['required']">
                                                <i :class="toggleIcon(state['fields'][p][k]['shipment-list'])"></i>
                                            </button>
                                        </td>
                                        <!-- shipment-box-list -->
                                        <td class="p-0 align-middle table-info border-0 border-top border-bottom border-info">
                                            <button v-if="v['config']['shipment-box-list']['enabled']" class="fs-4 btn p-0 m-0 border-0 rounded-0 w-100 p-0" type="button"
                                                    @click="() => state['fields'][p][k]['shipment-box-list'] = !state['fields'][p][k]['shipment-box-list']"
                                                    :disabled="v['config']['shipment-box-list']['required']">
                                                <i :class="toggleIcon(state['fields'][p][k]['shipment-box-list'])"></i>
                                            </button>
                                        </td>
                                        <!-- shipment-manifest -->
                                        <td class="p-0 align-middle table-info border-0 border-top border-bottom border-info">
                                            <button v-if="v['config']['shipment-manifest']['enabled']" class="fs-4 btn p-0 m-0 border-0 rounded-0 w-100 p-0" type="button"
                                                    @click="() => state['fields'][p][k]['shipment-manifest'] = !state['fields'][p][k]['shipment-manifest']"
                                                    :disabled="v['config']['shipment-manifest']['required']">
                                                <i :class="toggleIcon(state['fields'][p][k]['shipment-manifest'])"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </template>
                </template>

                <div class="alert alert-danger mt-1 mb-0 px-3 py-2" v-if="v$ && v$.$errors.length">
                    <strong>Validation Error:</strong>
                    <ul class="mb-0">
                        <li v-for="error of v$.$errors" :key="error.$uid">{{ error.$message }}</li>
                    </ul>
                </div>
            </div>
            <div class="card-footer d-flex gap-2 justify-content-end">
                <button class="btn btn-primary" @click="saveModuleConfig"><i class="fas fa-save">&nbsp;</i>Save Changes</button>
            </div>
        </div>

        <pre v-if="debug" class="mt-3">{{ debug }}</pre>

        <Dialog modal v-model:visible="extrasDialogVisible" :header="rulesDialogHeader" :style="{ width: '50rem' }" position="top">
            <template v-if="selectedEFM">
                <div class="d-flex gap-2 flex-column">
                    <template v-for="(sv, sk) in state['fields'][selectedEFM['project_name']][selectedEFM['field_name']]['extras']">
                        <!-- specimen_name extras -->
                        <div v-if="sk === 'matchPrefill'" class="card">
                            <div class="card-header d-flex align-items-start">
                                <div class="flex-fill me-3">
                                    <h4>Pre-fill by Nomenclature</h4>
                                    <hr class="my-1" />
                                    <p>This feature can speed up the data entry process by finding "siblings" of a scanned specimen that already exist in the system and then pre-filling configured fields based on that existing specimen.</p>
                                    <p>Selections from the [<strong class="text-danger">Specimen Name Nomenclature</strong>] facilitate the match process, and selections of other Specimen fields that are enabled on the [<strong class="text-danger">Specimen Entry Form</strong>] facilitate the pre-fill.</p>
                                    <p>If this feature is enabled, and a match is found during the specimen scan/entry, the pre-fill that applies here will take precedence over the default pre-fill process (if applicable).</p>

                                    <div class="p-2 border-start border-3 border-info bg-info-subtle mb-0 d-flex flex-row gap-2"><strong>NOTE:</strong><div>Be mindful of how you configure this feature!
                                        <ul class="mb-0">
                                            <li>Not selecting enough nomenclature groups may allow the match process to select specimens that are not true matches.</li>
                                            <li>Selecting too many may prevent any matches at all.</li>
                                        </ul>
                                    </div></div>
                                    <div class="p-2 border-start border-3 border-info bg-info-subtle mt-3 mb-0 d-flex flex-row gap-2"><strong>NOTE:</strong><div>Ensure you have one or more selections made for each section below.  Any errors or missing configuration will cause this feature to be ignored.</div></div>
                                </div>
                                <button class="fs-2 btn border-0 rounded-0" type="button"
                                        @click="() => sv['enabled'] = !sv['enabled']"
                                >
                                    <i :class="toggleIcon(sv['enabled'])"></i>
                                </button>
                            </div>
                            <template v-if="sv['enabled']">
                                <div class="bg-secondary-subtle text-secondary font-monospace px-3 py-2 mb-0 rounded-0 border-0">{{ state['general']['specimen_name_regex'] }}</div>
                                <div class="card-body px-3 pt-2 pb-3">
                                    <label class="form-label fw-bold">Specimen Name Nomenclature Groups</label>
                                    <hr class="mt-0 mb-2"/>
                                    <div class="container">
                                        <div class="row">
                                            <div class="col-6" v-for="v in specimenNameNomenclatureGroups">
                                                <label class="font-monospace d-flex align-items-center"><input type="checkbox" v-model="sv['groups']" :value="v" />&nbsp;[<span class="text-danger">{{ v }}</span>]</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="isEmpty(sv['groups'])" class="alert alert-danger py-2 my-1"><strong>ERROR:</strong> Must select one or more nomenclature parts!</div>
                                    <label class="form-label fw-bold mt-1">Specimen Entry Form Fields to Pre-fill</label>
                                    <hr class="mt-0 mb-2"/>
                                    <div class="container">
                                        <div class="row">
                                            <template v-for="(v,k) in state['fields']['specimen']">
                                                <div class="col-6" v-if="v && v['specimen-entry-form'] && k !== 'specimen_name'">
                                                    <label class="font-monospace d-flex align-items-center"><input type="checkbox" v-model="sv['fields']" :value="k" />&nbsp;[<span class="text-danger">{{ k }}</span>]</label>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    <div v-if="isEmpty(sv['fields'])" class="alert alert-danger py-2 mt-1 mb-0"><strong>ERROR:</strong> Must select one or more specimen fields!</div>
                                </div>
                            </template>
                        </div>
                        <!-- datetime-based extras -->
                        <div v-if="sk === 'noFuture'" class="card">
                            <div class="card-header d-flex align-items-start">
                                <div class="flex-fill me-3">
                                    <h4>Not in the Future</h4>
                                    <hr class="my-1" />
                                    <p>Ensure that <code>[{{ selectedEFM['field_name'] }}]</code> cannot be set into the future.</p>
                                </div>
                                <button class="fs-2 btn border-0 rounded-0" type="button"
                                        @click="() => sv['enabled'] = !sv['enabled']"
                                >
                                    <i :class="toggleIcon(sv['enabled'])"></i>
                                </button>
                            </div>
                        </div>
                        <div v-if="sk === 'afterDate'" class="card">
                            <div class="card-header d-flex align-items-start">
                                <div class="flex-fill me-3">
                                    <h4>After Date</h4>
                                    <hr class="my-1" />
                                    <p>Ensure that <code>[{{ selectedEFM['field_name'] }}]</code> must be chronologically <strong>after</strong> a specified date/time field.  Optionally, a minimum/maximum number of <code>minutes</code> can be specified.</p>
                                    <div>
                                        <strong>Example:</strong>
                                        <div class="alert alert-secondary py-1 rounded-1 mt-1 mb-0">
                                            <code class="fw-bold">[date_1]</code> must be <code class="fw-bold">a minimum 30</code> minutes after <code class="fw-bold">[date_2]</code>
                                        </div>
                                    </div>
                                </div>
                                <button class="fs-2 btn border-0 rounded-0" type="button"
                                        @click="() => sv['enabled'] = !sv['enabled']"
                                >
                                    <i :class="toggleIcon(sv['enabled'])"></i>
                                </button>
                            </div>
                            <div class="card-body" v-if="sv['enabled']">
                                <div class="row align-items-baseline">
                                    <div class="col">
                                        <label class="fw-bold">Target Field&nbsp;<i class="fas fa-info-circle text-primary" v-tooltip="'Selectable fields must be enabled on the Specimen Entry Form, and use datetime validation.'"></i></label>
                                        <select class="form-select" v-model="sv['target']">
                                            <option value="">--</option>
                                            <template v-for="(v, k) in metadata['specimen']">
                                                <option :value="k"
                                                        v-if="k !== selectedEFM['field_name'] && state['fields']['specimen'][k]['specimen-entry-form'] && ['datetime'].includes(v['field_type'])"
                                                >{{ v['field_label'] }}</option>
                                            </template>
                                        </select>
                                    </div>
                                    <div class="col-2">
                                        <label class="fw-bold">Minimum Time (min)</label>
                                        <input type="text" class="form-control" v-model="sv['minimum']" />
                                    </div>
                                    <div class="col-2">
                                        <label class="fw-bold">Maximum Time (min)</label>
                                        <input type="text" class="form-control" v-model="sv['maximum']" />
                                    </div>
                                    <div class="col-auto text-center">
                                        <label class="fw-bold">Warning Only</label>
                                        <button class="fs-4 btn p-0 m-0 border-0 rounded-0 w-100 p-0" type="button"
                                                @click="() => sv['warningOnly'] = !sv['warningOnly']"
                                        >
                                            <i :class="toggleIcon(sv['warningOnly'])"></i>
                                        </button>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <strong>Preview</strong>
                                        <div class="alert mt-2 mb-0" :class="[ sv['warningOnly'] ? 'alert-warning' : 'alert-danger' ]" v-html="afterDatePreviewMessage"></div>
                                    </div>
                                    <div class="col-12 mt-2" v-if="sv['warningOnly']">
                                        <div class="well mb-0">
                                            <h5>Warning Acknowledgement</h5>
                                            <hr/>
                                            <p>If one or more warnings exist when the user saves or edits a specimen, they will be prompted to acknowledge that they have seen these warnings and choose to proceed anyway.</p>
                                            <p>You may choose to have these acknowledgements documented, by selecting a <code>[notes]</code> field below.</p>
                                            <div>
                                                <strong>Preview</strong>
                                                <div class="alert alert-warning py-2 mt-2 mb-2 font-monospace" v-html="ModuleUtils.warningAcknowledgementMessagePreview(userid)"></div>
                                            </div>
                                            <label class="fw-bold">Target Field&nbsp;<i class="fas fa-info-circle text-primary" v-tooltip="'Must be a \'notes\' field and enabled on the Specimen Entry Form.'"></i></label>
                                            <select class="form-select" v-model="state['general']['warning_ack_field']">
                                                <option value="">--</option>
                                                <template v-for="(v, k) in metadata['specimen']">
                                                    <option :value="k"
                                                            v-if="k !== selectedEFM['field_name'] && state['fields']['specimen'][k]['specimen-entry-form'] && ['notes'].includes(v['field_type'])"
                                                    >{{ v['field_label'] }}</option>
                                                </template>
                                            </select>
                                            <div class="p-2 border-start border-3 border-info bg-info-subtle mt-3 mb-0 d-flex flex-row gap-2"><strong>NOTE:</strong><div>This configuration applies to all fields that have this warning acknowledgement feature enabled.</div></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- text-based extras -->
                        <div v-if="sk === 'confirm'" class="card">
                            <div class="card-header d-flex align-items-start">
                                <div class="flex-fill me-3">
                                    <h4>Confirm </h4>
                                    <hr class="my-1" />
                                    <p>To confirm correctness during data entry, <code>[{{ selectedEFM['field_name'] }}]</code> must be entered twice.</p>
                                    <p><strong>Note:</strong> It is not recommended to use this feature on fields that may get pre-filled in any way, or are configured to use Default Value/Batch Mode - doing so may have unintended side effects.</p>
                                </div>
                                <button class="fs-2 btn border-0 rounded-0" type="button"
                                        @click="() => sv['enabled'] = !sv['enabled']"
                                >
                                    <i :class="toggleIcon(sv['enabled'])"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

            </template>
            <template #footer>
                <button type="button" class="btn btn-outline-dark" @click="extrasDialogVisible = false">Ok</button>
            </template>
        </Dialog>

        <Dialog modal v-model:visible="fieldConfigDialogVisible" header="Field Configurations" :style="{ width: '60rem' }" position="center">
            <div class="d-flex flex-column gap-1">
                <table class="table table-hover my-0">
                    <thead>
                    <tr class="table-dark">
                        <th>Name</th>
                        <th>Description</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr class="table-primary">
                        <th class="text-nowrap">Specimen Entry Form</th>
                        <td>Data entry fields on the Specimen Entry Dashboard.
                            <ul class="mb-0">
                                <li>Only specimen fields are eligible</li>
                            </ul>
                        </td>
                    </tr>
                    <tr class="table-primary">
                        <th class="text-nowrap">Extras</th>
                        <td>Additional validations based on field type that can be enabled during specimen entry.
                            <ul class="mb-0">
                                <li>Specimen Entry Form must be enabled</li>
                            </ul>
                        </td>
                    </tr>
                    <tr class="table-primary">
                        <th class="text-nowrap">Default Value</th>
                        <td>Provide a default value for a given field, if it is almost always the same value for every specimen.
                            <ul class="mb-0">
                                <li>Only text fields are eligible</li>
                                <li>Specimen Entry Form must be enabled</li>
                            </ul>
                        </td>
                    </tr>
                    <tr class="table-primary">
                        <th class="text-nowrap">Units Label</th>
                        <td>Provide a visual label to the field during specimen entry.
                            <ul class="mb-0">
                                <li>Only text fields are eligible</li>
                                <li>Specimen Entry Form must be enabled</li>
                            </ul>
                        </td>
                    </tr>
                    <tr class="table-primary">
                        <th class="text-nowrap">Batch Mode</th>
                        <td>A feature that allows you to preserve the last value provided when saving a specimen (by default, all fields are reset after a specimen is saved).
                            <ul class="mb-0">
                                <li>Specimen Entry Form must be enabled</li>
                            </ul>
                        </td>
                    </tr>
                    <tr class="table-warning">
                        <th class="text-nowrap">Specimen List</th>
                        <td>Specimen fields to be displayed in the table below the specimen entry and box preview.
                            <ul class="mb-0">
                                <li>Only specimen fields are eligible</li>
                            </ul>
                        </td>
                    </tr>
                    <tr class="table-danger">
                        <th class="text-nowrap">Box Preview and Details</th>
                        <td>Display a list of box information in a collapsible section above the Box Preview.
                            <ul class="mb-0">
                                <li>Only box fields are eligible</li>
                            </ul>
                        </td>
                    </tr>
                    <tr class="table-success">
                        <th class="text-nowrap">Reporting Dashboard</th>
                        <td>Enable fields for display &amp; export on the Reporting Dashboard</td>
                    </tr>
                    <tr class="table-info">
                        <th class="text-nowrap">Shipment List</th>
                        <td>Fields to be displayed when searching and displaying shipment information on the Shipment Dashboard
                            <ul class="mb-0">
                                <li>Only shipment fields are eligible</li>
                            </ul>
                        </td>
                    </tr>
                    <tr class="table-info">
                        <th class="text-nowrap">Shipment Box List</th>
                        <td>Fields to be displayed in the box list, for boxes that have been added to a shipment.
                            <ul class="mb-0">
                                <li>Only box fields are eligible</li>
                            </ul>
                        </td>
                    </tr>
                    <tr class="table-info">
                        <th class="text-nowrap">Shipment Manifest</th>
                        <td>Fields to be included in the shipment manifest export.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </Dialog>

        <Popover ref="popRegex">
            <div class="d-flex flex-column gap-3 fs-6" style="width: 40rem;">
                <strong>Regular Expressions (regex)</strong>
                <hr class="my-0" />
                <div>A regular expression (or regex) allows us to express the structure of a field like <code>[box_name]</code> and <code>[specimen_name]</code> in a programmatic way.</div>
                <div>This expression allows us to both validate that the provided value matches a required format, as well as extract named pieces of the value, for use in other functionality of the module.</div>
                <div>To learn how this works, the links below take you to a very useful website - <a href="https://regex101.com/" rel="noopener" target="_blank">regex 101</a> - that allows you to build your expression, test it, see a breakdown of what it all means.</div>
                <div class="btn-group">
                    <a href="https://regex101.com/r/zGlzTU/1" rel="noopener" target="_blank" class="btn btn-outline-primary"><i class="fas fa-external-link-alt">&nbsp;</i>Box Example</a>
                    <a href="https://regex101.com/r/KSD5Ry/1" rel="noopener" target="_blank" class="btn btn-outline-primary"><i class="fas fa-external-link-alt">&nbsp;</i>Specimen Example</a>
                </div>
            </div>
        </Popover>

        <Toast position="bottom-right" />
        <ProgressSpinner v-show="isLoading" class="overlay"/>
    </BlockUI>
</template>

<style>
* {
    --p-tooltip-max-width: 25rem;
}
.p-dialog-header {
    padding-top: 1rem !important;
    padding-bottom: .5rem !important;;
}
.overlay {
    position: fixed !important;
    top: calc(50% - 50px);
    left: calc(50% - 50px);
    z-index: 100; /* this seems to work for me but may need to be higher*/
}
input.invalid {
    border-color: var(--bs-danger) !important;
}
.module-config.card {
    background-color: var(--bs-light);
}
.module-config.card .card-footer {
    background-color: var(--bs-secondary-bg-subtle);
}
.module-config.card.modified {
    background-color: var(--bs-warning-bg-subtle);
}
.module-config.card.modified .card-footer {
    background-color: var(--bs-warning);
}
.btn.btn-outline-primary {
    text-decoration: none;
    color: var(--bs-primary);
}
.btn.btn-outline-primary:hover {
    color: var(--bs-light);
}
</style>