<script setup>
import {ref, computed, onMounted, nextTick} from 'vue';
import { isEmpty, isNotEmpty } from '@primeuix/utils/object';
import ModuleUtils from '../ModuleUtils';
import {useConfirm} from "primevue/useconfirm";
import {useToast} from 'primevue/usetoast';
import {useVuelidate} from '@vuelidate/core';
import {helpers, required} from '@vuelidate/validators';
// local components
import ShipmentSearch from './ShipmentSearch.vue';
import ShipmentModal from './ShipmentModal.vue';

const box_name_input = ref();
const shipmentModal = ref();

const isLoading = ref(false);
const debug = ref();

const config = ref({});
const errors = ref({});
const forceReadOnly = ref(false);
const shipment = ref();
const shipment_details = ref();
const box_name = ref();
const boxes = ref([]);

const confirm = useConfirm();

const confirmAddBox = (box) => {
    confirm.require({
        group: 'add-box',
        header: 'Adding Box',
        message: box.box_name,
        rejectLabel: 'Cancel',
        acceptLabel: 'Confirm',
        rejectClass: 'p-button-secondary p-button-outlined',
        acceptClass: 'p-button-primary',
        accept: () => {
            updateBoxShipment(box.record_id, shipment.value.record_id);
        },
        reject: () => {
            box_name.value = null;
            resetFocus();
        }
    });
};

const confirmRemoveBox = (box) => {
    confirm.require({
        group: 'remove-box',
        header: 'Removing Box',
        message: box.box_name,
        rejectLabel: 'Cancel',
        acceptLabel: 'Confirm',
        rejectClass: 'p-button-secondary p-button-outlined',
        acceptClass: 'p-button-danger',
        accept: () => {
            updateBoxShipment(box.record_id, null);
        }
    });
};

const confirmComplete = () => {
    confirm.require({
        group: 'complete',
        header: 'Completing the Shipment',
        message: shipmentTitle.value,
        rejectLabel: 'Cancel',
        acceptLabel: 'Confirm',
        rejectClass: 'p-button-secondary p-button-outlined',
        acceptClass: 'p-button-success',
        accept: () => {
            completeShipment(shipment.value.record_id)
        },
        reject: () => {
            resetFocus();
        }
    });
};

const toast = useToast();
const showToast = (type, summary, detail) => {
    toast.add({
        severity: type,
        summary: summary,
        detail: detail,
        life: 3000
    });
};

const shipmentRecordId = computed(() => {
    if (shipment.value) {
        return shipment.value.record_id;
    } else {
        return null;
    }
});
const shipmentTitle = computed(() => {
    if (shipment.value) {
        return shipment.value.shipment_name;
    } else {
        return 'Shipment Dashboard'
    }
});
const boxCount = computed(() => {
    if (shipment.value && boxes.value.length) {
        return boxes.value.length;
    }
});
const canCreateNewShipment = computed(() => {
    // true unless config is in a bad state
    return !isReadOnly.value && config.value && config.value.new_shipment_url;
});
const showButtonCompleteShipment = computed(() => {
    return !isReadOnly.value && shipment.value &&!isShipmentComplete.value;
});
const canSearchShipments = computed(() => {
    // true unless config is in a bad state
    return !isReadOnly.value;
});
const canExportManifest = computed(() => {
    return !isReadOnly.value && config.value && config.value.manifest_export_url;
});
const canModifyShipment = computed(() => {
    return !isReadOnly.value && shipment.value && shipment.value.shipment_status !== "complete";
});
const isShipmentComplete = computed(() => {
    return shipment.value && shipment.value.shipment_status === "complete";
});
const isReadOnly = computed(() => {
    return forceReadOnly.value || isNotEmpty(errors.value);
});

const initializeDashboard = async () => {
    isLoading.value = true;
    OrcaSpecimenTracking().jsmo.ajax('initialize-shipment-dashboard', {
        id: ModuleUtils.qs_get('id')
    })
    .then(response => {
        box_name.value = null;
        config.value = response.config;
        shipment.value = response.shipment ?? null;
        shipment_details.value = response.shipment_details ?? null;
        boxes.value = response.boxes ?? [];
        // debug
        // debug.value = response;
    })
    .catch(e => {
        let errorMsg = 'An unknown error occurred';
        if (e.message) {
            errorMsg = e.message;
        }
        if (typeof errorMsg === 'string') {
            errorMsg = [ errorMsg ];
        }
        errors.value = Object.assign(errors.value, errorMsg);
    })
    .finally(() => {
        setUrlState();
        resetFocus();
        setTimeout(() => {
            isLoading.value = false;
        }, 250);
    });
};
const boxScanned = () => {
    if (v$.value.box_name.$dirty && !v$.value.box_name.$error && isNotEmpty(box_name.value)) {
        searchBoxName(box_name.value);
    }
};
const searchBoxName = async (search_value) => {
    isLoading.value = true;
    OrcaSpecimenTracking().jsmo.ajax('search-box-list', {
        search: search_value,
        include_specimens: false
    })
    .then(response => {
        searchBoxNameCallback(response?.boxes[0]);
    })
    .catch(e => {
        let errorMsg = 'An unknown error occurred';
        if (e.message) {
            errorMsg = e.message;
        }
        showToast("error", "Box Search Failed", errorMsg);
    })
    .finally(() => {
        setTimeout(() => {
            isLoading.value = false;
        }, 250);
    });
};
const searchBoxNameCallback = (box) => {
    let v = null;
    // if a box was found
    if (box) {
        // verify it isn't already tied to a shipment
        if (isEmpty(box.shipment_record_id)) {
            if (box.box_status === 'closed') {
                // box is in a closed status
                v = {
                    box_name: [ `Box is Closed - cannot add a closed box to the shipment!` ]
                };
            } else {
                confirmAddBox(box);
            }
        } else {
            if (box.shipment_record_id === shipment.value.record_id) {
                // already on this shipment
                v = {
                    box_name: [ 'Box already exists in this shipment!' ]
                };
            } else {
                // on another shipment
                v = {
                    box_name: [ 'Box already exists in another shipment!' ]
                };
            }
        }
    } else {
        // no box was found by that name
        v = {
            box_name: [ 'No box found with that name.' ]
        };
    }
    if (v !== null) {
        Object.assign($vuelidateExternalResults.value, v);
        nextTick(() => {
            v$.value.box_name.$validate();
        });
    }
    // debug
    // debug.value = data;
    v$.value.box_name.$reset();
};
const searchShipments = () => {
    shipmentModal.value.show();
};
const boxDashboard = (box) => {
    if (box && box.record_id) {
        window.location.href = `${config.value.box_dashboard_base_url}&id=${box.record_id}`;
    }
};

const boxDisplayValue = (f, v) => {
    if (isNotEmpty(v)) {
        try {
            let dv = v;
            let fm = config.value?.fields?.box[f] ?? {};
            switch (fm['field_type']) {
                case 'radio':
                case 'dropdown':
                    dv = fm['choices'][v];
                    break;
                case 'date':
                case 'datetime':
                    // reformat to configured format
                    dv = ModuleUtils.formatDate(v, fm['validation']['type']);
                    break;
            }
            return dv;
        } catch (e) {}
    }
    return v;
};

const completeShipment = async (shipment_record_id) => {
    // complete-shipment
    isLoading.value = true;
    OrcaSpecimenTracking().jsmo.ajax('complete-shipment', {
        shipment_record_id: shipment_record_id
    })
    .then(response => {
        showToast('success', 'Save Successful', 'Shipment Closed Successfully');
        // rebuild the dashboard
        initializeDashboard();
    })
    .catch(e => {
        let errorMsg = 'An unknown error occurred';
        if (e.message) {
            errorMsg = e.message;
        }
        showToast('error', 'Error while completing Shipment', errorMsg);
    })
    .finally(() => {
        setTimeout(() => {
            isLoading.value = false;
            resetFocus();
        }, 250);
    });
};
const updateBoxShipment = async (box_record_id, shipment_record_id) => {
    // update-box-shipment
    isLoading.value = true;
    OrcaSpecimenTracking().jsmo.ajax('update-box-shipment', {
        box_record_id: box_record_id,
        shipment_record_id: shipment_record_id
    })
    .then(response => {
        // rebuild the dashboard
        initializeDashboard();
    })
    .catch(e => {
        let errorMsg = 'An unknown error occurred';
        if (e.message) {
            errorMsg = e.message;
        }
        showToast('error', 'Box Shipment Update Failed', errorMsg);
    })
    .finally(() => {
        setTimeout(() => {
            isLoading.value = false;
            resetFocus();
        }, 250);
    });
};
const resetFocus = () => {
    nextTick(() => {
        if (box_name_input.value) {
            box_name_input.value.focus();
        }
    });
};
const setUrlState = () => {
    if (shipment.value == null) {
        // 'id=' will not get removed, so force an arbitrary value so it'll be completely removed
        ModuleUtils.qs_push("id", false, true);
        ModuleUtils.qs_remove("id", true);
    } else {
        ModuleUtils.qs_push("id", shipmentRecordId.value, true);
    }
};

// validation
const rules = {
    box_name: {
        regexMatch: helpers.withMessage('Value provided does not match the required nomenclature!',
            (value) => isEmpty(value) || value.match(config.value['general']['box_name_regex'])
        )
    }
};

// server-side validation support
const $vuelidateExternalResults = ref({ box_name: [] });
const v$ = useVuelidate(rules, { box_name }, {
    $lazy: true,
    $autoDirty: true,
    $stopPropagation: true,
    $externalResults: $vuelidateExternalResults
});

onMounted(() => {
    initializeDashboard();
});
</script>

<template>
    <div class="projhdr">
        <i class="fas fa-truck text-dark"></i>&nbsp;{{ shipmentTitle }}
        <template v-if="config.shipment_record_home_url">
            <span>&nbsp;|&nbsp;</span>
            <a :href="config.shipment_record_home_url" class="text-primary ml-1"><i class="fas fa-share"></i>&nbsp;Record Home</a>
        </template>
        <template v-if="canCreateNewShipment">
            <span>&nbsp;|&nbsp;</span>
            <a :href="config.new_shipment_url" class="text-success font-weight-normal"><i class="fas fa-plus"></i>&nbsp;New Shipment</a>
        </template>
        <template v-if="canSearchShipments">
            <span>&nbsp;|&nbsp;</span>
            <button type="button" @click="searchShipments" class="btn btn-link text-primary font-weight-normal text-decoration-none"><i class="fas fa-search"></i>&nbsp;Search Shipments</button>
        </template>
    </div>

    <BlockUI :blocked="isLoading">
        <div v-if="isShipmentComplete" class="alert alert-success">This Shipment is marked Complete.</div>

        <template v-if="isNotEmpty(errors)">
            <div class="alert alert-danger p-4">
                <h1 class="display-4">Critical Errors Exist!</h1>
                <p class="lead mb-0">This dashboard has been disabled until all critical errors have been resolved.</p>
                <hr/>
                <ul>
                    <template v-for="(v, k) in errors">
                        <li>{{ v }}</li>
                    </template>
                </ul>
            </div>
        </template>

        <!-- MAIN CONTENT AREA -->
        <div class="card">
            <div class="card-header">
                <template v-if="shipment">
                    <div class="row">
                        <div class="col border-right" v-if="canModifyShipment">
                            <h1 class="lead mb-0">Scan Box Names Here</h1>
                            <hr class="my-1" />
                            <input class="form-control"
                                   ref="box_name_input"
                                   autocomplete="off"
                                   @keyup.enter="boxScanned"
                                   @blur="boxScanned"
                                   v-model="box_name" />
                            <div class="alert alert-danger mt-1 mb-0 px-3 py-2"
                                 v-if="v$.box_name.$error">
                                <strong>Validation Error:</strong>
                                <ul class="mb-0">
                                    <li v-for="error of v$.box_name.$errors" :key="error.$uid">{{ error.$message }}</li>
                                </ul>
                            </div>
                        </div>

                        <div v-if="isNotEmpty(config['fields']['shipment'])" class="col-4">
                            <h1 class="lead mb-0">Shipment Details</h1>
                            <dl class="row">
                                <template v-for="(v, k) of config['fields']['shipment']">
                                    <template v-if="config['save-state']['shipment'][k]['shipment-list']">
                                        <div class="col-12"><hr class="my-1" /></div>
                                        <dt class="col-lg-5 text-truncate" :title="v['field_label']">{{ v['field_label'] }}</dt>
                                        <dd class="col-lg-7 mb-0">{{ shipment_details[k] }}</dd>
                                    </template>
                                </template>
                            </dl>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <h1 class="lead">Shipment Search</h1>
                    <shipment-search />
                </template>
            </div>
            <div class="card-body">
                <h1 class="lead">
                    Shipment Boxes
                    <span v-if="boxCount">
                        ({{ boxCount }})
                    </span>
                    <template v-if="canExportManifest">
                        <span>&nbsp;|&nbsp;</span>
                        <a :href="config.manifest_export_url" class="text-primary font-weight-normal"><i class="fas fa-file-export"></i>&nbsp;Export Manifest</a>
                    </template>
                </h1>
                <table class="table" v-if="config['save-state']">
                    <thead>
                    <tr>
                        <!-- configured fields -->
                        <template v-for="(fv, fk) in config['save-state']['box']">
                            <th v-if="fv['shipment-box-list']">{{ config['fields']['box'][fk]['field_label'] }}</th>
                        </template>
                        <th scope="col">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(bv, bk) in boxes">
                        <!-- configured fields -->
                        <template v-for="(fv, fk) in config['save-state']['box']">
                            <td v-if="fv['shipment-box-list']">{{ boxDisplayValue(fk, bv[fk]) }}</td>
                        </template>
                        <td class="d-flex gap-2">
                            <button v-if="canModifyShipment" @click.prevent="confirmRemoveBox(bv)" class="btn btn-xs btn-danger text-light" title="Remove Box"><i class="fas fa-times"></i>&nbsp;Remove</button>
                            <button @click.prevent="boxDashboard(bv)" class="btn btn-xs btn-primary text-light" title="Go to Box Dashboard"><i class="fas fa-vials"></i>&nbsp;Dashboard</button>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <button v-if="showButtonCompleteShipment" type="button" class="btn btn-success" @click.prevent="confirmComplete">Complete Shipment</button>
            </div>
        </div>
    </BlockUI>
    <pre v-if="debug" class="mt-3">{{ debug }}</pre>

    <ConfirmDialog group="add-box">
        <template #message="slotProps">
            <div>Adding box [<strong>{{ slotProps.message.message }}</strong>] to this shipment.  Please confirm.</div>
        </template>
    </ConfirmDialog>
    <ConfirmDialog group="remove-box">
        <template #message="slotProps">
            <div>You are about to remove box [<strong>{{ slotProps.message.message }}</strong>] from this shipment.  Please confirm.</div>
        </template>
    </ConfirmDialog>
    <ConfirmDialog group="complete" contentClass="flex-column align-items-start">
        <template #message="slotProps">
            <div>You are about to complete the following shipment:</div>
            <div class="p-2 my-2 border-left border-start border-success border-3 font-weight-normal">{{ slotProps.message.message }}</div>
            <div>Please click 'Confirm' to complete the process.</div>
        </template>
    </ConfirmDialog>

    <Toast position="bottom-right"></Toast>
    <shipment-modal ref="shipmentModal"></shipment-modal>
    <ProgressSpinner v-show="isLoading" class="overlay"/>
</template>

<style>
.overlay {
    position: fixed !important;
    top: calc(50% - 50px);
    left: calc(50% - 50px);
    z-index: 100; /* this seems to work for me but may need to be higher*/
}
</style>