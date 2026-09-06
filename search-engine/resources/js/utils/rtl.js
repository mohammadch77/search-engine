const RTL_PATTERN = /[֐-׿؀-ۿ܀-ݏݐ-ݿࢠ-ࣿﭐ-﷿ﹰ-﻿]/;

export function isRtlText(text) {
    return RTL_PATTERN.test(text || '');
}

export function dirFor(text) {
    return isRtlText(text) ? 'rtl' : 'ltr';
}
