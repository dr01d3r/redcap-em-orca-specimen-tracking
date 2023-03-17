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
            <i class="fas fa-truck text-dark"></i>&nbsp;{{ shipmentTitle }}
            <template v-if="config.shipment_record_home_url != null">
                <span>&nbsp;|&nbsp;</span>
                <a :href="config.shipment_record_home_url" class="text-primary ml-1"><i class="fas fa-share"></i>&nbsp;Record Home</a>
            </template>
            <template v-if="canCreateNewShipment">
                <span>&nbsp;|&nbsp;</span>
                <a :href="config.new_shipment_url" class="text-success font-weight-normal"><i class="fas fa-plus"></i>&nbsp;New Shipment</a>
            </template>
            <template v-if="canSearchShipments">
                <span>&nbsp;|&nbsp;</span>
                <a href="#" @click.prevent="searchShipments()" class="text-primary font-weight-normal"><i class="fas fa-search"></i>&nbsp;Search Shipments</a>
            </template>
        </div>

        <div v-if="isShipmentComplete" class="alert alert-success">This Shipment is marked Complete.</div>

        <template v-if="!isObjectEmpty(errors)">
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
                            <b-form-input
                                    ref="box_name_input"
                                    autocomplete="off"
                                    @keyup.enter="boxScanned"
                                    @blur="boxScanned"
                                    v-model="box_name"
                                    :state="v$.box_name.$error ? false : null"
                            ></b-form-input>
                            <b-alert variant="danger" class="mt-1 mb-0 px-3 py-2"
                                     v-if="v$.box_name.$error"
                                     show
                            >
                                <strong>Validation Error:</strong>
                                <ul class="mb-0">
                                    <li v-for="error of v$.box_name.$errors" :key="error.$uid">{{ error.$message }}</li>
                                </ul>
                            </b-alert>
                        </div>
                        <div class="col-4">
                            <h1 class="lead mb-0">Shipment Details</h1>
                            <dl class="row">
                                <template v-for="(v, k) in config.shipment_fields">
                                    <div class="col-12"><hr class="my-1" /></div>
                                    <dt class="col-lg-5 text-truncate" :title="v">{{ v }}</dt>
                                    <dd class="col-lg-7 mb-0">{{ shipment_details[k] }}</dd>
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
                        <a :href="this.config.manifest_export_url" class="text-primary font-weight-normal"><i class="fas fa-file-export"></i>&nbsp;Export Manifest</a>
                    </template>
                </h1>
                <table class="table">
                    <thead>
                    <tr>
                        <th scope="col">Record ID</th>
                        <th scope="col">Box Name</th>
                        <th scope="col">Box Type</th>
                        <th scope="col">Sample Type</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(bv, bk) in boxes">
                        <td>{{ bv['record_id'] }}</td>
                        <td>{{ bv['box_name'] }}</td>
                        <td>{{ bv['box_type'] }}</td>
                        <td>{{ bv['sample_type'] }}</td>
                        <td>{{ bv['box_status'] }}</td>
                        <td>
                            <a v-if="canModifyShipment" href="javascript:void(0)" @click.prevent="removeBox(bv)" class="btn btn-xs btn-danger text-light" title="Remove Box"><i class="fas fa-times"></i>&nbsp;Remove</a>
                            <a href="javascript:void(0)" @click.prevent="boxDashboard(bv)" class="btn btn-xs btn-primary text-light" title="Go to Box Dashboard"><i class="fas fa-vials"></i>&nbsp;Dashboard</a>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <button v-if="showButtonCompleteShipment" type="button" class="btn btn-success" @click.prevent="tryCompleteShipment">Complete Shipment</button>
            </div>
        </div>

        <template v-if="debugMsg != null">
            <pre class="well">{{ debugOutput }}</pre>
        </template>

        <shipment-modal ref="shipmentModal"></shipment-modal>
    </b-overlay>
</template>

<script>
    // QS
    import qs from 'qs';
    // Vuelidate
    import useVuelidate from '@vuelidate/core'
    import {
        helpers
    } from '@vuelidate/validators'
    // Loader
    import loader from '../loader.vue'
    // local components
    import ShipmentSearch from './ShipmentSearch'
    import ShipmentModal from './ShipmentModal'

    export default {
        setup: () => {
            return {
                v$: useVuelidate({
                    $lazy: true,
                    $autoDirty: true,
                    $stopPropagation: true
                })
            }
        },
        components: {
            loader,
            ShipmentSearch,
            ShipmentModal
        },
        validations() {
            return {
                box_name: {
                    regexMatch: helpers.withMessage('Value provided does not match the required nomenclature!',
                        (value) => this.isEmpty(value) || value.match(this.config.box_name_regex)
                    )
                }
            }
        },
        data() {
            return {
                config: {},
                errors: {},
                debugMsg: null,
                forceReadOnly: false,
                isOverlayed: false,
                shipment: null,
                shipment_details: null,
                box_name: null,
                boxes: [],
                // server-side validation support
                vuelidateExternalResults: {
                    box_name: []
                }
            }
        },
        watch: {},
        computed: {
            shipmentRecordId: function() {
                if (this.shipment) {
                    return this.shipment.record_id;
                }
                else {
                    return null;
                }
            },
            shipmentTitle: function() {
                if (this.shipment) {
                    return this.shipment.shipment_name;
                }
                else {
                    return 'Shipment Dashboard'
                }
            },
            boxCount: function() {
                if (this.shipment && this.boxes.length) {
                    return this.boxes.length;
                }
            },
            canCreateNewShipment: function() {
                // true unless config is in a bad state
                return !this.isReadOnly && this.config && this.config.new_shipment_url;
            },
            showButtonCompleteShipment: function() {
                return !this.isReadOnly && this.shipment &&!this.isShipmentComplete;
            },
            canSearchShipments: function() {
                // true unless config is in a bad state
                return !this.isReadOnly;
            },
            canExportManifest: function() {
                return !this.isReadOnly && this.config && this.config.manifest_export_url;
            },
            canModifyShipment: function() {
                return !this.isReadOnly && this.shipment && this.shipment.shipment_status !== "complete";
            },
            isShipmentComplete: function() {
                return this.shipment && this.shipment.shipment_status === "complete";
            },
            isReadOnly: function() {
                return this.forceReadOnly || !this.isObjectEmpty(this.errors);
            },
            debugOutput: function() {
                return JSON.stringify(this.debugMsg, null, '\t');
            },
        },
        methods: {
            async initializeDashboard() {
                this.isOverlayed = true;
                this.axios({
                    url: OrcaSpecimenTracking().url,
                    params: {
                        action: 'initialize-shipment-dashboard',
                        id: this.qs_get('id')
                    }
                })
                .then(response => {
                    this.box_name = null;
                    this.config = response.data.config;
                    this.shipment = response.data.shipment ?? null;
                    this.shipment_details = response.data.shipment_details ?? null;
                    this.boxes = response.data.boxes ?? [];
                    // debug
                    // this.debugMsg = this.shipment;
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
                    this.setUrlState();
                    this.resetFocus();
                    setTimeout(() => {
                        this.isOverlayed = false;
                    }, 250);
                });
            },
            boxScanned: function() {
                if (this.v$.box_name.$dirty && !this.v$.box_name.$error && !this.isEmpty(this.box_name)) {
                    this.searchBoxName(this.box_name);
                }
            },
            async searchBoxName(search_value) {
                this.isOverlayed = true;
                const data = {
                    redcap_csrf_token: OrcaSpecimenTracking().redcap_csrf_token,
                    action: 'search-plate',
                    search_value: search_value,
                    include_specimens: false
                };
                this.axios({
                    url: OrcaSpecimenTracking().url,
                    method: 'post',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    data: qs.stringify(data)
                })
                .then(response => {
                    if (response.data) {
                        this.searchBoxNameCallback(response.data);
                    }
                })
                .catch(e => {
                    let errorMsg = 'An unknown error occurred';
                    if (e.response && e.response.data) {
                        errorMsg = e.response.data;
                    }
                    this.toast(
                        errorMsg,
                        'Box Search Failed',
                        'danger'
                    );
                })
                .finally(() => {
                    setTimeout(() => {
                        this.isOverlayed = false;
                    }, 250);
                });
            },
            searchBoxNameCallback: function(data) {
                let v = null;
                // if a box was found
                if (data.plate) {
                    // verify it isn't already tied to a shipment
                    if (this.isEmpty(data.plate.shipment_record_id)) {
                        if (data.plate.box_status === 'closed') {
                            // box is in a closed status
                            v = {
                                box_name: [ `Box is Closed - cannot add a closed box to the shipment!` ]
                            };
                        } else {
                            // sample_type must match
                            if (this.shipment.sample_type === data.plate.sample_type) {
                                this.addBox(data.plate);
                            } else {
                                // sample_type mismatch
                                v = {
                                    box_name: [ `Sample Type Mismatch - cannot add '${data.plate.sample_type}' box to '${this.shipment.sample_type}' shipment!` ]
                                };
                            }
                        }
                    } else {
                        if (data.plate.shipment_record_id === this.shipment.record_id) {
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
                    Object.assign(this.vuelidateExternalResults, v);
                    this.$nextTick(() => {
                        this.v$.box_name.$validate();
                    });
                }
                // debug
                // this.debugMsg = data;
                this.v$.box_name.$reset();
            },
            searchShipments: function() {
                this.$bvModal.show('shipment-search-modal');
            },
            tryCompleteShipment: function() {
                const h = this.$createElement;
                const ack = h('div', { }, [
                    h('div', 'You are about to complete the following shipment:'),
                    h('div', [
                        h('div', { class: [ 'p-2 my-2 border-left border-start border-success font-weight-normal' ] }, this.shipmentTitle)
                    ]),
                    h('div', `Please click 'Confirm' to complete the process.`)
                ]);
                this.$bvModal.msgBoxConfirm([ack], {
                    title: 'Completing the Shipment',
                    headerClass: 'bg-success',
                    headerTextVariant: 'light',
                    bodyClass: 'lead',
                    footerClass: 'alert-success',
                    okVariant: 'success',
                    okTitle: 'Confirm',
                    cancelTitle: 'Cancel',
                    hideHeaderClose: false,
                    centered: true
                })
                .then(value => {
                    if (value === true) {
                        this.completeShipment(this.shipment.record_id);
                    } else {
                        this.resetFocus();
                    }
                })
                .catch(error => {
                    this.toast(
                        error,
                        'Completing Shipment Failed',
                        'danger'
                    );
                });
            },
            addBox: function(box) {
                const h = this.$createElement;
                const msgVNode = h('div', {
                    domProps: {
                        innerHTML: `Adding box [<strong>${box.box_name}</strong>] to this shipment.  Please confirm.`
                    }
                });
                this.$bvModal.msgBoxConfirm([msgVNode], {
                    title: 'Adding Box',
                    headerClass: 'bg-primary',
                    headerTextVariant: 'light',
                    bodyClass: 'lead',
                    footerClass: 'alert-primary',
                    okVariant: 'primary',
                    okTitle: 'Confirm',
                    cancelTitle: 'Cancel',
                    hideHeaderClose: false,
                    centered: true
                })
                .then(value => {
                    if (value === true) {
                        this.updateBoxShipment(box.record_id, this.shipment.record_id);
                    } else {
                        this.box_name = null;
                        this.resetFocus();
                    }
                })
                .catch(error => {
                    this.toast(
                        error,
                        'Adding Box Failed',
                        'danger'
                    );
                });
            },
            removeBox: function(box) {
                const h = this.$createElement;
                const msgVNode = h('div', {
                    domProps: {
                        innerHTML: `You are about to remove box [<strong>${box.box_name}</strong>] from this shipment.  Please confirm.`
                    }
                });
                this.$bvModal.msgBoxConfirm([msgVNode], {
                    title: 'Removing Box',
                    headerClass: 'bg-danger',
                    headerTextVariant: 'light',
                    bodyClass: 'lead',
                    footerClass: 'alert-danger',
                    okVariant: 'danger',
                    okTitle: 'Confirm',
                    cancelTitle: 'Cancel',
                    hideHeaderClose: false,
                    centered: true
                })
                .then(value => {
                    if (value === true) {
                        this.updateBoxShipment(box.record_id, null);
                    }
                })
                .catch(error => {
                    this.toast(
                        error,
                        'Box Search Failed',
                        'danger'
                    );
                });
            },
            boxDashboard: function(box) {
                if (box && box.record_id) {
                    window.location.href = `${this.config.box_dashboard_base_url}&id=${box.record_id}`;
                }
            },
            async completeShipment(shipment_record_id) {
                // complete-shipment
                this.isOverlayed = true;
                const data = {
                    redcap_csrf_token: OrcaSpecimenTracking().redcap_csrf_token,
                    action: 'complete-shipment',
                    shipment_record_id: shipment_record_id
                };
                this.axios({
                    url: OrcaSpecimenTracking().url,
                    method: 'post',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    data: qs.stringify(data)
                })
                .then(response => {
                    this.toast(
                        'Shipment Closed Successfully',
                        'Save Successful',
                        'success'
                    );
                    // rebuild the dashboard
                    this.initializeDashboard();
                })
                .catch(e => {
                    let errorMsg = 'An unknown error occurred';
                    if (e.response && e.response.data) {
                        errorMsg = e.response.data;
                    }
                    this.toast(
                        errorMsg,
                        'Error while completing Shipment',
                        'danger'
                    );
                })
                .finally(() => {
                    setTimeout(() => {
                        this.isOverlayed = false;
                        this.resetFocus();
                    }, 250);
                });
            },
            async updateBoxShipment(box_record_id, shipment_record_id) {
                // update-box-shipment
                this.isOverlayed = true;
                const data = {
                    redcap_csrf_token: OrcaSpecimenTracking().redcap_csrf_token,
                    action: 'update-box-shipment',
                    box_record_id: box_record_id,
                    shipment_record_id: shipment_record_id
                };
                this.axios({
                    url: OrcaSpecimenTracking().url,
                    method: 'post',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    data: qs.stringify(data)
                })
                .then(response => {
                    // rebuild the dashboard
                    this.initializeDashboard();
                })
                .catch(e => {
                    let errorMsg = 'An unknown error occurred';
                    if (e.response && e.response.data) {
                        errorMsg = e.response.data;
                    }
                    this.toast(
                        errorMsg,
                        'Box Shipment Update Failed',
                        'danger'
                    );
                })
                .finally(() => {
                    setTimeout(() => {
                        this.isOverlayed = false;
                        this.resetFocus();
                    }, 250);
                });
            },
            // interactivity support
            resetFocus: function() {
                this.focusElement('box_name_input');
            },
            focusElement: function(refName) {
                if (this.$refs[refName]) {
                    this.$nextTick(() => {
                        this.$refs[refName].focus();
                    });
                }
            },
            setUrlState: function() {
                if (this.shipment == null) {
                    // 'id=' will not get removed, so force an arbitrary value so it'll be completely removed
                    this.qs_push("id", false, true);
                    this.qs_remove("id", true);
                } else {
                    this.qs_push("id", this.shipmentRecordId, true);
                }
            },
        },
        mounted() {
            this.$nextTick(function () {
                this.initializeDashboard();
            });
        }
    }
</script>

<style></style>