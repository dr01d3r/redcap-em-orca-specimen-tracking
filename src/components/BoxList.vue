<script setup>
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import { isEmpty, isNotEmpty } from '@primeuix/utils/object';
const {
    boxes
} = defineProps({
    boxes: {
        type: Array,
        required: true
    }
});
const emit = defineEmits([ 'selected' ]);
const selected = (e) => {
    // emit the event so the parent can display the plate
    emit('selected', e);
}
</script>

<template>
    <template v-if="isEmpty(boxes)">
        <!-- some empty list or loading message here -->
    </template>
    <template v-else>
        <DataTable :value="boxes" size="small" tableClass="table table-striped table-hover fs-6"
                   selectionMode="single" @rowSelect="selected"
                   paginator :rows="10" :rowsPerPageOptions="[5, 10, 20, 50]"
                   paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
        >
            <Column field="record_id" header="Record ID">
                <template #body="slotProps">
                    <i>{{ slotProps.data[slotProps.field] }}</i>
                </template>
            </Column>
            <Column field="box_name" header="Box Name">
                <template #body="slotProps">
                    <i>{{ slotProps.data[slotProps.field] }}</i>
                </template>
            </Column>
            <Column field="box_status" header="Box Status">
                <template #body="slotProps">
                    <div class="badge" :class="`box-status-${slotProps.data[slotProps.field]}`">{{ slotProps.data[slotProps.field] }}</div>
                </template>
            </Column>
        </DataTable>
    </template>
</template>

<style scoped>
.box-status-closed {
    color: var(--bs-white);
    background-color: var(--bs-red);
}
.box-status-available {
    color: var(--bs-white);
    background-color: var(--bs-green);
}
</style>