<script setup>
import {ref, computed, onMounted, watch, nextTick} from 'vue';
import {useToast} from "primevue/usetoast";
import { FilterMatchMode } from '@primevue/core/api';

const toast = useToast();
const showToast = (type, summary, detail) => {
    toast.add({
        severity: type,
        summary: summary,
        detail: detail,
        life: 3000
    });
};

const isLoading = ref(false);
const debug = ref();

const config = ref({});
const shipments = ref([]);
const perPage = ref(20);
const totalRows = ref(0);
const currentPage = ref(1);
const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});

watch(shipments, async (newVal, oldVal) => {
    // update helper fields
    totalRows.value = shipments.value.length ?? 0;
});

const sortedShipments = computed(() => {
    if (shipments.value.sort) {
        return shipments.value.sort((a, b) => {
            if (a.record_id < b.record_id) { return -1; }
            if (a.record_id > b.record_id) { return 1; }
            return 0;
        });
    }
    return shipments.value;
});

const shipmentFields = computed(() => {
    if (config.value && config.value.fields) {
        return config.value.fields['shipment'];
    }
    return {};
});

const update = async () => {
    isLoading.value = true;
    OrcaSpecimenTracking().jsmo.ajax('search-shipments', {})
    .then(response => {
        if (response) {
            config.value = response.config;
            shipments.value = response.shipments ?? [];
            // debug
            // debug.value = response;
        }
    })
    .catch(e => {
        let errorMsg = 'An unknown error occurred';
        if (e.message) {
            errorMsg = e.message;
        }
        showToast("error", "Failed to load shipments!", errorMsg);
    })
    .finally(() => {
        setTimeout(() => {
            isLoading.value = false;
        }, 250);
    });
};
const goToShipment = (s) => {
    if (s && s.shipment_dashboard_url) {
        window.location.href = s.shipment_dashboard_url;
    }
};
onMounted(() => {
    nextTick(function () {
        update();
    });
});
</script>

<template>
    <BlockUI :blocked="isLoading">
        <DataTable :value="sortedShipments" size="small" tableClass="table table-striped table-hover"
                   :globalFilterFields="Object.keys(shipmentFields)" v-model:filters="filters"
                   paginator :rows="perPage" :rowsPerPageOptions="[5, 10, 20, 50]" scrollable
                   paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
        >
            <template #header>
                <div class="d-flex">
                    <div class="input-group w-auto ms-auto">
                        <div class="input-group-text"><i class="fas fa-search"></i></div>
                        <input type="text" class="form-control" v-model="filters['global'].value" placeholder="Keyword Search">
                    </div>
                </div>
            </template>
            <Column :key="col" :field="col" :header="label">
                <template #body="slotProps">
                    <button class="btn btn-xs btn-primary" @click="goToShipment(slotProps.data)"><i class="fas fa-edit"></i>&nbsp;Open</button>
                </template>
            </Column>
            <template v-for="(cv, col) of shipmentFields">
                <Column v-if="config['save-state']['shipment'][col]['shipment-list']" :key="col" :field="col" :header="cv['field_label']"></Column>
            </template>
        </DataTable>

        <pre v-if="debug" class="mt-3">{{ debug }}</pre>

        <a :href="config.new_shipment_url" class="btn btn-success text-light mt-3"><i class="fas fa-plus"></i>&nbsp;New Shipment</a>
    </BlockUI>
    <ProgressSpinner v-show="isLoading" class="overlay"/>
</template>

<style scoped>
    table tbody tr:hover {
        cursor: pointer;
    }
</style>