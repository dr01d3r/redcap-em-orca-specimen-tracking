// import 'primevue/resources/themes/bootstrap4-light-blue/theme.css'
import "./style.scss";

import { createApp } from "vue";
import App from "./App.vue";

import PrimeVue from "primevue/config";
import Lara from '@primevue/themes/lara';
import Dialog from 'primevue/dialog';
import ConfirmDialog from 'primevue/confirmdialog';
import ConfirmationService from 'primevue/confirmationservice';
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
app.use(ConfirmationService);
app.use(ToastService);

// make v-focus usable in all components
app.directive('focus', {
    mounted: (el) => el.focus()
})

app.component("Dialog", Dialog);
app.component("ConfirmDialog", ConfirmDialog);
app.component("Toast", Toast);
app.component("BlockUI", BlockUI);
app.component("ProgressSpinner", ProgressSpinner);

app.mount("#ORCA_SPECIMEN_TRACKING");