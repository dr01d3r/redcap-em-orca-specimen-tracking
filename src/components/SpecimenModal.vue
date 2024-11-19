<script setup>
import { ref, nextTick } from 'vue';
import SpecimenForm from './SpecimenForm.vue'

const dialogVisible = ref(false);
const specimenForm = ref();

const editSpecimen = (record_id) => {
    if (record_id) {
        // show modal
        dialogVisible.value = true;
        // load specimen
        nextTick(() => {
            specimenForm.value.loadSpecimen(record_id);
        });
    }
};

const specimenSaved = (specimen) => {
    if (specimen) {
        emit('specimenSaved', specimen);
        // hide modal
        dialogVisible.value = false;
    }
};

// PROPS | EXPOSE | EMITS
const props = defineProps({
    box_record_id: {
        type: String
    },
    box_info: {
        type: Object
    },
    config: {
        type: Object
    }
});
defineExpose({
    editSpecimen
});
const emit = defineEmits(['closed']);
</script>

<template>
    <Dialog v-model:visible="dialogVisible" modal header="Edit Specimen" :style="{ width: '50vw' }" :breakpoints="{ '1199px': '75vw', '575px': '90vw' }" class="bg-secondary-subtle">
        <specimen-form
            ref="specimenForm"
            mode="edit"
            reset-disable="true"
            batch-disable="true"
            :config="config"
            :box_record_id="box_record_id"
            :box_info="box_info"
            @specimenSaved="specimenSaved"
        ></specimen-form>
    </Dialog>
</template>

<style scoped></style>