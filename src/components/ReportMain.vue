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
            <i class="fas fa-vials text-dark"></i>&nbsp;Reporting Dashboard
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
                        <span class="lead">This dashboard is a full output of all specimens and their related data.</span>
                        <hr class="my-1">
                        <span class="text-muted">The primary purpose of this dashboard is to make the data exportable.</span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div>
                    <table id="report-table" class="table mb-0 display" style="width:100%">
                        <thead>
                        <tr>
                            <th scope="col">Box</th>
                            <th scope="col">Name</th>
                            <th scope="col">Date/Time Collected</th>
                            <th scope="col">Volume</th>
                            <th scope="col">MHN</th>
                            <th scope="col">Date/Time Processed</th>
                            <th scope="col">Tech Initials</th>
                            <th scope="col">Date/Time Frozen</th>
                            <th scope="col">CSID</th>
                            <th scope="col">CUID</th>
                            <th scope="col">Comment</th>
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
        <template v-if="debugMsg != null">
            <pre class="well">{{ debugOutput }}</pre>
        </template>
    </b-overlay>
</template>

<script>
    // Loader
    import loader from '../loader.vue'

    export default {
        components: {
            loader
        },
        data() {
            return {
                config: {},
                errors: {},
                debugMsg: null,
                isOverlayed: false,
                specimens: [],
                perPage: 50,
                currentPage: 1
            }
        },
        methods: {
            initializeDataTable() {
                // DataTable initialization
                this.dataTable = $("#report-table").DataTable({
                    // iDisplayLength: this.perPage,
                    ajax: OrcaSpecimenTracking().url + '&action=get-specimen-report-data',
                    deferRender: true,
                    columns: [
                        { data: 'box_name.value' },
                        { data: 'name.value' },
                        { data: {
                            _: 'date_time_collected.value',
                            sort: 'date_time_collected.__SORT__'
                            } },
                        { data: 'volume.value' },
                        { data: 'mhn.value' },
                        { data: {
                                _: 'date_time_processed.value',
                                sort: 'date_time_collected.__SORT__'
                            } },
                        { data: 'tech_initials.value' },
                        { data: {
                                _: 'date_time_frozen.value',
                                sort: 'date_time_collected.__SORT__'
                            } },
                        { data: 'csid.value' },
                        { data: 'cuid.value' },
                        { data: 'comment.value' },
                    ],
                    dom: 'Bfrtip',
                    buttons: [
                        'excelHtml5'
                    ],
                });
            }
        },
        computed: {
            debugOutput: function() {
                return JSON.stringify(this.debugMsg, null, '\t');
            }
        },
        mounted() {
            this.$nextTick(function () {
                this.initializeDataTable();
            });
        }
    }
</script>

<style scoped>

</style>