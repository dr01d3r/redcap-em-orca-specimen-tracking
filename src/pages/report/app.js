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

const app = createApp(App);

app.use(PrimeVue, {
    theme: {
        preset: Lara
    }
});
app.use(ToastService);

app.component("Toast", Toast);
app.component("BlockUI", BlockUI);
app.component("ProgressSpinner", ProgressSpinner);

app.mount("#ORCA_SPECIMEN_TRACKING");