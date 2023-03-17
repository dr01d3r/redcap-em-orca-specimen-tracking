import qs from 'qs';

export default {
    components: {
        qs
    },
    methods: {
        toast(msg, title, variant, duration = 5000, toaster = 'b-toaster-bottom-right') {
            this.$bvToast.toast(msg, {
                title: title,
                variant: variant,
                autoHideDelay: duration,
                toaster: toaster,
                solid: true
            });
        },
        isEmpty(s) {
            return ((s == null) || (s.length == 0))
        },
        isObjectEmpty(someObject){
            return !(Object.keys(someObject).length)
        },
        qs_get(key) {
            let params = qs.parse(location.search);
            return params[key];
        },
        qs_push(key, value, replace) {
            let params = qs.parse(location.search.substring(1));
            params[key] = value;
            let new_params_string = qs.stringify(params);
            if (replace === true) {
                history.replaceState({}, "", window.location.pathname + '?' + new_params_string);
            } else {
                history.pushState({}, "", window.location.pathname + '?' + new_params_string);
            }
        },
        qs_remove(key, replace) {
            let params = qs.parse(location.search.substring(1));
            if (params[key]) {
                delete params[key];
                let new_params_string = qs.stringify(params);
                if (replace === true) {
                    history.replaceState({}, "", window.location.pathname + '?' + new_params_string);
                } else {
                    history.pushState({}, "", window.location.pathname + '?' + new_params_string);
                }
            }
        }
    }
}