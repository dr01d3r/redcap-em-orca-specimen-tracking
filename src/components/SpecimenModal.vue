<template>
    <b-modal
        size="xl"
        title="Edit Specimen"
        id="specimen-edit-modal"
        ref="specimenEditModal"
    >
        <template #default>
            <specimen-form
                    ref="specimenEditForm"
                    mode="edit"
                    reset-disable="true"
                    batch-disable="true"
                    :config="config"
                    :box_record_id="box_record_id"
                    :box_info="box_info"
                    @specimenSaved="specimenSaved"
            ></specimen-form>
        </template>
        <template #modal-footer>&nbsp;</template>
    </b-modal>
</template>

<script>
    import SpecimenForm from './SpecimenForm'

    export default {
        components: {
            SpecimenForm
        },
        props: {
            box_record_id: {
                type: String
            },
            box_info: {
                type: Object
            },
            config: {
                type: Object
            }
        },
        methods: {
            editSpecimen: function(record_id) {
                if (record_id) {
                    // show modal
                    this.$bvModal.show('specimen-edit-modal');
                    // load specimen
                    setTimeout(() => {
                        this.$refs.specimenEditForm.loadSpecimen(record_id);
                    }, 250);
                }
            },
            specimenSaved: function(specimen) {
                if (specimen) {
                    this.$emit('specimenSaved', specimen);
                    // hide modal
                    this.$bvModal.hide('specimen-edit-modal');
                }
            }
        }
    }
</script>

<style scoped>

</style>