<template>
    <main v-if="ready" class="sheetflow">
        <h1>{{ text('title') }}</h1>
        <p class="lead">{{ text('lead') }}</p>

        <section>
            <h2>{{ text('upload_heading') }}</h2>
            <form @submit.prevent="upload">
                <label>
                    {{ text('file') }}
                    <input type="file" accept=".csv,.txt,.xls,.xlsx,.ods" @change="onFile">
                </label>
                <button type="submit">{{ text('upload') }}</button>
            </form>
        </section>

        <section v-if="run && run.headers.length">
            <h2>{{ text('preview_heading') }}</h2>
            <label v-if="run.sheet_names.length > 1">
                {{ text('sheet') }}
                <select :value="run.sheet" @change="changeSheet">
                    <option v-for="name in run.sheet_names" :key="name" :value="name">{{ name }}</option>
                </select>
            </label>
            <table>
                <thead>
                    <tr>
                        <th v-for="header in run.headers" :key="header">{{ header }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in run.preview_rows" :key="index">
                        <td v-for="header in run.headers" :key="header">{{ row[header] }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section v-if="run && mapped">
            <h2>{{ text('map_heading') }}</h2>
            <label v-if="run.save_templates !== false && run.templates.length">
                {{ text('saved_template') }}
                <select @change="applyTemplate">
                    <option value="">{{ text('choose') }}</option>
                    <option v-for="template in run.templates" :key="template.id" :value="template.id">{{ template.name }}</option>
                </select>
            </label>
            <table>
                <thead>
                    <tr>
                        <th>{{ text('field') }}</th>
                        <th>{{ text('file_column') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="field in run.fields" :key="field.name">
                        <td>{{ field.label }}<span v-if="field.required"> *</span></td>
                        <td>
                            <select v-model="columnMap[field.name]">
                                <option :value="null">{{ text('skip') }}</option>
                                <option v-for="header in run.headers" :key="header" :value="header">{{ header }}</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <label v-if="run.save_templates !== false">
                {{ text('template_name') }}
                <input v-model="templateName" type="text" maxlength="255">
            </label>
            <button v-if="run.save_templates !== false" type="button" @click="saveMapping">{{ text('save_mapping') }}</button>
        </section>

        <section v-if="run">
            <h2>{{ text('progress') }}</h2>
            <p class="meta">{{ run.filename }} · {{ text('statuses.' + run.status) }} · {{ run.processed_rows }} / {{ run.total_rows }}</p>
            <div class="progress"><span :style="{ width: run.percent + '%' }"></span></div>
            <p class="meta">{{ text('valid') }} {{ run.valid_rows }} · {{ text('failed') }} {{ run.failed_rows }} · {{ text('imported') }} {{ run.imported_rows }}</p>
            <button v-if="busy" class="danger" type="button" @click="cancel">{{ text('cancel') }}</button>
        </section>

        <section v-if="run">
            <h2>{{ text('actions_heading') }}</h2>
            <div class="row">
                <button type="button" :disabled="run.status !== 'staged'" @click="validateRun">{{ text('validate') }}</button>
                <button type="button" :disabled="run.status !== 'validated' || run.valid_rows < 1" @click="importRows">{{ text('import_valid') }}</button>
            </div>
            <p v-if="run.status === 'validated'" class="notice">{{ validationNotice }}</p>
            <p v-if="run.status === 'completed'" class="notice">{{ importNotice }}</p>
        </section>

        <section v-if="failures.length">
            <h2>{{ text('row_errors') }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>{{ text('row') }}</th>
                        <th>{{ text('error') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="failure in failures" :key="failure.row_number">
                        <td>{{ failure.row_number }}</td>
                        <td>{{ messages(failure) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <p v-if="error" class="error">{{ error }}</p>
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import '../../css/wizard.css';

const props = defineProps({
    definition: { type: String, required: true },
    apiBase: { type: String, required: true },
    messages: { type: Object, default: null },
});

const copy = ref(props.messages ?? {});
const ready = ref(props.messages !== null);
const run = ref(null);
const file = ref(null);
const columnMap = ref({});
const templateName = ref('');
const failures = ref([]);
const error = ref('');
let timer = null;

const busy = computed(() => run.value && ['staging', 'validating', 'importing'].includes(run.value.status));
const mapped = computed(() => run.value && !['staging', 'uploaded'].includes(run.value.status));

watch(run, (value) => {
    if (!value) {
        return;
    }

    columnMap.value = { ...value.column_map };

    if (value.failed_rows > 0 && ['validated', 'completed', 'importing', 'failed'].includes(value.status)) {
        loadFailures();
    } else {
        failures.value = [];
    }
});

function text(path) {
    const value = path.split('.').reduce((carry, key) => {
        if (carry && typeof carry === 'object' && Object.prototype.hasOwnProperty.call(carry, key)) {
            return carry[key];
        }

        return undefined;
    }, copy.value);

    return typeof value === 'string' ? value : path;
}

function fill(path, replacements) {
    let value = text(path);

    Object.entries(replacements).forEach(([key, replacement]) => {
        value = value.replaceAll(':' + key, String(replacement));
    });

    return value;
}

const validationNotice = computed(() => {
    if (!run.value || run.value.status !== 'validated') {
        return '';
    }

    const key = run.value.failed_rows > 0 ? 'validated_partial' : 'validated_ready';

    return fill(key, {
        valid: run.value.valid_rows,
        failed: run.value.failed_rows,
    });
});

const importNotice = computed(() => {
    if (!run.value || run.value.status !== 'completed') {
        return '';
    }

    const key = run.value.failed_rows > 0 ? 'imported_partial' : 'imported_ready';

    return fill(key, {
        imported: run.value.imported_rows,
        failed: run.value.failed_rows,
    });
});

onMounted(async () => {
    if (props.messages !== null) {
        return;
    }

    try {
        const response = await fetch(`${props.apiBase}/translations`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();
        copy.value = payload && typeof payload === 'object' ? payload : {};
    } catch (caught) {
        copy.value = {};
    }

    ready.value = true;
});

function token() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function jsonHeaders() {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token(),
        'X-Requested-With': 'XMLHttpRequest',
    };
}

async function request(url, options) {
    const response = await fetch(url, options);
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const errors = payload.errors ?? {};
        const first = Object.values(errors)[0];
        throw new Error(Array.isArray(first) ? first[0] : (payload.message ?? text('request_failed')));
    }

    return payload;
}

function onFile(event) {
    file.value = event.target.files?.[0] ?? null;
}

async function upload() {
    error.value = '';
    const body = new FormData();
    body.append('file', file.value);
    const headers = {
        Accept: 'application/json',
        'X-CSRF-TOKEN': token(),
        'X-Requested-With': 'XMLHttpRequest',
    };

    try {
        run.value = await request(`${props.apiBase}/${props.definition}/uploads`, {
            method: 'POST',
            headers,
            body,
        });
        watchRun();
    } catch (caught) {
        error.value = caught.message;
    }
}

async function changeSheet(event) {
    try {
        run.value = await request(`${props.apiBase}/runs/${run.value.id}/sheet`, {
            method: 'PUT',
            headers: jsonHeaders(),
            body: JSON.stringify({ sheet: event.target.value }),
        });
        watchRun();
    } catch (caught) {
        error.value = caught.message;
    }
}

function applyTemplate(event) {
    const template = run.value.templates.find((item) => String(item.id) === event.target.value);

    if (template) {
        columnMap.value = { ...template.column_map };
    }
}

async function saveMapping() {
    error.value = '';

    try {
        run.value = await request(`${props.apiBase}/runs/${run.value.id}/mapping`, {
            method: 'PUT',
            headers: jsonHeaders(),
            body: JSON.stringify({
                column_map: columnMap.value,
                save_template: templateName.value !== '',
                template_name: templateName.value,
            }),
        });
    } catch (caught) {
        error.value = caught.message;
    }
}

async function validateRun() {
    error.value = '';

    try {
        run.value = await request(`${props.apiBase}/runs/${run.value.id}/validation`, {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({ column_map: columnMap.value }),
        });
        watchRun();
    } catch (caught) {
        error.value = caught.message;
    }
}

async function importRows() {
    error.value = '';

    try {
        run.value = await request(`${props.apiBase}/runs/${run.value.id}/import`, {
            method: 'POST',
            headers: jsonHeaders(),
        });
        watchRun();
    } catch (caught) {
        error.value = caught.message;
    }
}

async function cancel() {
    try {
        run.value = await request(`${props.apiBase}/runs/${run.value.id}/cancel`, {
            method: 'POST',
            headers: jsonHeaders(),
        });
        watchRun();
    } catch (caught) {
        error.value = caught.message;
    }
}

async function loadFailures() {
    const payload = await request(`${props.apiBase}/runs/${run.value.id}/failures`, {
        headers: jsonHeaders(),
    });
    failures.value = payload.data ?? [];
}

function messages(failure) {
    return Object.values(failure.errors ?? {}).flat().join(' ');
}

function watchRun() {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }

    if (!busy.value) {
        return;
    }

    timer = setInterval(async () => {
        run.value = await request(`${props.apiBase}/runs/${run.value.id}`, {
            headers: jsonHeaders(),
        });

        if (!['staging', 'validating', 'importing'].includes(run.value.status)) {
            clearInterval(timer);
            timer = null;
        }
    }, 1000);
}

onBeforeUnmount(() => {
    if (timer) {
        clearInterval(timer);
    }
});
</script>
