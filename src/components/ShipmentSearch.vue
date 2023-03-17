<template>
    <b-overlay variant="light"
               blur="50px"
               spinner-variant="dark"
               spinner-small
               opacity="0.95"
               :show="isOverlayed"
               rounded="sm">
        <div class="row">
            <div class="col">
                <b-pagination
                    v-model="currentPage"
                    :total-rows="totalRows"
                    :per-page="perPage"
                    aria-controls="shipment-search"
                ></b-pagination>
            </div>
            <div class="col-auto">
                <b-form-input
                    id="filter-input"
                    v-model="filter"
                    type="search"
                    placeholder="Type to Search"
                />
            </div>
        </div>
        <b-table
            id="shipment-search"
            :items="sortedShipments"
            :fields="shipmentFields"
            :filter="filter"
            :per-page="perPage"
            :current-page="currentPage"
            :select-mode="selectMode"
            selectable
            @row-selected="shipmentSelected"
            @filtered="onFiltered"
            responsive
        ></b-table>

        <template v-if="debugMsg != null">
            <pre class="well">{{ debugOutput }}</pre>
        </template>

        <a :href="config.new_shipment_url" class="btn btn-success text-light"><i class="fas fa-plus"></i>&nbsp;New Shipment</a>
    </b-overlay>
</template>

<script>
    // QS
    import qs from 'qs';
    // Loader
    import loader from '../loader.vue'

    export default {
        components: {
            qs,
            loader,
        },
        data() {
            return {
                config: {},
                debugMsg: null,
                isOverlayed: false,
                shipments: [],
                perPage: 25,
                totalRows: 0,
                currentPage: 1,
                filter: null,
                selectMode: 'single'
            }
        },
        watch: {
            shipments: function(val) {
                // update helper fields
                this.totalRows = this.shipments.length ?? 0;
            }
        },
        computed: {
            sortedShipments: function() {
                if (this.shipments.sort) {
                    return this.shipments.sort((a, b) => {
                        if (a.record_id < b.record_id) { return -1; }
                        if (a.record_id > b.record_id) { return 1; }
                        return 0;
                    });
                }
                return this.shipments;
            },
            shipmentFields: function() {
                if (this.config && this.config.shipment_fields) {
                    return this.config.shipment_fields;
                }
                return [];
            },
            debugOutput: function() {
                return JSON.stringify(this.debugMsg, null, '\t');
            }
        },
        methods: {
            async update() {
                this.isOverlayed = true;
                const data = {
                    redcap_csrf_token: OrcaSpecimenTracking().redcap_csrf_token,
                    action: 'search-shipments'
                };
                this.axios({
                    url: OrcaSpecimenTracking().url,
                    method: 'post',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    data: qs.stringify(data)
                })
                .then(response => {
                    if (response.data) {
                        this.config = response.data.config;
                        this.shipments = response.data.shipments ?? [];
                        // debug
                        // this.debugMsg = response.data;
                    }
                })
                .catch(e => {
                    let errorMsg = 'An unknown error occurred';
                    if (e.response.data) {
                        errorMsg = e.response.data;
                    }
                    this.toast(
                        errorMsg,
                        'Failed to load shipments!',
                        'danger'
                    );
                })
                .finally(() => {
                    setTimeout(() => {
                        this.isOverlayed = false;
                    }, 250);
                });
            },
            shipmentSelected(items) {
                if (items.length === 1) {
                    this.goToShipment(items[0]);
                }
            },
            goToShipment(s) {
                if (s && s.shipment_dashboard_url) {
                    window.location.href = s.shipment_dashboard_url;
                }
            },
            onFiltered(filteredItems) {
                // Trigger pagination to update the number of buttons/pages due to filtering
                this.totalRows = filteredItems.length;
                this.currentPage = 1;
            }
        },
        mounted() {
            this.$nextTick(function () {
                this.update();
            });
        }
    }
</script>

<style scoped>
    table tbody tr:hover {
        cursor: pointer;
    }
</style>