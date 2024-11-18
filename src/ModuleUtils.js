import qs from 'qs';
import _ from "lodash";
import { DateTime, Duration } from 'luxon';
import { isNotEmpty, isNumber } from "@primeuix/utils/object";

export default class ModuleUtils {

    static regexCaptureGroups = /<([a-zA-Z0-9_]+)>/g;
    static luxonDateTimeFormatFrom =  'yyyy-MM-dd HH:mm';

    static qs_get(key) {
        let params = qs.parse(location.search);
        return params[key];
    }
    static qs_push(key, value, replace) {
        let params = qs.parse(location.search.substring(1));
        params[key] = value;
        let new_params_string = qs.stringify(params);
        if (replace === true) {
            history.replaceState({}, "", window.location.pathname + '?' + new_params_string);
        } else {
            history.pushState({}, "", window.location.pathname + '?' + new_params_string);
        }
    }
    static qs_remove(key, replace) {
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

    static deepMerge(...obj) {
        return _.merge(...obj);
    }

    static formatDate(date, format) {
        let rc_fmt_date = 'yyyy-MM-dd';
        let rc_fmt_datetime = 'yyyy-MM-dd HH:mm';
        let val = date;
        switch (format) {
            case 'date_dmy': val = DateTime.fromFormat(date, rc_fmt_date).toFormat('dd-MM-yyyy'); break;
            case 'date_mdy': val = DateTime.fromFormat(date, rc_fmt_date).toFormat('MM-dd-yyyy'); break;
            case 'date_ymd': val = DateTime.fromFormat(date, rc_fmt_date).toFormat('yyyy-MM-dd'); break;
            case 'datetime_dmy': val = DateTime.fromFormat(date, rc_fmt_datetime).toFormat('dd-MM-yyyy HH:mm'); break;
            case 'datetime_mdy': val = DateTime.fromFormat(date, rc_fmt_datetime).toFormat('MM-dd-yyyy HH:mm'); break;
            case 'datetime_ymd': val = DateTime.fromFormat(date, rc_fmt_datetime).toFormat('yyyy-MM-dd HH:mm'); break;
        }
        return val;
    }

    static minutesToHuman(t) {
        if (isNumber(t)) {
            return Duration.fromObject({ minutes: parseInt(t) })
                .rescale()
                .toHuman();
        }
        return t;
    }

    static afterDateTimeErrorMessage = (field, target, extras) => {
        let d1 = isNotEmpty(field) ? field : 'placeholder';
        let d2 = isNotEmpty(target) ? target : 'placeholder';
        let time = '';
        if (isNotEmpty(extras)) {
            let t1 = ModuleUtils.minutesToHuman(extras['minimum']);
            let t2 = ModuleUtils.minutesToHuman(extras['maximum']);
            if (isNotEmpty(t1) && isNotEmpty(t2)) {
                time = `between ${t1} and ${t2} `;
            } else if (isNotEmpty(t1)) {
                time = `a minimum ${t1} `;
            } else if (isNotEmpty(extras['maximum'])) {
                time = `a maximum ${t2} `;
            }
        }
        // if (time.length) {
        //     time = `<strong>${time}</strong> `;
        // }
        // return `<strong>${d1}</strong> must be ${time}after <strong>${d2}</strong>`;
        return `${d1} must be ${time}after ${d2}`;
    };

    static toNumericBoxPosition = (pos, regex, cols) => {
        let x = regex.exec(pos);
        let x1 = 0;
        let x2 = x.groups.col * 1;
        switch (x.groups.row.length) {
            case 1:
                x1 = (x.groups.row.charCodeAt(0) - 65) * cols;
                break;
            case 2:
                let x1a = (x.groups.row.charCodeAt(0) - 64) * 26 * cols;
                let x1b = (x.groups.row.charCodeAt(1) - 65) * cols;
                x1 = x1a + x1b;
                break;
        }
        return x1 + x2;
    }

    static warningAcknowledgementMessagePreview = (userid = 'joe_user', msg = 'Warning Message') => {
        let timestamp = DateTime.now().toFormat(ModuleUtils.luxonDateTimeFormatFrom);
        return `[${timestamp}][${userid}] - ${msg}`;
    };

    static getRegExpGroups(rx) {
        let g = [];
        if (isNotEmpty(rx)) {
            for (const m of `${rx}`.matchAll(ModuleUtils.regexCaptureGroups)) {
                g.push(m[1]);
            }
        }
        return g;
    }
}