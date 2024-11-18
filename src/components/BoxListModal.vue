<script setup>
import { ref } from 'vue';
import Dialog from 'primevue/dialog';
import { isEmpty, isNotEmpty } from '@primeuix/utils/object';
import BoxList from "./BoxList.vue";

defineExpose({
    show
});
const emit = defineEmits([ 'selected', 'closed']);

const dialogVisible = ref(false);

const header = ref("Search");
const boxes = ref([]);

function show(s, b) {
    // set local vars
    header.value = `Search Value: '${s}'`;
    boxes.value = b;
    // finally show the dialog, if not already shown
    dialogVisible.value = true;
}

const onPlateSelected = (event) => {
    // emit the event so the parent can display the plate
    emit('selected', event);
    // close the dialog
    dialogVisible.value = false;
}

const onHidden = () => {
    // emit close so parent dashboard can update
    emit('closed');
}
</script>

<template>
    <Dialog v-model:visible="dialogVisible" modal :header="header" :style="{ width: '50vw' }" @after-hide="onHidden">
        <template v-if="!isEmpty(boxes)">
            <box-list :boxes="boxes" @selected="onPlateSelected"></box-list>
        </template>
    </Dialog>
</template>

<style></style>