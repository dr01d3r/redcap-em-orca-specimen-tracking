<template>
    <b-modal
        size="xl"
        title="Find Shipment"
        id="shipment-search-modal"
        ref="shipmentSearchModal"
    >
        <template #default>
            <shipment-search />
        </template>
        <template #modal-footer>&nbsp;</template>
    </b-modal>
</template>

<script>
    import ShipmentSearch from './ShipmentSearch'

    export default {
        components: {
            ShipmentSearch
        }
    }
</script>

<style scoped>

</style>