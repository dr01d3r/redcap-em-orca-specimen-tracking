// import 'primevue/resources/themes/bootstrap4-light-blue/theme.css'
import "./style.scss";

import { createApp } from "vue";
import App from "./App.vue";

import PrimeVue from "primevue/config";
import Lara from '@primevue/themes/lara';

import ProgressSpinner from 'primevue/progressspinner';
import BlockUI from 'primevue/blockui';
import Dialog from 'primevue/dialog';
import Popover from 'primevue/popover';
import Tooltip from 'primevue/tooltip';
import Toast from "primevue/toast";
import ToastService from 'primevue/toastservice';

const app = createApp(App);

app.use(PrimeVue, {
    theme: {
        preset: Lara
    }
});
app.use(ToastService);

app.directive('tooltip', Tooltip);

app.component("BlockUI", BlockUI);
app.component("ProgressSpinner", ProgressSpinner);
app.component("Dialog", Dialog);
app.component("Popover", Popover);
app.component("Toast", Toast);

app.mount("#ORCA_SPECIMEN_TRACKING");