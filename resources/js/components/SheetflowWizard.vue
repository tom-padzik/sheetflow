<template>
    <main class="sheetflow">
        <h1>Import spreadsheet</h1>
        <p class="lead">Upload a CSV, XLSX, or ODS file, map the columns, then validate and import.</p>

        <section>
            <h2>1. Upload</h2>
            <form @submit.prevent="upload">
                <label>
                    File
                    <input type="file" accept=".csv,.txt,.xlsx,.ods" @change="onFile">
                </label>
                <button type="submit">Upload</button>
            </form>
        </section>

        <section v-if="run">
            <h2>Progress</h2>
            <p class="meta">{{ run.filename }} · {{ run.status }} · {{ run.processed_rows }} / {{ run.total_rows }}</p>
            <div class="progress"><span :style="{ width: run.percent + '%' }"></span></div>
            <p class="meta">Valid {{ run.valid_rows }} · Failed {{ run.failed_rows }} · Imported {{ run.imported_rows }}</p>
            <button v-if="busy" class="danger" type="button" @click="cancel">Cancel</button>
        </section>

        <section v-if="run && run.headers.length">
            <h2>2. Preview</h2>
            <label v-if="run.sheet_names.length > 1">
                Sheet
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
            <h2>3. Map columns</h2>
            <label v-if="run.templates.length">
                Saved template
                <select @change="applyTemplate">
                    <option value="">Choose</option>
                    <option v-for="template in run.templates" :key="template.id" :value="template.id">{{ template.name }}</option>
                </select>
            </label>
            <table>
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>File column</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="field in run.fields" :key="field.name">
                        <td>{{ field.label }}<span v-if="field.required"> *</span></td>
                        <td>
                            <select v-model="columnMap[field.name]">
                                <option :value="null">Skip</option>
                                <option v-for="header in run.headers" :key="header" :value="header">{{ header }}</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <label>
                Template name
                <input v-model="templateName" type="text" maxlength="255">
            </label>
            <button type="button" @click="saveMapping">Save mapping</button>
        </section>

        <section v-if="run">
            <h2>4. Validate and import</h2>
            <div class="row">
                <button type="button" :disabled="run.status !== 'staged'" @click="validateRun">Validate</button>
                <button type="button" :disabled="run.status !== 'validated' || run.valid_rows < 1" @click="importRows">Import valid rows</button>
            </div>
        </section>

        <section v-if="failures.length">
            <h2>Row errors</h2>
            <table>
                <thead>
                    <tr>
                        <th>Row</th>
                        <th>Error</th>
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
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import '../../css/wizard.css';

const props = defineProps({
    definition: { type: String, required: true },
    apiBase: { type: String, required: true },
});

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
        throw new Error(Array.isArray(first) ? first[0] : (payload.message ?? 'The request failed.'));
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
