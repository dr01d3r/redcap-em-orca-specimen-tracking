// import 'primevue/resources/themes/bootstrap4-light-blue/theme.css'
import { createApp } from "vue";
import App from "./App.vue";

import PrimeVue from "primevue/config";
import { definePreset } from "@primevue/themes";
import Lara from '@primevue/themes/lara';

import ProgressSpinner from 'primevue/progressspinner';
import Dialog from 'primevue/dialog';
import Popover from 'primevue/popover';
import Tooltip from 'primevue/tooltip';
import Toast from "primevue/toast";
import ToastService from 'primevue/toastservice';

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

app.directive('tooltip', Tooltip);

app.component("ProgressSpinner", ProgressSpinner);
app.component("Dialog", Dialog);
app.component("Popover", Popover);
app.component("Toast", Toast);

app.mount("#ORCA_SPECIMEN_TRACKING");