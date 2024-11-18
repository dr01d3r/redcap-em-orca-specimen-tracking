<script setup>
import {
    ref,
    inject,
    useTemplateRef,
    computed,
    watch,
    watchEffect,
    nextTick,
    onMounted,
    Teleport,
    getCurrentInstance
} from 'vue';
import { isEmpty, isNotEmpty } from '@primeuix/utils/object';
import {useToast} from 'primevue/usetoast';
import {useConfirm} from "primevue/useconfirm";
import ModuleUtils from '../ModuleUtils';
import { DateTime, Interval } from 'luxon';

const uid = getCurrentInstance().uid;

const toast = useToast();
const showToast = (type, summary, detail, life = 3000) => {
    toast.add({
        severity: type,
        summary: summary,
        detail: detail,
        life: life
    });
};

const confirm = useConfirm();
const confirmMove = (data) => {
    confirm.require({
        group: `move-${uid}`,
        message: data,
        header: data.specimen.specimen_name,
        accept: () => {
            // move specimen
            saveSpecimen(data.specimen);
        },
        reject: () => {
            resetSpecimen();
        },
        onShow: () => {
            nextTick(() => moveCancelBtn.value.focus());
        }
    });
};
const confirmWarnings = (data) => {
    confirm.require({
        group: `warnings-${uid}`,
        message: data,
        header: 'Save Acknowledgement',
        accept: () => {
            // append warnings to target field, if configured
            if (isNotEmpty(warnings.value) && isNotEmpty(config['general']['warning_ack_field'])) {
                let warningStr = Object.values(warnings.value).map((x) =>
                    ModuleUtils.warningAcknowledgementMessagePreview(userid.value, x)
                ).join('\r\n');
                if (isEmpty(specimen.value[config['general']['warning_ack_field']])) {
                    specimen.value[config['general']['warning_ack_field']] = warningStr;
                } else {
                    specimen.value[config['general']['warning_ack_field']] += `\r\n${warningStr}`;
                }
            }
            // save the specimen!
            saveSpecimen(specimen.value);
        },
        reject: () => {

        },
        onShow: () => {
            nextTick(() => confirmWarningCancelBtn.value.focus());
        }
    });
};

// Vuelidate
import useVuelidate from '@vuelidate/core'
import {
    helpers,
    required
} from '@vuelidate/validators'

// DEFAULT DATA STATE
const defaultSpecimenState = () => {
    return {
        record_id: null,
        specimen_name: null,
        box_position: null,
        box_record_id: null
    }
}

// VUELIDATE
const noFutureDateTime = (value, vm, model) => {
    return isEmpty(value) || DateTime.fromFormat(value, ModuleUtils.luxonDateTimeFormatFrom) <= DateTime.now();
};
const sameAsConfirm = (field_name) => helpers.withParams(
    { type: 'sameAsConfirm', value: field_name },
    (value, vm, model) => {
        return value === vm[`confirm_${field_name}`];
    }
);

const afterDateMinMax = (field_name, extras) => helpers.withParams({}, (value, vm, model) => {
        if (isNotEmpty(value) && isNotEmpty(extras) && isNotEmpty(specimen.value[extras['target']])) {
            let d1 = DateTime.fromFormat(value, ModuleUtils.luxonDateTimeFormatFrom);
            let d2 = DateTime.fromFormat(specimen.value[extras['target']], ModuleUtils.luxonDateTimeFormatFrom);
            // calculate the diff with min/max, if set
            const diff = Interval.fromDateTimes(d2, d1).length("minutes");
            // minimum
            let minCheckPassed = (isEmpty(extras['minimum']) || diff >= parseInt(extras['minimum']));
            // maximum
            let maxCheckPassed = (isEmpty(extras['maximum']) || diff <= parseInt(extras['maximum']));
            // if the min/max should only be warnings, update the warnings object
            if (extras['warningOnly'] === true) {
                let warningName = `${field_name}.afterDateTime`;
                // update min/max warnings
                if (minCheckPassed && maxCheckPassed) {
                    delete warnings.value[warningName];
                } else {
                    warnings.value[warningName] = ModuleUtils.afterDateTimeErrorMessage(
                        fields.value['specimen'][field_name]['field_label'],
                        fields.value['specimen'][extras['target']]['field_label'],
                        extras);
                }
                return true;
            } else {
                return d1 >= d2 && minCheckPassed && maxCheckPassed;
            }
        }
        return true;
    }
);
const afterDateTime = (extras) => helpers.withParams({}, (value, vm, model) => {
        if (isNotEmpty(value) && isNotEmpty(extras) && isNotEmpty(specimen.value[extras['target']])) {
            let d1 = DateTime.fromFormat(value, ModuleUtils.luxonDateTimeFormatFrom);
            let d2 = DateTime.fromFormat(specimen.value[extras['target']], ModuleUtils.luxonDateTimeFormatFrom);
            return d1 >= d2;
        }
        return true;
    }
);

const specimenMatchesBox = (value, vm, model) => {
    let specimen_match = value.match(model.config['general']['specimen_name_regex']);
    let box_match = model.box_info['box_name'].match(model.config['general']['box_name_regex']);
    // ignore if it's not a match - either empty or base regex validation failed
    if (specimen_match === null || box_match === null) return true;
    let is_valid = true;
    for (const [key, value] of Object.entries(box_match.groups)) {
        let a = specimen_match.groups[key];
        let b = box_match.groups[key];
        is_valid = is_valid && (isEmpty(a) || a === b);
    }
    return is_valid;
};

const userid = ref(OrcaSpecimenTracking().userid);

const dt = ref();
const specimen = ref(defaultSpecimenState());
const fields = ref({});

const debug = ref();
const errors = ref([]);
const warnings = ref({});
const isLoading = ref(false);

const batchEnabled = ref(false);

// INPUT REFS
const inputRefMap = ref();
const inputRefs = useTemplateRef('specimen-input');
const specimen_name_input = ref();
const moveCancelBtn = ref();
const confirmWarningCancelBtn = ref();

// COMPUTED
const batchMode = computed(() => {
    return batchEnabled.value ? 'On' : 'Off';
});

// VUELIDATE
const rules = computed(() => {
    const myRules = {};
    if (isNotEmpty(fields.value)) {
        let k = null;
        for (k in fields.value['specimen']) {
            // validation
            let sr = {};
            let fv = fields.value['specimen'][k];
            // specialized rules for specific fields
            if (k === 'specimen_name') {
                sr = {
                    required: helpers.withMessage(`<strong>${fv['field_label']}</strong> is required`, required),
                    regexMatch: helpers.withMessage('Value provided does not match the required nomenclature!',
                        (value) => isEmpty(value) || value.match(config['general']['specimen_name_regex'])
                    ),
                    specimenMatchesBox: helpers.withMessage('Box nomenclature mismatch - one or more parts do not align with the current box', specimenMatchesBox)
                };
            } else {
                // default rule behaviors
                if (fv['required'] === true) {
                    sr.required = helpers.withMessage(`<strong>${fv['field_label']}</strong> is required`, required);
                }
                // leverage built-in redcap validation
                // be sure to trim the leading/trailing slashes, otherwise the match will fail
                if (isNotEmpty(fv['validation']) && config.validation[fv['validation']['type']]) {
                    // get the validation info
                    let val_info = config.validation[fv['validation']['type']];
                    // normalize datetime due to the component being used
                    if (fv['field_type'] === 'datetime') val_info = config.validation['datetime_ymd'];
                    // apply validation to the rules
                    sr[fv['validation']['type']] = helpers.withMessage(val_info['validation_label'],
                        (value) => isEmpty(value) || value.match(val_info['regex_js'].replace(/^\/|\/$/g, ''))
                    );
                }
                // time to handle the extras config
                if (isNotEmpty(config['save-state']['specimen'][k]['extras'])) {
                    for (let x in config['save-state']['specimen'][k]['extras']) {
                        let extras = config['save-state']['specimen'][k]['extras'][x];
                        // skip if not enabled
                        if (!extras['enabled']) continue;
                        // noFuture
                        if (x === 'noFuture') {
                            sr.noFuture = helpers.withMessage(`<strong>${fv['field_label']}</strong> cannot be in the future!`, noFutureDateTime);
                        }
                        // afterDate
                        if (x === 'afterDate' && isNotEmpty(extras['target'])) {
                            // errors vs warningOnly is handled within the custom validator
                            sr.afterDateTime = helpers.withMessage(ModuleUtils.afterDateTimeErrorMessage(fv['field_label'], fields.value['specimen'][extras['target']]['field_label'], null), afterDateTime(extras));
                            sr.afterDateMinMax = helpers.withMessage(ModuleUtils.afterDateTimeErrorMessage(fv['field_label'], fields.value['specimen'][extras['target']]['field_label'], extras), afterDateMinMax(k, extras));
                        }
                        // confirm
                        if (x === 'confirm') {
                            // have the sameAs reference the confirm pseudo field
                            sr.sameAsConfirm = helpers.withMessage(`<strong>${fv['field_label']}</strong> double entry confirmation mismatch!`, sameAsConfirm(k));
                        }
                    }
                }
            }
            if (isNotEmpty(sr)) {
                myRules[k] = sr;
            }
        }
    }
    return myRules;
});
// server-side validation support
const $vuelidateExternalResults = ref({
    specimen_name: []
});

const v$ = useVuelidate(rules, specimen, {
    $lazy: true,
    $autoDirty: true,
    $externalResults: $vuelidateExternalResults
});

// WATCHES
watch(() => config, async () => {
    initialize();
});
watchEffect(() => {
    if (inputRefs.value) {
        // build the input ref map
        let map = {};
        for (const i in inputRefs.value) {
            map[inputRefs.value[i].id] = i;
        }
        inputRefMap.value = Object.assign({}, map);
    }
})


// METHODS
const initSpecimen = (f) => {
    // THIS SHOULD ONLY OCCUR ONCE, DURING MOUNT!
    let o = {};
    let d = {};
    if (isNotEmpty(f)) {
        for (let k in f['specimen']) {
            let fv = f['specimen'][k];
            // special handling for datetime, due to component datetime format
            // Component -> 2024-10-01T15:00
            // REDCap    -> 2024-10-01 15:00
            if (fv['field_type'] === 'datetime') {
                // data state
                d[k] = null;
                o[k] = computed({
                    get() {
                        if (isEmpty(dt.value[k])) return null;
                        return dt.value[k].replace('T', ' ');
                    },
                    set(_) {
                        dt.value[k] = _?.replace(' ', 'T') ?? null;
                    }
                });
            } else {
                // data state
                o[k] = null;
            }
        }
    }
    dt.value = Object.assign({}, d);
    specimen.value = Object.assign(defaultSpecimenState(), o);
};

const searchSpecimenCallback = (data) => {
    if (data.match_type === 'exact') {
        if (data.specimen.box_record_id === box_record_id &&
            data.specimen.box_position === box_position.position) {
            Object.assign($vuelidateExternalResults.value, {
                specimen_name: ['Cannot process specimen as it already exists on this box!']
            });
            nextTick(() => {
                v$.value.specimen_name.$validate();
            });
        } else if (data.box.box_status === 'closed') {
            Object.assign($vuelidateExternalResults.value, {
                specimen_name: ['Cannot process specimen because it exists on a closed box!']
            });
            this.$nextTick(() => {
                v$.value.specimen_name.$validate();
            });
        } else {
            // this is a move
            confirmMove(data);
        }
    } else if (data.match_type === 'partial') {
        // partial match is only possible if the feature is enabled and properly configured
        // so we should be able to assume this configuration has data
        // local var for specimen_name extras for easy access
        const snx = config['save-state']['specimen']['specimen_name']['extras'];
        if (isNotEmpty(snx['matchPrefill'])) {
            for (const i in snx['matchPrefill']['fields']) {
                let k = snx['matchPrefill']['fields'][i];
                if (specimen.value.hasOwnProperty(k)) {
                    // ignore fields that aren't selected in the config
                    if (!config['save-state']['specimen'][k]['specimen-entry-form']) continue;
                    // if a structured field, ensure the value is valid
                    if (isNotEmpty(fields.value['specimen'][k].choices) && !fields.value['specimen'][k].choices.hasOwnProperty(data.specimen[k])) {
                        specimen.value[k] = '';
                    } else {
                        specimen.value[k] = data.specimen[k];
                        // account for a field configured to use 'confirm'
                        if (isNotEmpty(config['save-state']['specimen'][k]['extras']) &&
                            isNotEmpty(config['save-state']['specimen'][k]['extras']['confirm']) &&
                            config['save-state']['specimen'][k]['extras']['confirm']['enabled']) {
                            specimen.value[`confirm_${k}`] = data.specimen[k];
                        }
                    }
                }
            }
            showToast('success', 'Partial Match Found', `Pre-filled based on <strong class="font-monospace">[<span class="text-danger">${data.specimen['specimen_name']}</span>]</strong>`, 5000);
        }
        // move focus
        focusNext('specimen_name');
    } else {
        // not a match, so just process normally
        // use basic matching parsed values to pre-fill specimen
        if (data.parsed_value) {
            for (let k in data.parsed_value) {
                // ensure we have a known property to set
                if (specimen.value.hasOwnProperty(k)) {
                    // ignore fields that aren't selected in the config
                    if (!config['save-state']['specimen'][k]['specimen-entry-form']) continue;
                    // if a structured field, ensure the value is valid
                    if (isNotEmpty(fields.value['specimen'][k].choices) && !fields.value['specimen'][k].choices.hasOwnProperty(data.parsed_value[k])) {
                        specimen.value[k] = '';
                    } else {
                        specimen.value[k] = data.parsed_value[k];
                    }
                }
            }
        }
        showToast('secondary', 'No Match Found', `No match found for <strong class="font-monospace">[<span class="text-danger">${data.search_value}</span>]</strong>`);
        // move focus
        focusNext('specimen_name');
    }
    if (isNotEmpty(data.warnings)) {
        showToast('warn', 'Warnings Occurred During Search', `<ul class="mb-0"><li>${data.warnings.join('</li><li>')}</li></ul>`, 10000);
    }
};

const searchSpecimen = async (search_value) => {
    isLoading.value = true;
    // TODO reset specimen??
    // this.resetSpecimen(true, true);
    OrcaSpecimenTracking().jsmo.ajax('search-specimen', {
        search_value: search_value
    })
        .then(response => {
            // look for possible errors during save result
            if (isNotEmpty(response.errors)) {
                if (Array.isArray(response.errors)) {
                    errors.value.push(...response.errors);
                } else {
                    errors.value.push(response.errors);
                }
            } else {
                // success
                searchSpecimenCallback(response);
            }
        })
        .catch(err => {
            debug.value = err;
        })
        .finally(() => {
            isLoading.value = false;
        });
};

const specimenScanned = () => {
    if (v$.value.specimen_name.$dirty && !v$.value.specimen_name.$error) {
        // ensure there's a valid position available
        let response = getPositionForSpecimen(specimen.value.specimen_name);
        if (response.result === true) {
            specimen.value.box_position = response.position;
            searchSpecimen(specimen.value.specimen_name);
        } else {
            Object.assign($vuelidateExternalResults.value, response.errors.specimen);
        }
    }
};

const saveSpecimen = (s) => {
    // set overlay
    isLoading.value = true;
    // submit requests
    // ensure certain fields are set when not in 'edit' mode
    if (mode === 'new') {
        s.box_record_id = box_record_id;
        s.box_position = box_position['position'];
    }
    OrcaSpecimenTracking().jsmo.ajax('save-specimen', {
        specimen: s
    })
        .then(response => {
            // look for possible errors during save result
            if (isNotEmpty(response.errors)) {
                if (Array.isArray(response.errors)) {
                    errors.value.push(...response.errors);
                } else {
                    errors.value.push(response.errors);
                }
            } else {
                // success
                if (response) {
                    // push back to root so it can be added to specimen list
                    emit('specimenSaved', response);
                    resetSpecimen(batchEnabled.value);
                }
            }
        })
        .catch(err => {
            debug.value = err;
        })
        .finally(() => {
            isLoading.value = false;
        });
};

const trySaveSpecimen = async () => {
    let isValid = await v$.value.$validate();
    if (isValid) {
        if (isNotEmpty(warnings.value)) {
            // prompt for warnings before save
            confirmWarnings(warnings.value);
        } else {
            // save it!
            saveSpecimen(specimen.value);
        }
    }
};

const resetFocus = () => {
    if (mode === 'new') {
        specimen_name_input.value.focus();
    }
}

const focusNext = (current) => {
    // initialize next to -1, so prevent action if we don't find a valid target
    let next = -1;
    // if field name is mapped, get its index
    if (current === 'specimen_name') {
        // specimen_name is outside the refs so next field is at index 0
        next = 0;
    } else if (isNotEmpty(inputRefMap.value[current])) {
        next = parseInt(inputRefMap.value[current]) + 1;
    }
    // move focus to the next field if it's not the last
    if (isNotEmpty(inputRefs.value[next])) {
        nextTick(() => inputRefs.value[next].focus());
    }
} ;

const resetSpecimen = (preserveBatch = false) => {
    // clear any warnings before field loop
    warnings.value = {};
    // reset each property on the specimen
    for (let k in specimen.value) {
        // handle exclusions
        if (mode === 'edit' && [
            'specimen_name',
            'record_id',
            'box_position',
            'box_record_id'
        ].includes(k)) continue;
        // pseudo fields may exist, and if so, ignore them directly
        //   they will be handled indirectly through other true fields
        if (isEmpty(config['save-state']['specimen'][k])) continue;
        // ensure i'm not wiping out fields that cannot be seen/edited
        if (!config['save-state']['specimen'][k]['specimen-entry-form']) continue;
        // preserve batch fields, if necessary
        if (preserveBatch && config['save-state']['specimen'][k]['batch-mode']) continue;
        // look for fields with a configured default value
        if (isNotEmpty(config['save-state']['specimen'][k]['field-default'])) {
            specimen.value[k] = config['save-state']['specimen'][k]['field-default'];
        } else {
            // if we get this far, reset the value
            specimen.value[k] = null;
            // wipe out a pseudo field if exists
            if (isNotEmpty(config['save-state']['specimen'][k]['extras']) &&
                isNotEmpty(config['save-state']['specimen'][k]['extras']['confirm']) &&
                config['save-state']['specimen'][k]['extras']['confirm']['enabled']) {
                specimen.value[`confirm_${k}`] = null;
            }
        }
    }
    v$.value.$reset();
    nextTick(() => {
        resetFocus();
    });
};
const loadSpecimen = (id) => {
    // set overlay
    isLoading.value = true;
    // submit requests
    OrcaSpecimenTracking().jsmo.ajax('get-specimen', {
        specimen_record_id: id
    })
    .then(response => {
        // look for possible errors during save result
        if (isNotEmpty(response.errors)) {
            if (Array.isArray(response.errors)) {
                errors.value.push(...response.errors);
            } else {
                errors.value.push(response.errors);
            }
        } else {
            // success
            specimen.value = Object.assign(specimen.value, (response.specimen ?? {}));
            // sync up fields flagged for 'confirm' validation
            if (mode === 'edit') {
                for (let k in specimen.value) {
                    if (isNotEmpty(config['save-state']['specimen'][k]) &&
                        isNotEmpty(config['save-state']['specimen'][k]['extras']) &&
                        isNotEmpty(config['save-state']['specimen'][k]['extras']['confirm']) &&
                        config['save-state']['specimen'][k]['extras']['confirm']['enabled']) {
                        specimen.value[`confirm_${k}`] = specimen.value[k];
                    }
                }
            }
        }
    })
    .catch(err => {
        debug.value = err;
    })
    .finally(() => {
        isLoading.value = false;
    });
}

// PROPS | EXPOSE | EMITS | INJECTS
const {
    resetDisable,
    batchDisable,
    mode,
    box_record_id,
    box_position,
    box_info,
    config
} = defineProps({
    resetDisable: {
        type: Boolean,
        required: false
    },
    batchDisable: {
        type: Boolean,
        required: false
    },
    mode: {
        type: String,
        required: true
    },
    box_record_id: {
        type: String,
        required: true
    },
    box_position: {
        type: String,
        required: false
    },
    box_info: {
        type: Object,
        required: true
    },
    config: {
        type: Object,
        required: true
    }
});

defineExpose({
    resetFocus,
    resetSpecimen,
    loadSpecimen
});

const emit = defineEmits([
    'specimenSaved'
]);

const getPositionForSpecimen = inject('getPositionForSpecimen', (name) => {});

const initialize = () => {
    initSpecimen(config.fields);
    fields.value = config.fields ?? {};
    // reset the specimen, which initializes the focus
    nextTick(() => resetSpecimen());
};

onMounted(() => {
    initialize();
});
</script>

<template>
    <BlockUI :blocked="isLoading">
        <template v-if="isNotEmpty(errors)">
            <div class="alert alert-danger p-4">
                <h1 class="display-4">Critical Errors Exist!</h1>
                <p class="lead mb-0">This dashboard has been disabled until all critical errors have been resolved.</p>
                <hr/>
                <ul>
                    <template v-for="error in errors">
                        <li>{{ error }}</li>
                    </template>
                </ul>
            </div>
        </template>

        <!-- MAIN CONTENT AREA -->
        <div class="row">
            <!-- specimen - name display -->
            <div class="col-12 mb-2">
                <label class="form-label">{{ config['fields']['specimen']['specimen_name']['field_label'] }}<span v-if="mode!=='edit'" class="text-danger">*</span></label>
                <template v-if="mode==='edit'">
                    <input type="text" class="form-control bg-dark-subtle text-secondary" ref="specimen_name_input" v-model="specimen.specimen_name" disabled="disabled" />
                </template>
                <template v-else>
                    <input type="text" class="form-control" ref="specimen_name_input" v-model="specimen.specimen_name" autocomplete="off"
                           @keydown.tab="specimenScanned" @keyup.enter="specimenScanned"
                    />
                    <div class="alert alert-danger mt-1 mb-0 px-3 py-2" v-if="v$.specimen_name && v$.specimen_name.$error">
                        <strong>Validation Error:</strong>
                        <ul class="mb-0">
                            <li v-for="error of v$.specimen_name.$errors" :key="error.$uid" v-html="error.$message"></li>
                        </ul>
                    </div>
                </template>
            </div>
            <!-- FIELD LOOP - FIELDS -->
            <template v-for="(fv, fk) in fields['specimen']">
                <template v-if="config['save-state']['specimen'][fk]['specimen-entry-form'] && !fv['config']['specimen-entry-form']['required']">
                    <!-- {{ fk }} -->
                    <div class="pt-1 pb-2 rounded-0" :class="[
                        config['save-state']['specimen'][fk]['batch-mode'] && batchEnabled ? 'batch-mode' : '',
                        fv['field_type']==='notes' ? 'col-12 col-xl-6' : 'col-6'
                    ]">
                        <div class="row g-0" :id="`${fk}-labels`">
                            <div class="col">
                                <label class="form-label mb-1">{{ fv['field_label'] }}<span v-if="fv['required']" class="text-danger">*</span></label>
                            </div>
                        </div>
                        <template v-if="fv['field_type']==='text'">
                            <div class="input-group">
                                <input type="text" class="form-control" :id="fk" ref="specimen-input" v-model="specimen[fk]" autocomplete="off"
                                        @keyup.enter="focusNext(fk)" />
                                <template v-if="config['save-state']['specimen'][fk]['extras']['confirm']['enabled']">
                                    <input type="text" class="form-control" :id="`confirm_${fk}`" ref="specimen-input" v-model="specimen[`confirm_${fk}`]" autocomplete="off"
                                           @keyup.enter="focusNext(`confirm_${fk}`)"
                                    />
                                    <Teleport defer :to="`#${fk}-labels`">
                                        <div class="col">
                                            <label class="form-label mb-1">Confirm {{ fv['field_label'] }}<span v-if="fv['required']" class="text-danger">*</span></label>
                                        </div>
                                    </Teleport>
                                </template>
                                <template v-if="isNotEmpty(config['save-state']['specimen'][fk]['field-units'])">
                                    <span class="input-group-text">{{ config['save-state']['specimen'][fk]['field-units'] }}</span>
                                </template>
                            </div>
                        </template>
                        <template v-else-if="fv['field_type']==='dropdown'">
                            <select class="form-select" :id="fk" ref="specimen-input" v-model="specimen[fk]">
                                <option value="">--</option>
                                <option v-for="(ov, ok) in fv['choices']" :value="ok">{{ ov }}</option>
                            </select>
                        </template>
                        <template v-else-if="fv['field_type']==='date'">
                            <input type="date" class="form-control" v-model="specimen[fk]" :id="fk" ref="specimen-input" />
                        </template>
                        <template v-else-if="fv['field_type']==='datetime'">
                            <input type="datetime-local" class="form-control" v-model="dt[fk]" :id="fk" ref="specimen-input" />
                        </template>
                        <template v-else-if="fv['field_type']==='notes'">
                            <textarea rows="3" class="form-control" v-model="specimen[fk]" :id="fk" ref="specimen-input" />
                        </template>
                        <div class="alert alert-danger mt-1 mb-0 px-3 py-2" v-if="v$[fk] && v$[fk].$error">
                            <strong>Validation Error:</strong>
                            <ul class="mb-0">
                                <li v-for="error of v$[fk].$errors" :key="error.$uid" v-html="error.$message"></li>
                            </ul>
                        </div>
                    </div>
                </template>
            </template>
        </div>
        <!-- empty spacer row -->
        <div class="row mt-2"></div>
        <div class="alert alert-warning px-3 py-2" v-if="isNotEmpty(warnings)">
            <strong>CAUTION:</strong> - The following warnings exist - review before saving!
            <ul class="mb-0">
                <li v-for="(v, k) in warnings" v-html="v"></li>
            </ul>
        </div>
        <div class="row">
            <div class="col d-grid mb-0">
                <button type="button" class="btn btn-success" @click="trySaveSpecimen">
                    <i class="fas fa-save"></i>&nbsp;Save
                </button>
            </div>
            <div class="col d-grid mb-0" v-if="mode === 'new'">
                <button type="button" class="btn btn-danger" @click="resetSpecimen" >
                    <i class="fas fa-undo"></i>&nbsp;Reset
                </button>
            </div>
            <div class="col d-grid mb-0" v-if="mode === 'new'">
                <button type="button" class="btn btn-warning text-light" @click="() => batchEnabled = !batchEnabled" >
                    <i class="fas fa-sync"></i>&nbsp;Batch Mode : {{ batchMode }}
                </button>
            </div>
        </div>
        <ConfirmDialog :group="`move-${uid}`" :style="{ width: '40rem' }" class="p-dialog-headless">
            <template #container="{ message, acceptCallback, rejectCallback }">
                <div class="card border-1 border-primary">
                    <div class="card-header bg-secondary-subtle">
                        <h5>Moving: [<span class="text-monospace text-danger">{{ message.header }}</span>]</h5>
                    </div>
                    <div class="card-body py-0">
                        <table class="table text-center m-0">
                            <thead>
                            <tr>
                                <th>From</th>
                                <th><i class="fas fa-angle-double-right"></i></th>
                                <th>To</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td>{{ message.message.box.box_name }}</td>
                                <th>BOX</th>
                                <td>{{ box_info['box_name'] }}</td>
                            </tr>
                            <tr>
                                <td>{{ message.message.specimen.box_position }}</td>
                                <th>POS</th>
                                <td>{{ box_position.position }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-secondary-subtle">
                        <div class="d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-outline-dark" @click="rejectCallback" ref="moveCancelBtn">Cancel</button>
                            <button type="button" class="btn btn-primary" @click="acceptCallback">Confirm</button>
                        </div>
                    </div>
                </div>
            </template>
        </ConfirmDialog>
        <ConfirmDialog :group="`warnings-${uid}`" :style="{ width: '40rem' }" class="p-dialog-headless">
            <template #container="{ message, acceptCallback, rejectCallback }">
                <div class="card border-1 border-warning">
                    <div class="card-header bg-warning-subtle">
                        <h5 class="mb-0">{{ message.header }}</h5>
                    </div>
                    <div class="card-body py-0">
                        <div>
                            <p class="lead">The following <strong class="text-danger text-uppercase">warnings</strong> exist.  Please review prior to saving this specimen.</p>
                            <ul>
                                <li v-for="(v, k) in message.message" v-html="v"></li>
                            </ul>
                            <!-- check for a target note field for documenting the ack -->
                            <div v-if="isNotEmpty(config['general']['warning_ack_field'])"
                                 class="border-start border-3 border-warning bg-warning-subtle py-2 ps-2 mb-3">
                                <strong class="text-warning">NOTE:</strong> Upon Save, the warnings will be appended to the <code>[{{ config['general']['warning_ack_field'] }}]</code> field.
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-warning">
                        <div class="d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-outline-dark" @click="rejectCallback" ref="confirmWarningCancelBtn">Cancel</button>
                            <button type="button" class="btn btn-success" @click="acceptCallback">Save</button>
                        </div>
                    </div>
                </div>
            </template>
        </ConfirmDialog>
        <ProgressSpinner v-show="isLoading" class="overlay"/>
    </BlockUI>
    <pre v-if="debug" class="mt-3">{{ debug }}</pre>
</template>

<style lang="scss" scoped>
@import "../node_modules/bootstrap/scss/functions";
@import "../node_modules/bootstrap/scss/variables";
@import "../node_modules/bootstrap/scss/mixins";
.batch-mode {
    font-weight: bold;
    background-color: $yellow-200 !important;
}
.batch-mode input.form-control, .batch-mode span.input-group-text {
    border-color: var(--bs-warning) !important;
}
.alert ul {
    padding-inline-start: 15px;
}
.overlay {
    position: fixed !important;
    top: calc(50% - 50px);
    left: calc(50% - 50px);
    z-index: 100; /* this seems to work for me but may need to be higher*/
}
/* headless dialogs still have styling that needs to be removed */
.p-dialog-headless{
    background: unset !important;
    box-shadow: unset !important;
    border: unset !important;
}
textarea {
    font-size: .85rem;
}
</style>