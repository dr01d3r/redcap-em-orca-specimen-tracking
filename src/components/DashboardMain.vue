<script setup>
import {ref, computed, watch, onMounted, nextTick, provide} from 'vue';
import { isEmpty, isNotEmpty } from '@primeuix/utils/object';
import ModuleUtils from '../ModuleUtils';
import SpecimenForm from './SpecimenForm.vue';
import SpecimenModal from './SpecimenModal.vue';
import BoxList from './BoxList.vue';
import BoxListModal from './BoxListModal.vue';
import {useConfirm} from "primevue/useconfirm";
import {useToast} from 'primevue/usetoast';

const confirm = useConfirm();
const confirmDelete = (specimen) => {
    confirm.require({
        group: 'delete',
        message: specimen.specimen_name,
        header: 'Deleting Specimen',
        accept: () => {
            deleteSpecimen(specimen.record_id);
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

const rx_box_size = /^(?<rows>[0-9]+)x(?<cols>[0-9]+)$/;
const rx_position = /^(?<row>[A-Z]+)(?<col>[0-9]+)$/;

const plateColors = [
    'blue',
    'purple',
    'pink',
    'orange',
    'yellow',
    'teal',
    'cyan',
    'red'
];

const search_input = ref();
const search_value = ref();
const boxes = ref([]);
const boxListModal = ref();

const specimenForm = ref();
const specimenModal = ref();
const isLoading = ref(false);
const debug = ref();
const errors = ref([]);

const config = ref({});
const metadata = ref({});
const forceReadOnly = ref(false);
const currentPosition = ref();
const maxSpecimens = ref();

const showBoxDisplayInfo = ref(false);

/**
    "A": {
        "1": {
            position: "A1",
            isAvailable: true,
            participantGroup: null,
            specimen: null,
            isSelected: true
        },
    },
*/
const positions = ref({});

const box = ref();
const specimens = ref([]);

// WATCHERS
watch(() => currentPosition.value, async (newPos, oldPos) => {
    if (oldPos) {
        oldPos.isSelected = false;
    }
    if (newPos) {
        newPos.isSelected = true;
    }
});
watch(() => box.value, async (newVal, oldVal) => {
    if (newVal !== null) {
        search_value.value = null;
        search_input.value.blur();
        // specimenForm.value.resetSpecimen();
        initializeBox();
    }
    setUrlState();
});

// COMPUTED
const plateRecordId = computed(() => {
    if (box.value) {
        return box.value.record_id;
    } else {
        return null;
    }
});
const isBoxFull = computed(() => {
    if (isNotEmpty(maxSpecimens.value) && isNotEmpty(specimens.value)) {
        return maxSpecimens.value === specimens.value.length;
    }
    return false;
});
const sortedSpecimens = computed(() => {
    if (specimens.value != null) {
        return specimens.value.sort((a, b) => {
            // parse the positions
            let x = ModuleUtils.toNumericBoxPosition(a.box_position, rx_position, boxSize.value.cols);
            let y = ModuleUtils.toNumericBoxPosition(b.box_position, rx_position, boxSize.value.cols);
            // TODO this is based on row->col box layout. update if support for more layouts is needed
            return x - y;
        });
    }
    return [];
});
const boxSize = computed(() => {
    if (isNotEmpty(box.value) && isNotEmpty(box.value.box_size)) {
        let m = rx_box_size.exec(box.value.box_size);
        if (m !== null) {
            return {
                rows: parseInt(m.groups.rows),
                cols: parseInt(m.groups.cols)
            };
        }
    }
    return {
        rows: 0,
        cols: 0
    };
});
const boxDisplayInfo = computed(() => {
    if (isEmpty(box.value)) return null;
    let data = {};
    for (const fn in config.value['save-state']['box']) {
        if (config.value['save-state']['box'][fn]['specimen-dashboard']) {
            let v = box.value[fn];
            if (isNotEmpty(config.value['fields']['box'][fn]['choices'])) {
                v = config.value['fields']['box'][fn]['choices'][v];
            }
            data[fn] = {
                label: config.value['fields']['box'][fn]['field_label'],
                value: v
            };
        }
    }
    return data;
});
const showButtonNewBox = computed(() => {
    return config.value && config.value.new_box_url;
});
const showShipmentDashboardLink = computed(() => {
    return box.value && box.value.shipment_record_id;
});
const isReadOnly = computed(() => {
    return forceReadOnly.value || isNotEmpty(errors.value);
});
const isPlateStatusClosed = computed(() => {
    return box.value && box.value.box_status === 'closed';
});
const canEnterSpecimens = computed(() => {
    return !isReadOnly.value && !isBoxFull.value && !isPlateStatusClosed.value;
});
const canEditSpecimens = computed(() => {
    return !isReadOnly.value && !isPlateStatusClosed.value;
});
const specimenDisplayValue = (f, v) => {
    if (isNotEmpty(v)) {
        try {
            let dv = v;
            let fm = config.value?.fields?.specimen[f] ?? {};
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

// METHODS
const deleteSpecimen = (id) => {
    isLoading.value = true;
    OrcaSpecimenTracking().jsmo.ajax('delete-specimen', {
        specimen_record_id: id
    })
    .then(response => {
        // success
        if (response === true) {
            handleContext();
            showToast(
                'success',
                'Success',
                'Specimen Deleted Successfully'
            );
        } else {
            // look for possible errors during save result
            if (isNotEmpty(response?.errors ?? {})) {
                let errMsg = '';
                if (Array.isArray(response.errors)) {
                    errMsg = response.errors.join('\r\n');
                } else {
                    errMsg = response.errors;
                }
                showToast(
                    'error',
                    'Specimen Delete Failed',
                    errMsg
                );
            } else {
                showToast(
                    'error',
                    'Specimen Delete Failed',
                    'An unknown error occurred while trying to delete the specimen'
                );
            }
        }
    })
    .catch(err => {
        debug.value = err;
    })
    .finally(() => isLoading.value = false);
};
const initializeBox = () => {
    let tempPositions = {};
    let tempCount = 0;
    currentPosition.value = null;
    for (let r = 0; r < boxSize.value.rows; r++) {
        let row = {};
        let rx = r;
        let rp = '';
        let rpi = null; // row prefix index
        // if the rows exceed the alphabet, we need to attach a prefix
        if (rx >= config.value.alphabet.length) {
            rpi = Math.floor(r / config.value.alphabet.length) - 1;
            rx = r % config.value.alphabet.length;
            rp = config.value.alphabet[rpi];
        }
        const rowKey = `${rp}${config.value.alphabet[rx]}`;
        for (let c = 0; c < boxSize.value.cols; c++) {
            const colKey = (c + 1);
            const isAvailable = isPositionAvailable(r + 1, c + 1);
            row[colKey] = {
                position: rowKey + colKey,
                isAvailable: isAvailable,
                isSelected: false,
                specimen: null,
            };
            if (isAvailable) {
                tempCount++;
            }
        }
        tempPositions[rowKey] = row;
    }
    maxSpecimens.value = tempCount;
    positions.value = tempPositions;

    if (isNotEmpty(specimens.value)) {
        loadSpecimensToBox(specimens.value);
    }
    currentPosition.value = getNextAvailablePosition();
    setTimeout(() => {
        resetFocus();
    }, 250);
};
const editSpecimen = (record_id) => {
    // trigger the modal to show and load target specimen
    if (specimenModal.value) {
        specimenModal.value.editSpecimen(record_id);
    }
};
const tryDeleteSpecimen = (s) => {
    confirmDelete(s);
};
const resetToList = () => {
    box.value = null;
    search_value.value = null;
    getBoxList();
};
const setUrlState = () => {
    if (isEmpty(box.value)) {
        // 'id=' will not get removed, so force an arbitrary value so it'll be completely removed
        ModuleUtils.qs_push("id", false, true);
        ModuleUtils.qs_remove("id", true);
    } else {
        ModuleUtils.qs_push("id", box.value.record_id, true);
    }
};
const resetFocus = () => {
    if (specimenForm.value) {
        specimenForm.value.resetFocus();
    }
};
const specimenSaved = (specimen) => {
    if (specimen) {
        const index = specimens.value.findIndex(s => s.record_id === specimen.record_id);
        if (index >= 0) {
            // was this a move within the existing box?
            if (specimens.value[index].box_position !== specimen.box_position) {
                // move the specimen
                moveSpecimenWithinBox(specimens.value[index].box_position, specimen)
                // update specimen list
                specimens.value.splice(index, 1, specimen);
                // toast!
                showToast(
                    'success',
                    'Move Successful!',
                    'Specimen moved successfully'
                );
            } else {
                specimens.value.splice(index, 1, specimen);
                showToast(
                    'success',
                    'Edit Successful!',
                    'Specimen edited successfully'
                );
            }
        } else {
            specimens.value.push(specimen);
            showToast(
                'success',
                'Save Successful!',
                'Specimen added to the box'
            );
            loadSpecimensToBox([specimen]);
        }
        currentPosition.value = getNextAvailablePosition();
    }
};
const positionSelected = (pos) => {
    if (pos.specimen === null && pos.isAvailable === true) {
        currentPosition.value = pos;
    }
};
const moveSpecimenWithinBox = (oldPos, specimen) => {
    let tmpArr = {};
    let rxOld = rx_position.exec(oldPos);
    let rxNew = rx_position.exec(specimen.box_position);
    if (rxOld === null || rxNew === null) {
        tmpArr['specimen_move'] = `Failed to move specimen from '${oldPos}' to '${specimen.box_position}' - invalid/missing value for Box Position.`;
    } else {
        positions.value[rxNew[1]][rxNew[2]].specimen = specimen;
        positions.value[rxOld[1]][rxOld[2]].specimen = null;
    }
    if (isNotEmpty(tmpArr)) {
        errors.value = Object.assign(errors.value, tmpArr);
    }
};
const loadSpecimensToBox = (specimens) => {
    let tmpArr = {};
    for (const s of specimens) {
        let w = rx_position.exec(s.box_position);
        if (w === null) {
            tmpArr[`specimen_load_${s.record_id}`] = `Failed to add specimen '${s.specimen_name}' to the box - invalid/missing value for Box Position.`;
        } else {
            const row = w[1];
            const col = w[2];
            positions.value[row][col].specimen = s;
        }
    }
    if (isNotEmpty(tmpArr)) {
        errors.value = Object.assign(errors.value, tmpArr);
    }
};
const positionClass = (pos) => {
    let wc = [];
    if (pos.specimen != null) {
        wc.push(getPositionColorForSpecimen(pos.specimen));
    } else if (pos.isSelected === true) {
        wc.push('box-position-selected');
    } else if (pos.isAvailable === true) {
        wc.push('box-position-empty');
    } else {
        wc.push('box-position-dark-800');
    }
    return wc.join(' ');
};
const positionTitle = (pos) => {
    if (pos.specimen !== null) {
        return pos.specimen.specimen_name
    } else if (pos.isAvailable !== true) {
        return "UNAVAILABLE";
    } else {
        return "EMPTY";
    }
};
const isPositionAvailable = (row, col) => {
    return true;
};
const getPositionColorForSpecimen = (specimen) => {
    let colorIndex = 0;
    const c = plateColors[colorIndex];
    return `box-position-${c}-800`;
};
const getPositionForSpecimen = (specimen) => {
    let response = {
        result: false,
        position: null,
        errors: {
            specimen: {
                name: []
            }
        }
    };
    try {
        // all other box types (standard layouts)
        let pos = getNextAvailablePosition();
        if (pos !== null) {
            currentPosition.value = pos;
            response.result = true;
            response.position = pos.position;
        } else {
            throw `Unable to obtain a valid box position.`;
        }
    } catch (e) {
        response.result = false;
        response.errors.specimen.specimen_name.push(e);
    }
    return response;
};
const getNextAvailablePosition = () => {
    // if no position is currently selected, finds the next open spot
    // if position is selected, find the next available position in box sequence (i.e. row->col)
    let targetPosition = "A1";
    if (currentPosition.value !== null) {
        if (currentPosition.value.isAvailable === true && currentPosition.value.specimen === null) {
            return currentPosition.value;
        } else {
            // new position based on current context
            // this will fail, but it's the simplest way to get to the next ordered position
            targetPosition = currentPosition.value.position;
        }
    }
    // current position doesn't work, so lets iterate!
    let rx_pos = rx_position.exec(targetPosition);
    let posRow = config.value.alphabet.indexOf(rx_pos[1]);
    let posCol = rx_pos[2];
    // row->col box order
    // row loop is 0-based, so use '<'
    for (let r = posRow; r < boxSize.value.rows; r++) {
        let rAlpha = config.value.alphabet[r];
        // col loop is 1-based, so use '<='
        for (let c = posCol; c <= boxSize.value.cols; c++) {
            let pos = positions.value[rAlpha][c];
            if (pos.isAvailable === true && pos.specimen === null) {
                // if we found one, return it
                return pos;
            }
        }
        // if we get here, we're wrapping to the next row
        // reset column to 1
        posCol = 1;
    }
    return null;
};
const gotoShipmentDashboard = () => {
    window.location.href = `${config.value.shipment_dashboard_base_url}&id=${box.value.shipment_record_id}`;
};
const gotoNewBoxURL = () => {
    window.location.href = config.value.new_box_url;
}

const boxSelected = (e) => {
    if (isNotEmpty(e.data)) {
        getBox(e.data.record_id);
    } else {
        showToast('error', 'Error', 'An unexpected error occurred while trying to select a box.');
    }
};
const handleContext = () => {
    let id = ModuleUtils.qs_get('id');
    if (id && `${id}`.length > 0) {
        // get the box
        getBox(id);
    } else {
        // get the box list
        getBoxList();
    }
};
const getBox = (id) => {
    isLoading.value = true;
    OrcaSpecimenTracking().jsmo.ajax('get-box', {
        id: id
    })
    .then((response) => {
        if (isNotEmpty(response.errors)) {
            if (Array.isArray(response.errors)) {
                errors.value.push(...response.errors);
            } else {
                errors.value.push(response.errors);
            }
        } else {
            config.value.box_record_home_url = response.config?.box_record_home_url;
            // ensure specimens is set first, since box watch depends on them
            specimens.value = response.specimens ?? [];
            box.value = response.box;
        }
    })
    .catch((err) => {
        debug.value = err;
    })
    .finally(() => {
        isLoading.value = false;
    });
};
const getBoxList = () => {
    isLoading.value = true;
    boxes.value = null;
    OrcaSpecimenTracking().jsmo.ajax('get-box-list', {
        // no parameters
    })
    .then((response) => {
        if (isNotEmpty(response.errors)) {
            if (Array.isArray(response.errors)) {
                errors.value.push(...response.errors);
            } else {
                errors.value.push(response.errors);
            }
        } else {
            boxes.value = response.boxes;
        }
    })
    .catch((err) => {
        debug.value = err;
    })
    .finally(() => {
        isLoading.value = false;
    });
};
const search = async (e) => {
    // first automatically trim the search value
    if (isNotEmpty(search_value.value)) {
        search_value.value = search_value.value.trim();
    }
    isLoading.value = true;
    OrcaSpecimenTracking().jsmo.ajax('search-box-list', {
        search: search_value.value
    })
    .then(response => {
        // look for possible errors during save result
        if (isNotEmpty(response.errors)) {
            if (Array.isArray(response.errors)) {
                errors.value.push(...response.errors);
            } else {
                errors.value.push(response.errors);
            }
        } else {
            // show the modal with the search value and results
            boxListModal.value.show(search_value.value, response.boxes);
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
};
const initializeDashboard = async () => {
    isLoading.value = true;
    OrcaSpecimenTracking().jsmo.ajax('initialize-box-dashboard', {})
    .then(response => {
        // look for possible errors during save result
        if (isNotEmpty(response.errors)) {
            if (Array.isArray(response.errors)) {
                errors.value.push(...response.errors);
            } else {
                errors.value.push(response.errors);
            }
            isLoading.value = false;
        } else {
            if (response) {
                // success
                config.value = response.config;
                handleContext();
            } else {
                debug.value = response;
                isLoading.value = false;
            }
        }
    })
    .catch(err => {
        debug.value = err;
        isLoading.value = false;
    });
};

provide('getPositionForSpecimen', getPositionForSpecimen);

onMounted(() => {
    nextTick(() => {
        initializeDashboard();
    });
});
</script>

<template>
    <BlockUI :blocked="isLoading">
        <div class="projhdr">
            <i class="fas fa-vials text-dark"></i>&nbsp;Specimen Entry Dashboard
            <template v-if="config.box_record_home_url">
                <span>&nbsp;|&nbsp;</span>
                <a :href="config.box_record_home_url" class="text-primary ml-1 text-decoration-none">
                    <i class="fas fa-share"></i>&nbsp;Record Home
                </a>
            </template>
            <template v-if="showShipmentDashboardLink">
                <span>&nbsp;|&nbsp;</span>
                <a href="javascript:void(0)" @click.prevent="gotoShipmentDashboard()" class="text-primary ml-1 text-decoration-none">
                    <i class="fas fa-truck"></i>&nbsp;Shipment Dashboard
                </a>
            </template>
        </div>

        <div class="row">
            <div class="col-xl-4 col-lg-6">
                <div class="input-group input-group-sm mb-3">
                    <input ref="search_input" type="text" class="form-control" placeholder="Search Anything!" v-model="search_value" @keyup.enter="search" v-focus />
                    <button type="button" title="Search Result" class="btn btn-primary" @click="search">
                        <i class="fas fa-search"></i>
                    </button>
                    <template v-if="box">
                        <!-- "back to list" button -->
                        <button type="button" class="btn btn-outline-primary" @click="resetToList"><i class="fas fa-list"></i>&nbsp;List</button>
                    </template>
                    <template v-if="showButtonNewBox">
                        <button type="button" class="btn btn-outline-success"@click="gotoNewBoxURL"><i class="fas fa-plus"></i>&nbsp;New</button>
                    </template>
                </div>
            </div>
        </div>

        <template v-if="isNotEmpty(errors)">
            <div class="alert alert-danger p-4">
                <h1 class="display-4">Critical Errors Exist!</h1>
                <p class="lead mb-0">This dashboard has been disabled until all critical errors have been resolved.</p>
                <hr/>
                <ul>
                    <template v-for="(v, k) in errors">
                        <li v-html="v"></li>
                    </template>
                </ul>
            </div>
        </template>

        <!-- MAIN CONTENT AREA -->
        <template v-if="box">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col">
                            <div class="mb-2">
                                <div class="d-flex gap-2 align-items-center">
                                    <label class="col-form-label">Box Name</label>
                                </div>
                                <input type="text" :value="box.box_name" class="form-control" disabled="disabled"/>
                            </div>
                            <template v-if="canEnterSpecimens">
                                <specimen-form
                                    ref="specimenForm"
                                    mode="new"
                                    :config="config"
                                    :box_record_id="plateRecordId"
                                    :box_position="currentPosition"
                                    :box_info="box"
                                    @specimenSaved="specimenSaved"
                                ></specimen-form>
                            </template>
                            <div class="alert alert-warning border border-warning mt-3" v-if="isBoxFull">Specimen Entry
                                Disabled! The box is full.
                            </div>
                            <div class="alert alert-warning border border-warning mt-3" v-if="isPlateStatusClosed"><strong>NOTICE:</strong>
                                This box is CLOSED, and as such, has been put into a read-only state.
                            </div>
                        </div>
                        <div class="col-auto mt-2">
                            <template v-if="isNotEmpty(boxDisplayInfo)">
                                <div class="d-flex align-items-center">
                                    <strong>Box Information</strong>
                                    <button type="button" class="ms-auto btn btn-primary btn-xs"
                                            @click="() => showBoxDisplayInfo = !showBoxDisplayInfo">
                                        <template v-if="showBoxDisplayInfo">
                                            <i class="fas fa-chevron-up"></i>&nbsp;Collapse
                                        </template>
                                        <template v-else>
                                            <i class="fas fa-chevron-down"></i>&nbsp;Expand
                                        </template>
                                    </button>
                                </div>
                                <hr class="mt-1 mb-0" />
                                <table v-if="showBoxDisplayInfo" class="table table-striped">
                                    <tbody>
                                    <tr v-for="(v, k) in boxDisplayInfo">
                                        <th>{{ v['label'] }}</th>
                                        <td>{{ v['value'] }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </template>
                            <template v-if="boxSize && config.alphabet">
                                <label class="col-form-label">Box Preview<span v-if="currentPosition">&nbsp;(<strong>{{ currentPosition.position }}</strong>)</span></label>
                                <div class="container box-preview border border-dark selectable">
                                    <div class="box-preview-header row text-center font-weight-bold bg-dark text-light">
                                        <div class="col-header py-2 px-0">#</div>
                                        <template v-for="col in boxSize.cols">
                                            <div class="col-header py-2 px-0">{{ col }}</div>
                                        </template>
                                    </div>
                                    <div class="row">
                                        <div class="box-preview-rows col overflow-y-auto g-0">
                                            <template v-for="(row, rowKey) in positions">
                                                <div class="d-flex">
                                                    <div class="row-header bg-dark text-light font-weight-bold d-flex align-items-center justify-content-center">
                                                        <span>{{ rowKey }}</span>
                                                    </div>
                                                    <template v-for="(pos, colKey) in row">
                                                        <div class="box-position px-0"
                                                             :class="[ positionClass(pos) ]"
                                                             :title="positionTitle(pos)"
                                                             :id="'position-' + pos.position"
                                                             :data-position-label="pos.position"
                                                             @click="positionSelected(pos)"
                                                        >
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="config && config['save-state'] && config['fields']">
                        <table id="dashboard_table" class="table mb-0">
                            <thead>
                            <tr>
                                <!-- actions -->
                                <th v-if="canEditSpecimens" class="text-center">Actions</th>
                                <!-- name -->
                                <th>{{ config['fields']['specimen']['specimen_name']['field_label'] }}</th>
                                <!-- box_position -->
                                <th>{{ config['fields']['specimen']['box_position']['field_label'] }}</th>
                                <!-- configured fields -->
                                <template v-for="(fv, fk) in config['save-state']['specimen']">
                                    <th v-if="!config['fields']['specimen'][fk]['config']['specimen-list']['required'] && fv['specimen-list']">{{ config['fields']['specimen'][fk]['field_label'] }}</th>
                                </template>
                            </tr>
                            </thead>
                            <tbody>
                            <template v-for="(s, index) in sortedSpecimens" :key="s.record_id">
                                <tr>
                                    <!-- actions -->
                                    <td class="text-center" v-if="canEditSpecimens">
                                        <button type="button" class="btn btn-sm btn-link py-0"
                                                @click="editSpecimen(s.record_id)">
                                            <i class="fas fa-edit" style="display: inline;"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-link text-danger py-0"
                                                @click="tryDeleteSpecimen(s)">
                                            <i class="fas fa-times" style="display: inline;"></i>
                                        </button>
                                    </td>
                                    <!-- name -->
                                    <th scope="row" :data-sort="s.specimen_name">
                                        {{ s.specimen_name }}
                                    </th>
                                    <!-- box_position -->
                                    <td :data-sort="s.box_position">
                                        {{ s.box_position }}
                                    </td>
                                    <!-- configured fields -->
                                    <template v-for="(fv, fk) in config['save-state']['specimen']">
                                        <td v-if="!config['fields']['specimen'][fk]['config']['specimen-list']['required'] && fv['specimen-list']">{{ specimenDisplayValue(fk, s[fk]) }}</td>
                                    </template>
                                </tr>
                            </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
        <template v-else>
            <div class="card">
                <div class="card-header">
                    <span>This dashboard shows you all <strong>Available</strong> boxes.</span>
                    <hr class="my-2">
                    <ul class="mb-0">
                        <li>Select a box to see its full details and take action.</li>
                        <li>Boxes in a <strong>Closed</strong> status will not appear in this list.</li>
                    </ul>
                    <hr class="my-2">
                    <span>To search for a box that is <strong>not</strong> in this list, use the search at the top of this page.</span>
                </div>
                <div class="card-body px-3 py-1">
                    <box-list :boxes="boxes" @selected="boxSelected"></box-list>
                </div>
            </div>
        </template>

        <div v-if="debug" class="mt-3">
            <pre>{{ debug }}</pre>
        </div>
        <Toast position="bottom-right">
            <template #message="slotProps">
                <div class="p-toast-message-text" data-pc-section="messagetext">
                    <span v-if="isNotEmpty(slotProps.message.summary)" class="p-toast-summary" data-pc-section="summary" v-html="slotProps.message.summary"></span>
                    <div v-if="isNotEmpty(slotProps.message.detail)" class="p-toast-detail" data-pc-section="detail" v-html="slotProps.message.detail"></div>
                </div>
            </template>
        </Toast>
        <ConfirmDialog group="delete">
            <template #container="{ message, acceptCallback, rejectCallback }">
                <div class="card">
                    <div class="card-header bg-danger">
                        <h5 class="text-light">{{ message.header }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center"><h5 class="lead">You are about to delete the following specimen:</h5>
                            <hr>
                            <h5 class="lead text-danger text-monospace font-weight-bold">[{{ message.message }}]</h5>
                            <hr>
                            <div class="lead text-danger mb-0"><i class="fas fa-exclamation-triangle"></i><span
                                class="mx-3">This action cannot be undone!</span><i
                                class="fas fa-exclamation-triangle"></i></div>
                        </div>
                    </div>
                    <div class="card-footer bg-danger-subtle">
                        <div class="d-flex gap-2 justify-content-end">
                            <button class="btn btn-outline-dark" label="Cancel" outlined @click="rejectCallback">
                                Cancel
                            </button>
                            <button class="btn btn-danger text-uppercase" label="Confirm" @click="acceptCallback">
                                DELETE
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </ConfirmDialog>
        <specimen-modal
            ref="specimenModal"
            :config="config"
            :box_record_id="plateRecordId"
            :box_info="box"
            @specimenSaved="specimenSaved"
        ></specimen-modal>
        <box-list-modal ref="boxListModal" @selected="boxSelected"></box-list-modal>
        <ProgressSpinner v-show="isLoading" class="overlay"/>
    </BlockUI>
</template>

<style lang="scss">
@import "../node_modules/bootstrap/scss/functions";
@import "../node_modules/bootstrap/scss/variables";
@import "../node_modules/bootstrap/scss/mixins";
@import "../node_modules/bootstrap/scss/alert";

@each $color, $value in $colors {
    .box-position-#{$color} {
        border-color: $white;
        background-color: $value;
    }
    .box-position-#{$color}-800 {
        border-color: $white;
        background-color: lighten($value, 20%);
    }
}

@each $color, $value in $theme-colors {
    .box-position-#{$color} {
        border-color: $white;
        background-color: $value;
    }
    .box-position-#{$color}-800 {
        border-color: $white;
        background-color: lighten($value, 20%);
    }
}

.overlay {
    position: fixed !important;
    top: calc(50% - 50px);
    left: calc(50% - 50px);
    z-index: 100; /* this seems to work for me but may need to be higher*/
}

// ensure the fixed headers stay aligned with content due to scrollbar
.margin-scrollbar {
    margin-right: calc((-.5 * var(--bs-gutter-x)) + var(--scrollbar-width));
}

.box-position-selected {
    color: var(--bs-success) !important;
    background-color: var(--bs-success-bg-subtle) !important;
    border-color: var(--bs-success) !important;
}

#dashboard_table th {
    font-weight: bold;
}

.dataTables_wrapper ul {
    -webkit-padding-start: 20px;
    margin-bottom: 0px;
}

.box-preview {
    --bp-cell-size: 34px;
}

.box-preview .row-header {
    text-align: center;
}

.box-preview .col-header,.box-preview .row-header,.box-preview .box-position {
    width: var(--bp-cell-size);
}
.box-preview .col-header {
    height: var(--bp-cell-size);
}
.box-preview .row-header,.box-preview .box-position {
    height: var(--bp-cell-size);
}

.box-preview .box-position {
    border: 1px solid #fff;
}

.box-preview.selectable .box-position-empty {
    cursor: pointer;
}

.box-preview-header {
    overflow: hidden;
    scrollbar-gutter: stable;
}

.box-preview-rows {
    max-height: calc(var(--bp-cell-size) * 13);
    scrollbar-gutter: stable;
}

</style>