// import 'primevue/resources/themes/bootstrap4-light-blue/theme.css'
import "./style.scss";

import { createApp } from "vue";
import App from "./App.vue";

import PrimeVue from "primevue/config";
import { definePreset } from "@primevue/themes";
import Lara from '@primevue/themes/lara';

import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import ToastService from 'primevue/toastservice';
import ProgressSpinner from 'primevue/progressspinner';

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
app.use(ToastService);

app.component("Dialog", Dialog);
app.component("Toast", Toast);
app.component("ProgressSpinner", ProgressSpinner);

app.mount("#ORCA_SPECIMEN_TRACKING");