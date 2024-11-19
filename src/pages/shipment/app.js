// import 'primevue/resources/themes/bootstrap4-light-blue/theme.css'
import "./style.scss";

import { createApp } from "vue";
import App from "./App.vue";

import PrimeVue from "primevue/config";
import Lara from '@primevue/themes/lara';
import Toast from 'primevue/toast';
import ToastService from 'primevue/toastservice';
import ProgressSpinner from 'primevue/progressspinner';
import BlockUI from 'primevue/blockui';
import ConfirmationService from 'primevue/confirmationservice';
import ConfirmDialog from 'primevue/confirmdialog';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';


const app = createApp(App);

app.use(PrimeVue, {
    theme: {
        preset: Lara
    }
});
app.use(ConfirmationService);
app.use(ToastService);

app.component("Toast", Toast);
app.component("BlockUI", BlockUI);
app.component('ConfirmDialog', ConfirmDialog);
app.component("ProgressSpinner", ProgressSpinner);
app.component("DataTable", DataTable);
app.component("Dialog", Dialog);
app.component("Column", Column);
app.component("IconField", IconField);
app.component("InputIcon", InputIcon);
app.component("InputText", InputText);

app.mount("#ORCA_SPECIMEN_TRACKING");