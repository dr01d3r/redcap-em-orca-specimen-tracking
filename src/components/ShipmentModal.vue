<script setup>
import { ref } from 'vue';
import ShipmentSearch from './ShipmentSearch.vue'

defineExpose({
    show
});

const dialogVisible = ref(false);

function show() {
    // finally show the dialog, if not already shown
    dialogVisible.value = true;
}
</script>

<template>
    <Dialog v-model:visible="dialogVisible" modal header="Find Shipment" class="mw-100">
        <shipment-search />
        <template #footer></template>
    </Dialog>
</template>

<style scoped></style>