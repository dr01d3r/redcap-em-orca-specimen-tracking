<script setup>
import {ref, computed, onMounted, nextTick} from 'vue';
import { isEmpty, isNotEmpty } from '@primeuix/utils/object';
import {useToast} from "primevue/usetoast";

import DataTable from 'datatables.net-vue3';
import DataTablesLib from 'datatables.net';
import DataTablesCore from 'datatables.net-bs5';
import jszip from 'jszip';
import 'datatables.net-buttons-dt';
import 'datatables.net-buttons/js/buttons.html5.mjs';

DataTable.use(DataTablesLib);
DataTable.use(DataTablesCore);
DataTablesLib.Buttons.jszip(jszip);

const toast = useToast();
const showToast = (type, summary, detail) => {
    toast.add({
        severity: type,
        summary: summary,
        detail: detail,
        life: 3000
    });
};

const dt = ref();

const isLoading = ref(false);
const initialized = ref(false);
const debug = ref();

const config = ref();
const specimens = ref();
const fields = ref();
const errors = ref({});

const exportMessage = computed(() => {
    if (config.value) {
        return `${config.value['study_name']} | ${config.value['datetime']}`;
    }
    return 'Orca Specimen Tracking Report Export';
});

const exportFileName = computed(() => {
    if (config.value) {
        // study names need to be sanitized of special characters
        return `${config.value['study_name'].replaceAll(/\W+/gi, '_').toLowerCase()}-${config.value['timestamp']}`;
    }
    return 'orca_specimen_tracking_export';
});

const dtColumns = computed(() => {
    let c = [];
    if (isNotEmpty(fields.value)) {
        for (const sh in fields.value['shipment']) {
            c.push({ data: sh, title: fields.value['shipment'][sh] });
        }
        for (const bx in fields.value['box']) {
            c.push({ data: bx, title: fields.value['box'][bx] });
        }
        for (const sp in fields.value['specimen']) {
            c.push({ data: sp, title: fields.value['specimen'][sp] });
        }
    }
    return c;
});

const options = ref({
    pageLength: 25,
    layout: {
        topStart: {
            buttons: [
                {
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-download"></i>&nbsp;CSV Export',
                    className: 'btn btn-outline-secondary btn-sm',
                    filename: 'orca_specimen_tracking_export'
                },
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-download"></i>&nbsp;Excel Export',
                    title: 'Orca Specimen Tracking Report Export',
                    className: 'btn btn-outline-secondary btn-sm',
                    filename: 'orca_specimen_tracking_export'
                }
            ]
        }
    },
    columns: []
});

const canShowDataTable = computed(() => {
    return initialized.value && isNotEmpty(fields.value) && isNotEmpty(specimens.value);
});

const initializeDataTable = () => {
    isLoading.value = true;
    initialized.value = false;
    OrcaSpecimenTracking().jsmo.ajax('get-report-data', {})
        .then(response => {
            if (isNotEmpty(response.errors)) {
                showToast('error', 'Failed to Fetch Data', response.errors);
            } else if (isNotEmpty(response)) {
                config.value = response.config;
                specimens.value = response.data;
                fields.value = response.fields;
                options.value.columns = dtColumns.value;
                options.value.layout.topStart.buttons[0].filename = exportFileName.value;
                options.value.layout.topStart.buttons[1].filename = exportFileName.value;
                options.value.layout.topStart.buttons[1].title = exportMessage.value;
                // debug
                // debug.value = response;

                initialized.value = true;
            }
        })
        .catch(e => {
            let errorMsg = 'An unknown error occurred';
            if (e.message) {
                errorMsg = e.message;
            }
            showToast('error', 'Failed to Fetch Data', errorMsg);
        })
        .finally(() => {
            setTimeout(() => {
                isLoading.value = false;
            }, 250);
        });
};

onMounted(() => {
    nextTick(function () {
        initializeDataTable();
    });
});
</script>

<template>
    <div class="projhdr">
        <i class="fas fa-vials text-dark"></i>&nbsp;Reporting Dashboard
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

    <BlockUI :blocked="isLoading">
        <!-- MAIN CONTENT AREA -->
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col">
                        <span class="lead">This dashboard is a full output of all specimens and their related data.</span>
                        <hr class="my-1">
                        <span class="text-muted">The primary purpose of this dashboard is to make the data exportable.</span>
                        <div class="border-dark-subtle border-start border-3 ps-2 mt-2 text-muted"><strong>NOTE:</strong>&nbsp;<span>The search filter is applied when exporting data (i.e. if the filter only shows 100 of 999 rows, only the 100 will be exported).</span></div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <template v-if="canShowDataTable">
                    <DataTable ref="dt" :options="options" :data="specimens" class="table table-hover table-striped mb-0 display w-100" />
                </template>
            </div>
        </div>
    </BlockUI>
    <ProgressSpinner v-show="isLoading" class="overlay"/>
    <pre v-if="debug" class="mt-3">{{ debug }}</pre>
</template>

<style>
@import 'datatables.net-bs5';
</style>