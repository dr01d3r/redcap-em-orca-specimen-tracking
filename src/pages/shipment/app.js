// import 'primevue/resources/themes/bootstrap4-light-blue/theme.css'
import "./style.scss";

import { createApp } from "vue";
import App from "./App.vue";

import PrimeVue from "primevue/config";
import Lara from '@primevue/themes/lara';
import Toast from 'primevue/toast';
import ToastService from 'primevue/toastservice';
import ProgressSpinner from 'primevue/progressspinner';
import ConfirmationService from 'primevue/confirmationservice';
import ConfirmDialog from 'primevue/confirmdialog';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import {definePreset} from "@primevue/themes";


const app = createApp(App);

app.use(PrimeVue, {
    theme: {
        // Use definePreset to extend Lara with the missing small padding tokens
        preset: definePreset(Lara, {
            extend: {
                datatable: {
                    header: {
                        cell: {
                            sm: { padding: '0.375rem 0.5rem' }
                        }
                    },
                    body: {
                        cell: {
                            sm: { padding: '0.375rem 0.5rem' }
                        }
                    },
                    footer: {
                        cell: {
                            sm: { padding: '0.375rem 0.5rem' }
                        }
                    }
                }
            }
        }),
        options: {
            darkModeSelector: false || 'none',
        }
    }
});
app.use(ConfirmationService);
app.use(ToastService);

app.component("Toast", Toast);
app.component('ConfirmDialog', ConfirmDialog);
app.component("ProgressSpinner", ProgressSpinner);
app.component("DataTable", DataTable);
app.component("Dialog", Dialog);
app.component("Column", Column);
app.component("IconField", IconField);
app.component("InputIcon", InputIcon);
app.component("InputText", InputText);

app.mount("#ORCA_SPECIMEN_TRACKING");