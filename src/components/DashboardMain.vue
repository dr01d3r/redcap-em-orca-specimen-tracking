<template>
    <b-overlay variant="light"
               blur="50px"
               spinner-variant="dark"
               spinner-small
               opacity="0.95"
               :show="isOverlayed"
               rounded="sm">
        <template #overlay>
            <loader />
        </template>

        <div class="projhdr">
            <i class="fas fa-vials text-dark"></i>&nbsp;Sample Entry Dashboard
            <template v-if="config.box_record_home_url != null">
                <span>&nbsp;|&nbsp;</span><a :href="config.box_record_home_url" class="text-primary ml-1"><i class="fas fa-share"></i>&nbsp;Record Home</a>
            </template>
            <template v-if="showShipmentDashboardLink">
                <span>&nbsp;|&nbsp;</span><a href="javascript:void(0)" @click.prevent="gotoShipmentDashboard()" class="text-primary ml-1"><i class="fas fa-truck"></i>&nbsp;Shipment Dashboard</a>
            </template>
        </div>

        <template v-if="!isObjectEmpty(errors)">
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
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col">
                        <div class="mb-2">
                            <label class="col-form-label">Selected Box</label>
                            <template v-if="showButtonNewBox">
                                <a class="btn btn-xs btn-success text-light" :href="config.new_plate_url"><i class="fas fa-plus"></i>&nbsp;New</a>
                            </template>
                            <template v-if="showButtonSearch">
                                <button class="btn btn-xs btn-primary" @click="togglePlateSearch"><i class="fas fa-search"></i>&nbsp;Search</button>
                                <input type="text" :value="plate.box_name" class="form-control" readonly="readonly" />
                            </template>
                            <template v-if="showButtonCancel">
                                <button class="btn btn-xs btn-danger" @click="togglePlateSearch"><i class="fas fa-times"></i>&nbsp;Cancel</button>
                            </template>
                            <b-form id="form_plate_search" @submit.prevent v-if="showPlateSearch">
                                <b-form-input
                                        id="plate_search_input"
                                        ref="plate_search_input"
                                        autocomplete="off"
                                        @keyup.enter="trySearchPlate"
                                        @blur="trySearchPlate"
                                        v-model="plate_search"
                                        :state="v$.plate_search.$error ? false : null"
                                        placeholder="Scan or Type the Box Name"
                                ></b-form-input>
                                <b-alert variant="danger" class="mt-1 mb-0 px-3 py-2"
                                         v-if="v$.plate_search.$error"
                                         show
                                >
                                    <strong>Validation Error:</strong>
                                    <ul class="mb-0">
                                        <li v-for="error of v$.plate_search.$errors" :key="error.$uid">{{ error.$message }}</li>
                                    </ul>
                                </b-alert>
                            </b-form>
                        </div>
                        <template v-if="plate !== null && canEnterSpecimens">
                            <specimen-form
                                    ref="specimenAddForm"
                                    mode="new"
                                    :config="config"
                                    :box_record_id="plateRecordId"
                                    :box_info="plate"
                                    @specimenSaved="specimenSaved"
                            ></specimen-form>
                        </template>
                        <b-alert variant="warning" class="border border-warning mt-3" v-if="isPlateFull" show>Specimen Entry Disabled!  The box is full.</b-alert>
                        <b-alert variant="warning" class="border border-warning mt-3" v-if="isPlateStatusClosed" show><strong>NOTICE:</strong> This box is CLOSED, and as such, has been put into a read-only state. </b-alert>
                    </div>
                    <template v-if="plate && config && config.plate_size && config.alphabet">
                        <div class="col-auto border-left border-start">
                            <label class="col-form-label">Box Preview</label>
                            <div class="plate-preview border border-dark" v-bind:class="[ plateClass() ]">
                                <div class="row text-center font-weight-bold bg-dark text-light no-gutters g-0">
                                    <div class="col-header py-2">#</div>
                                    <template v-for="col in config.plate_size.col">
                                        <div class="col-header py-2">{{ col }}</div>
                                    </template>
                                </div>
                                <template v-for="(row, rowKey) in wells">
                                    <div class="row no-gutters g-0">
                                        <div class="row-header bg-dark text-light font-weight-bold py-2">
                                            <span>{{ rowKey }}</span>
                                        </div>
                                        <template v-for="(well, colKey) in row">
                                            <div class="plate-well px-0"
                                                 v-bind:class="[ wellClass(well) ]"
                                                 :title="wellTitle(well)"
                                                 :id="'well-' + well.wellPosition"
                                                 :data-well-label="well.wellPosition"
                                                 v-on:click="wellSelected(well)"
                                            >
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div class="card-body">
                <div>
                    <input v-if="plate != null" type="hidden" name="plate[record_id]" :value="plateRecordId" />
                    <table id="specimen_table" class="table mb-0">
                        <thead>
                        <tr>
                            <th scope="col" class="text-center">x</th>
                            <th scope="col">Full Name</th>
                            <th scope="col">Pos</th>
                            <th scope="col">Collection</th>
                            <th scope="col">Processed</th>
                            <th scope="col">Frozen</th>
                            <th scope="col">Volume (ml)</th>
                            <th scope="col">MHN</th>
                        </tr>
                        </thead>
                        <tbody>
                        <template v-for="(s, index) in sortedSpecimens">
                            <tr :key="s.record_id">
                                <!-- actions -->
                                <td class="text-center">
                                    <button v-if="canEditSpecimens" type="button" class="btn btn-sm btn-link py-0" @click="editSpecimen(s.record_id)">
                                        <i class="fas fa-edit" style="display: inline;"></i>
                                    </button>
                                    <button v-if="canEditSpecimens" type="button" class="btn btn-sm btn-link text-danger py-0" @click="tryDeleteSpecimen(s)">
                                        <i class="fas fa-times" style="display: inline;"></i>
                                    </button>
                                </td>
                                <!-- name -->
                                <th scope="row" :data-sort="s.name">
                                    {{ s.name }}
                                </th>
                                <!-- box_position -->
                                <td scope="row" :data-sort="s.box_position">
                                    {{ s.box_position }}
                                </td>
                                <!-- date_time_collected -->
                                <td>
                                    {{ dateTimeFormat(s.date_time_collected) }}
                                </td>
                                <!-- date_time_processed -->
                                <td>
                                    {{ dateTimeFormat(s.date_time_processed) }}
                                </td>
                                <!-- date_time_frozen -->
                                <td>
                                    {{ dateTimeFormat(s.date_time_frozen) }}
                                </td>
                                <!-- volume -->
                                <td>
                                    {{ s.volume }}
                                </td>
                                <!-- mhn -->
                                <td>
                                    {{ s.mhn }}
                                </td>
                            </tr>
                        </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <template v-if="debugMsg != null">
            <pre class="well">{{ debugOutput }}</pre>
        </template>

        <specimen-modal
                ref="specimenModal"
                :config="config"
                :box_record_id="plateRecordId"
                :box_info="plate"
                @specimenSaved="specimenSaved"
        ></specimen-modal>
    </b-overlay>
</template>

<script>
    // QS
    import qs from 'qs';
    // DatePicker
    import DatePicker from 'vue2-datepicker';
    import 'vue2-datepicker/index.css';
    // Luxon
    import { DateTime } from 'luxon';
    // Vuelidate
    import useVuelidate from '@vuelidate/core'
    import {
        helpers
    } from '@vuelidate/validators'
    // Loader
    import loader from '../loader.vue'
    // local components
    import SpecimenForm from './SpecimenForm'
    import SpecimenModal from './SpecimenModal'

    export default {
        setup: () => ({v$: useVuelidate({$lazy: true, $autoDirty: true})}),
        components: {
            qs,
            DatePicker,
            loader,
            SpecimenForm,
            SpecimenModal
        },
        validations() {
            return {
                plate_search: {
                    regexMatch: helpers.withMessage('Value provided does not match the required nomenclature!',
                        (value) => value === null || value.match(this.config.box_name_regex)
                    )
                }
            }
        },
        data() {
            return {
                rx_well_position: /^(?<row>[A-Z])(?<col>[0-99])$/,
                luxonDateFormatFrom: 'yyyy-MM-dd HH:mm',
                show_plate_search: false,
                plate_search: null,
                plate: null,
                specimens: [],
                config: {},
                errors: {},
                debugMsg: null,
                forceReadOnly: false,
                isOverlayed: false,
                isPlateFull: false,
                currentWell: null,
                maxSpecimens: null,
                wells: {
                    /*
                    "A": {
                        "1": {
                            wellPosition: "A1",
                            isAvailable: true,
                            participantGroup: null,
                            specimen: null,
                            isSelected: true
                        },
                    },
                    */
                },
                participantMap: {
                    /*
                    "1234": {
                        "participantGroup": 1
                    }
                    */
                },
                plateColors: [
                    'blue',
                    // 'indigo',
                    'purple',
                    'pink',
                    'orange',
                    'yellow',
                    'teal',
                    'cyan',
                    'red',
                    // 'green',
                ]
            }
        },
        watch: {
            currentWell: function (newWell, oldWell) {
                if (newWell) {
                    newWell.isSelected = true;
                }
                if (oldWell) {
                    oldWell.isSelected = false;
                }
            },
            specimens: function (newVal, oldVal) {
                // debug
                // console.log('old: ' + oldVal.length + ', new: ' + newVal.length + ', specimens: ' + this.specimens.length);
                this.validateSpecimens();
            }
        },
        computed: {
            plateRecordId: function() {
                if (this.plate) {
                    return this.plate.record_id;
                }
                else {
                    return null;
                }
            },
            debugOutput: function() {
                return JSON.stringify(this.debugMsg, null, '\t');
            },
            sortedSpecimens: function() {
                if (this.specimens != null) {
                    if (this.isTemporaryBoxType(this.plate)) {
                        return this.specimens.sort((a, b) => {
                            const a_pos = this.rx_well_position.exec(a.box_position);
                            const b_pos = this.rx_well_position.exec(b.box_position);
                            const a_grp = this.participantMap[a.name_parsed.participant_id].participantGroup;
                            const b_grp = this.participantMap[b.name_parsed.participant_id].participantGroup;
                            let a_weight = a_grp * 10000;
                            let b_weight = b_grp * 10000;
                            a_weight += parseInt(a_pos[2]) * 100;
                            b_weight += parseInt(b_pos[2]) * 100;
                            a_weight += this.config.alphabet.indexOf(a_pos[1]);
                            b_weight += this.config.alphabet.indexOf(b_pos[1]);
                            if (a_weight > b_weight) { return 1; }
                            if (a_weight < b_weight) { return -1; }
                            return 0;
                        });
                    } else {
                        return this.specimens.sort((a, b) => {
                            // TODO this is based on row->col box layout. update if support for more layouts is needed
                            if (a.box_position < b.box_position) { return -1; }
                            if (a.box_position > b.box_position) { return 1; }
                            return 0;
                        });
                    }
                }
                return [];
            },
            showButtonNewBox: function() {
                return this.config && this.config.new_plate_url;
            },
            showButtonSearch: function() {
                return this.plate && !this.show_plate_search;
            },
            showButtonCancel: function() {
                return this.plate && this.show_plate_search;
            },
            showPlateSearch: function() {
                return !this.plate || this.show_plate_search;
            },
            showShipmentDashboardLink: function() {
                return this.plate && this.plate.shipment_record_id;
            },
            isReadOnly: function() {
                return this.forceReadOnly || !this.isObjectEmpty(this.errors);
            },
            isPlateStatusClosed: function() {
                return this.plate && this.plate.box_status === 'closed';
            },
            canEnterSpecimens: function() {
                return !this.isReadOnly && !this.isPlateFull && !this.isPlateStatusClosed;
            },
            canEditSpecimens: function() {
                return !this.isReadOnly && !this.isPlateStatusClosed;
            }
        },
        methods: {
            async post(action, data, callback, doOverlay = true) {
                if (doOverlay === true) {
                    this.isOverlayed = true;
                }
                data = Object.assign({
                    redcap_csrf_token: OrcaSpecimenTracking().redcap_csrf_token,
                    action: action
                }, data);
                this.axios({
                    url: OrcaSpecimenTracking().url,
                    method: 'post',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    data: qs.stringify(data)
                })
                    .then(response => {
                        if (response.data) {
                            callback(response.data);
                        }
                    })
                    .catch(e => {
                        let errorMsg = 'An unknown error occurred';
                        if (e.response && e.response.data) {
                            errorMsg = e.response.data;
                        }
                        this.toast(
                            errorMsg,
                            'Action Failed',
                            'danger'
                        );
                    })
                    .finally(() => {
                        if (doOverlay === true) {
                            setTimeout(() => {
                                this.isOverlayed = false;
                            }, 250);
                        }
                    });
            },
            async searchPlate(search_value) {
                this.isOverlayed = true;
                this.focusOverride = true;
                if (this.$refs.specimenAddForm) {
                    this.$refs.specimenAddForm.resetSpecimen();
                }
                const data = {
                    redcap_csrf_token: OrcaSpecimenTracking().redcap_csrf_token,
                    action: 'search-plate',
                    search_value: search_value
                };
                this.axios({
                    url: OrcaSpecimenTracking().url,
                    method: 'post',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    data: qs.stringify(data)
                })
                .then(response => {
                    if (response.data) {
                        // clear search value
                        this.plate_search = null;
                        // set plate and specimen values
                        this.plate = response.data.plate ?? null;
                        this.specimens = response.data.specimens ?? [];

                        this.initializePlate();
                    }
                })
                .catch(e => {
                    let errorMsg = 'An unknown error occurred';
                    if (e.response.data) {
                        errorMsg = e.response.data;
                    }
                    this.toast(
                        errorMsg,
                        'Box Search Failed',
                        'danger'
                    );
                })
                .finally(() => {
                    this.show_plate_search = this.plate === null;
                    this.setUrlState();
                    setTimeout(() => {
                        this.isOverlayed = false;
                        this.focusOverride = false;
                        this.resetFocus();
                    }, 250);
                });
            },
            initializePlate: function() {
                let wells = {};
                let maxSpecimens = 0;
                for (let r = 0; r < this.config.plate_size.row; r++) {
                    let row = {};
                    const rowKey = this.config.alphabet[r];
                    for (let c = 0; c < this.config.plate_size.col; c++) {
                        const colKey = (c + 1);
                        const isAvailable = this.isWellAvailable(r+1, c+1);
                        row[colKey] = {
                            wellPosition: rowKey + colKey,
                            isAvailable: isAvailable,
                            isSelected: false,
                            specimen: null,
                        };
                        if (isAvailable) {
                            maxSpecimens++;
                        }
                        if (this.isTemporaryBoxType(this.plate)) {
                            const participantsPerRow = Math.floor(this.config.plate_size.col / this.config.num_visits);
                            const groupOffsetRow = Math.floor(r / this.config.num_specimens) * participantsPerRow;
                            if (isAvailable) {
                                const groupOffsetCol = Math.floor(c / this.config.num_visits);
                                row[colKey].participantGroup = (groupOffsetRow + groupOffsetCol) + 1;
                            }
                        }
                    }
                    wells[rowKey] = row;
                }
                this.maxSpecimens = maxSpecimens;
                this.wells = Object.assign({}, wells);

                this.loadSpecimensToPlate(this.specimens ?? []);
                this.currentWell = this.getNextAvailableWell();
            },
            async initializeDashboard() {
                this.isOverlayed = true;
                this.axios({
                    url: OrcaSpecimenTracking().url,
                    params: {
                        action: 'initialize-box-dashboard',
                        id: this.qs_get('id')
                    }
                })
                .then(response => {
                    this.config = response.data.config;
                    this.plate = response.data.plate ?? null;
                    this.specimens = response.data.specimens ?? [];

                    this.initializePlate();
                })
                .catch(e => {
                    let errorMsg = 'An unknown error occurred';
                    if (e.response.data) {
                        errorMsg = e.response.data;
                    }
                    if (typeof errorMsg === 'string') {
                        errorMsg = [ errorMsg ];
                    }
                    this.errors = Object.assign(this.errors, errorMsg);
                })
                .finally(() => {
                    this.show_plate_search = this.plate === null;
                    this.setUrlState();
                    setTimeout(() => {
                        this.isOverlayed = false;
                        this.resetFocus();
                    }, 250);
                    // debug
                    // this.debugMsg = this.config;
                });
            },
            editSpecimen: function(record_id) {
                // trigger the modal to show and load target specimen
                if (this.$refs.specimenModal) {
                    this.$refs.specimenModal.editSpecimen(record_id);
                }
            },
            async deleteSpecimenCallback(data) {
                if (data === true) {
                    await this.initializeDashboard();
                    this.toast(
                        'Specimen Deleted Successfully',
                        'Success',
                        'success'
                    );
                } else if (typeof(data) === 'string') {
                    this.toast(
                        data,
                        'Specimen Delete Failed',
                        'danger'
                    );
                }
            },
            tryDeleteSpecimen: function(s) {
                const h = this.$createElement;
                const ack = h('div', { class: [ 'text-center' ] }, [
                    h('h5', { class: [ 'lead' ] }, 'You are about to delete the following specimen:'),
                    h('hr'),
                    h('h5', { class: [ 'lead', 'text-danger', 'text-monospace', 'font-weight-bold' ] }, `[${s.name}]`),
                    h('hr'),
                    h('div', { class: [ 'lead', 'text-danger', 'mb-0' ] }, [
                        h('i', { class: [ 'fas', 'fa-exclamation-triangle' ] }),
                        h('span', { class: [ 'mx-3' ] }, 'This action cannot be undone!'),
                        h('i', { class: [ 'fas', 'fa-exclamation-triangle' ] }),
                    ])
                ]);
                this.$bvModal.msgBoxConfirm([ack], {
                    title: 'Deleting Specimen',
                    headerBgVariant: 'danger',
                    headerTextVariant: 'light',
                    footerClass: 'alert-danger',
                    footerBorderVariant: 'danger',
                    okTitle: 'DELETE',
                    okVariant: 'danger',
                    cancelTitle: 'Cancel',
                    centered: true
                })
                .then(value => {
                    if (value === true) {
                        this.post('delete-specimen', { specimen_record_id: s.record_id }, this.deleteSpecimenCallback);
                    }
                })
                .catch(e => {
                    let errorMsg = 'An unknown error occurred';
                    if (e.response.data) {
                        errorMsg = e.response.data;
                    }
                    this.toast(
                        errorMsg,
                        'Add Specimen Failed',
                        'danger'
                    );
                });
            },
            trySearchPlate: function(e) {
                // validate against nomenclature
                if (this.plate_search && !this.v$.plate_search.$error) {
                    this.searchPlate(this.plate_search);
                }
            },
            setUrlState: function() {
                if (this.plate == null) {
                    // 'id=' will not get removed, so force an arbitrary value so it'll be completely removed
                    this.qs_push("id", false, true);
                    this.qs_remove("id", true);
                } else {
                    this.qs_push("id", this.plateRecordId, true);
                }
            },
            togglePlateSearch: function() {
                this.plate_search = null;
                this.show_plate_search = !this.show_plate_search;
                setTimeout(() => {
                    this.resetFocus();
                }, 100);
            },
            resetFocus: function() {
                if (this.show_plate_search === true) {
                    this.focusElement('plate_search_input');
                } else {
                    if (this.$refs.specimenAddForm) {
                        this.$refs.specimenAddForm.resetFocus();
                    }
                }
            },
            focusElement: function(refName) {
                if (!this.focusOverride && this.$refs[refName]) {
                    this.$nextTick(() => {
                        this.$refs[refName].focus();
                    });
                }
            },
            specimenSaved: function(specimen) {
                if (specimen) {
                    const index = this.specimens.findIndex(s => s.record_id === specimen.record_id);
                    if (index >= 0) {
                        this.specimens.splice(index, 1, specimen);
                        this.toast(
                            'Specimen edited successfully',
                            'Edit Successful!',
                            'success'
                        );
                    } else {
                        this.specimens.push(specimen);
                        this.toast(
                            'Specimen added to the box',
                            'Save Successful!',
                            'success'
                        );
                        this.loadSpecimensToPlate([ specimen ]);
                        this.currentWell = this.getNextAvailableWell();
                    }
                }
            },
            wellSelected: function(well) {
                if (!this.isTemporaryBoxType(this.plate)) {
                    if (well.specimen === null && well.isAvailable === true) {
                        this.currentWell = well;
                    }
                }
            },
            updateParticipantMap: function(participant_id, row, col) {
                if (this.isTemporaryBoxType(this.plate)) {
                    if (!this.participantMap.hasOwnProperty(participant_id)) {
                        this.participantMap[participant_id] = {
                            participantGroup: null
                        };
                    }
                    this.participantMap[participant_id].participantGroup = this.wells[row][col].participantGroup;
                }
            },
            loadSpecimensToPlate: function(specimens) {
                let errors = {};
                for (const s of specimens) {
                    let w = this.rx_well_position.exec(s.box_position);
                    if (w === null) {
                        errors[`specimen_load_${s.record_id}`] = `Failed to add specimen '${s.name}' to the box - invalid/missing value for Box Position.`;
                    } else {
                        const row = w[1];
                        const col = w[2];
                        this.wells[row][col].specimen = s;
                        this.updateParticipantMap(s.name_parsed.participant_id, row, col);
                    }
                }
                if (!this.isObjectEmpty(errors)) {
                    this.errors = Object.assign(this.errors, errors);
                }
            },
            plateClass: function() {
                let pc = [];
                if (!this.isTemporaryBoxType(this.plate)) {
                    pc.push('selectable');
                }
                return pc.join(' ');
            },
            wellClass: function(well) {
                let wc = [];
                if (well.specimen != null) {
                    wc.push(this.getWellColorForSpecimen(well.specimen));
                } else if (well.isSelected === true) {
                    wc.push('plate-well-selected');
                } else if (well.isAvailable === true) {
                    wc.push('plate-well-empty');
                } else {
                    wc.push('plate-well-dark-800');
                }
                return wc.join(' ');
            },
            wellTitle: function(well) {
                if (well.specimen !== null) {
                    return well.specimen.name
                } else if (well.isAvailable !== true) {
                    return "UNAVAILABLE";
                } else {
                    return "EMPTY";
                }
            },
            isWellAvailable: function(row, col) {
                // certain logic should only be used for 'temporary' boxes
                if (this.isTemporaryBoxType(this.plate)) {
                    const maxRow = this.config.plate_size.row - (this.config.plate_size.row % this.config.num_specimens);
                    const maxCol = this.config.plate_size.col - (this.config.plate_size.col % this.config.num_visits);
                    return row <= maxRow && col <= maxCol;
                } else {
                    return true;
                }
            },
            getWellColorForSpecimen: function(specimen) {
                let colorIndex = 0;
                if (this.isTemporaryBoxType(this.plate)) {
                    colorIndex = Object.keys(this.participantMap).indexOf(specimen.name_parsed.participant_id);
                    // if we run out of colors, just start over
                    colorIndex = colorIndex % this.plateColors.length;
                }
                const c = this.plateColors[colorIndex];
                return `plate-well-${c}-800`;
            },
            getWellForSpecimen: function(specimen) {
                let response = {
                    result: false,
                    wellPosition: null,
                    errors: {
                        specimen: {
                            name: []
                        }
                    }
                };
                try {
                    if (this.isTemporaryBoxType(this.plate)) {
                        // temporary box type '00'
                        /*
                            get the [participant_id]
                            if it's already mapped to the box
                                use [aliquot_number] to determine row
                                use [visit] to determine column
                                if the well is available
                                    return success result
                                else
                                    return error result
                            else
                         */
                        if (specimen === null) {
                            throw `Cannot determine well position.  No specimen value provided!`;
                        }
                        const rx_spec = specimen.match(this.config.specimen_name_regex);
                        const participant_id = rx_spec.groups['participant_id'];
                        const visit = parseInt(rx_spec.groups['visit']);
                        const aliquot_number = parseInt(rx_spec.groups['aliquot_number']);

                        // get some easy validation out of the way first
                        if (visit <= 0) {
                            throw `Visit number (${visit}) must be greater than zero (0)!`;
                        } else if (visit > this.config.num_visits) {
                            throw `Visit number (${visit}) exceeds allowed limit (${this.config.num_visits})!`;
                        }
                        if (aliquot_number <= 0) {
                            throw `Aliquot number (${aliquot_number}) must be greater than zero (0)!`;
                        } else if (aliquot_number > this.config.num_specimens) {
                            throw `Aliquot number (${aliquot_number}) exceeds allowed limit (${this.config.num_specimens})!`;
                        }
                        // target well position info
                        let wellRow = null;
                        let wellCol = null;
                        // get the first column for this participant
                        let participantGroup = null;
                        let firstCol = null;
                        // is the participant already mapped
                        if (this.participantMap[participant_id]) {
                            // get their column group
                            participantGroup = this.participantMap[participant_id].participantGroup;
                        } else {
                            // ensure there's participant room
                            const participants = Object.keys(this.participantMap);
                            if (participants.length >= this.config.max_participants) {
                                throw `Cannot add participant '${participant_id}' because the box is full!`;
                            } else {
                                // define total available column groups
                                let participantGroups = Array.from(Array(this.config.max_participants).keys(), x => x + 1);
                                // loop through existing participants to find first available participantGroup
                                for (const [participant_id, value] of Object.entries(this.participantMap)) {
                                    participantGroups.splice(participantGroups.indexOf(value.participantGroup), 1);
                                }
                                participantGroup = participantGroups.shift();
                            }
                        }
                        const participantsPerRow = Math.floor(this.config.plate_size.col / this.config.num_visits);

                        // get the target column based on column group and visit
                        let modPG = participantGroup % participantsPerRow;
                        if (modPG === 0) {
                            modPG = participantsPerRow;
                        }
                        firstCol = ((modPG - 1) * this.config.num_visits) + 1;
                        wellCol = firstCol - 1 + visit;

                        // get the row offset based on participantGroup
                        const participantGroupRowOffset = Math.floor((participantGroup - 1) / participantsPerRow) * this.config.num_specimens;
                        // get the target row based on aliquot number and offset
                        wellRow = Object.keys(this.wells)[(aliquot_number + participantGroupRowOffset) - 1];

                        // make sure it's available and not already taken
                        let wellPosition = [ wellRow, wellCol ].join('');
                        let well = this.wells[wellRow][wellCol];
                        if (!well) {
                            throw `Unable to reserve position [${wellPosition}] due to an unknown reason.`;
                        } else if (well.specimen !== null) {
                            throw `A specimen already exists in position [${wellPosition}].`;
                        } else if (well.isAvailable !== true) {
                            throw `Position [${wellPosition}] is not available for use.`;
                        }

                        // if we've gotten this far, we've found a well
                        // set current well and update the response
                        this.currentWell = well;
                        response.result = true;
                        response.wellPosition = wellPosition;

                    } else {
                        // all other box types (standard layouts)
                        let well = this.getNextAvailableWell();
                        if (well !== null) {
                            this.currentWell = well;
                            response.result = true;
                            response.wellPosition = well.wellPosition;
                        } else {
                            throw `Unable to obtain a valid well position.`;
                        }
                    }
                } catch (e) {
                    response.result = false;
                    response["errors"]["specimen"]["name"].push(e);
                }
                return response;
            },
            getNextAvailableWell: function() {
                // determines well position for non-temporary-box
                if (!this.isTemporaryBoxType(this.plate)) {
                    // if no well is currently selected, finds the next open spot
                    // if well is selected, find the next available well in box sequence (i.e. row->col)
                    let targetWellPosition = "A1";
                    if (this.currentWell !== null) {
                        if (this.currentWell.isAvailable === true && this.currentWell.specimen === null) {
                            return this.currentWell;
                        } else {
                            // new well based on current context
                            // this will fail, but it's the simplest way to get to the next ordered position
                            targetWellPosition = this.currentWell.wellPosition;
                        }
                    }
                    // current well doesn't work, so lets iterate!
                    let rx_pos = this.rx_well_position.exec(targetWellPosition);
                    let wellRow = this.config.alphabet.indexOf(rx_pos[1]);
                    let wellCol = rx_pos[2];
                    // row->col plate order
                    // row loop is 0-based, so use '<'
                    for (let r = wellRow; r < this.config.plate_size.row; r++) {
                        let rAlpha = this.config.alphabet[r];
                        // col loop is 1-based, so use '<='
                        for (let c = wellCol; c <= this.config.plate_size.col; c++) {
                            let well = this.wells[rAlpha][c];
                            if (well.isAvailable === true && well.specimen === null) {
                                // if we found one, return it
                                return well;
                            }
                        }
                        // if we get here, we're wrapping to the next row
                        // reset column to 1
                        wellCol = 1;
                    }
                }
                return null;
            },
            isTemporaryBoxType: function(plate) {
                return plate && plate.box_name_parsed.box_type === '00';
            },
            validateSpecimens: function() {
                let errors = {};
                // plate overflow
                if (this.maxSpecimens) {
                    if (this.specimens.length === this.maxSpecimens) {
                        this.isPlateFull = true;
                    } else if (this.specimens.length > this.maxSpecimens) {
                        errors['plate_overflow'] = `Maximum specimen limit exceeded! (count: ${this.specimens.length}, limit: ${this.maxSpecimens})`;
                    }
                }

                // TODO nomenclature alignment

                // temporary storage '00' validation
                if (this.isTemporaryBoxType(this.plate)) {
                    let counters = {
                        // '1234': {
                        //     '01': [
                        //         '21-V1234-v01sr01',
                        //         '21-V1234-v01sr02'
                        //     ]
                        // }
                    };
                    this.specimens.forEach((value, index) => {
                        if (value.name_parsed === null) {
                            errors[`specimen_name_${index}`] = 'Unable to process [' + value.name + ']: parsed value missing)';
                            return;
                        }
                        // get the relevant pieces and add to the count collection
                        let participant_id = value.name_parsed.participant_id;
                        let visit = value.name_parsed.visit;
                        if (participant_id !== null && visit !== null) {
                            if (!counters[participant_id]) {
                                counters[participant_id] = {};
                            }
                            if (!counters[participant_id][visit]) {
                                counters[participant_id][visit] = [];
                            }
                            counters[participant_id][visit].push(value.name);
                        }
                    });
                    const p_count = Object.keys(counters).length;
                    // participant count validation
                    if (p_count > this.config.max_participants) {
                        errors['too_many_participants'] = `Box exceeds the participant limit! (count: ${p_count}, limit: ${this.config.max_participants})`;
                    }
                    // visit count validation
                    for (const [participant_id, visits] of Object.entries(counters)) {
                        const v_count = Object.keys(visits).length;
                        if (v_count > this.config.num_visits) {
                            errors[`pv_${participant_id}`] = `Participant [${participant_id}] exceeds the visit limit! (count: ${v_count}, limit: ${this.config.num_visits})`;
                        }
                        // specimen count validation
                        for (const [visit_number, specimens] of Object.entries(visits)) {
                            if (specimens.length > this.config.num_specimens) {
                                errors[`pvs_${participant_id}_${visit_number}`] = `Participant & Visit [${participant_id}][${visit_number}] exceeds the specimen limit! (count: ${specimens.length}, limit: ${this.config.num_specimens})`;
                            }
                        }
                    }
                }

                if (!this.isObjectEmpty(errors)) {
                    this.errors = Object.assign(this.errors, errors);
                }
            },
            gotoShipmentDashboard: function() {
                window.location.href = `${this.config.shipment_dashboard_base_url}&id=${this.plate.shipment_record_id}`;
            },
            dateTimeFormat: function(dt) {
                if (dt && this.config.datetime_format) {
                    return DateTime.fromFormat(dt, this.luxonDateFormatFrom).toFormat(this.config.datetime_format);
                }
                return dt;
            }
        },
        mounted() {
            this.$nextTick(function () {
                this.initializeDashboard();
            });
        }
    }
</script>

<style>
    #specimen_table th {
        font-weight: bold;
    }

    .dataTables_wrapper ul {
        -webkit-padding-start: 20px;
        margin-bottom: 0px;
    }

    .plate-preview .row-header {
        text-align: center;
    }

    .plate-preview .col-header, .plate-preview .row-header, .plate-preview .plate-well {
        height: 34px;
        width: 34px;
    }

    .plate-preview .plate-well {
        border: 1px solid #fff;
    }

    .plate-preview.selectable .plate-well-empty {
        cursor: pointer;
    }
</style>