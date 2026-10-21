(function () {
    const root = document.getElementById('sheetflow-wizard');
    const app = document.getElementById('sheetflow-app');

    if (!root || !app) {
        return;
    }

    const definition = root.dataset.definition;
    const api = root.dataset.api;
    const copy = parseMessages(root.dataset.messages);
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    let run = null;
    let timer = null;

    const headers = {
        Accept: 'application/json',
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
    };

    function parseMessages(raw) {
        try {
            const parsed = JSON.parse(raw || '{}');

            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function t(path) {
        const value = path.split('.').reduce((carry, key) => {
            if (carry && typeof carry === 'object' && Object.prototype.hasOwnProperty.call(carry, key)) {
                return carry[key];
            }

            return undefined;
        }, copy);

        return typeof value === 'string' ? value : path;
    }

    function fill(path, replacements) {
        let value = t(path);

        Object.entries(replacements).forEach(([key, replacement]) => {
            value = value.replaceAll(':' + key, String(replacement));
        });

        return value;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');
    }

    async function request(url, options) {
        const response = await fetch(url, options);
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const message = firstError(payload) ?? t('request_failed');
            throw new Error(message);
        }

        return payload;
    }

    function firstError(payload) {
        if (typeof payload.message === 'string' && payload.errors === undefined) {
            return payload.message;
        }

        const errors = payload.errors ?? {};
        const first = Object.values(errors)[0];

        if (Array.isArray(first)) {
            return first[0];
        }

        return payload.message;
    }

    function render() {
        app.innerHTML = [
            uploadSection(),
            run && run.headers.length ? previewSection() : '',
            run && run.status !== 'staging' && run.status !== 'uploaded' ? mappingSection() : '',
            run ? statusSection() : '',
            run ? actionsSection() : '',
            '<div id="sheetflow-failures"></div>',
            '<p class="error" id="sheetflow-error"></p>',
        ].join('');

        document.getElementById('sheetflow-upload')?.addEventListener('submit', onUpload);
        document.getElementById('sheetflow-sheet')?.addEventListener('change', onSheet);
        document.getElementById('sheetflow-load-map')?.addEventListener('click', onLoadMap);
        document.getElementById('sheetflow-save-map')?.addEventListener('click', onSaveMap);
        document.querySelectorAll('[data-field], [data-invalid-action]').forEach((select) => {
            select.addEventListener('change', refreshSaveControls);
        });
        document.getElementById('sheetflow-validate')?.addEventListener('click', onValidate);
        document.getElementById('sheetflow-import')?.addEventListener('click', onImport);
        document.getElementById('sheetflow-cancel')?.addEventListener('click', onCancel);

        if (run && run.failed_rows > 0 && ['validated', 'completed', 'importing', 'failed'].includes(run.status)) {
            loadFailures();
        }
    }

    function uploadSection() {
        return [
            `<section><h2>${escapeHtml(t('upload_heading'))}</h2>`,
            '<form id="sheetflow-upload">',
            `<label>${escapeHtml(t('file'))}<input type="file" name="file" accept=".csv,.txt,.xls,.xlsx,.ods" required></label>`,
            `<button type="submit">${escapeHtml(t('upload'))}</button>`,
            '</form></section>',
        ].join('');
    }

    function statusSection() {
        const busy = ['staging', 'validating', 'importing'].includes(run.status);

        return [
            `<section><h2>${escapeHtml(t('progress'))}</h2>`,
            `<p class="meta">${escapeHtml(run.filename)} · ${escapeHtml(t('statuses.' + run.status))} · ${run.processed_rows} / ${run.total_rows}</p>`,
            '<div class="progress"><span style="width:' + run.percent + '%"></span></div>',
            `<p class="meta">${escapeHtml(t('valid'))} ${run.valid_rows} · ${escapeHtml(t('failed'))} ${run.failed_rows} · ${escapeHtml(t('imported'))} ${run.imported_rows}</p>`,
            busy ? `<button class="danger" id="sheetflow-cancel" type="button">${escapeHtml(t('cancel'))}</button>` : '',
            '</section>',
        ].join('');
    }

    function previewSection() {
        const head = run.headers.map((header) => `<th>${escapeHtml(header)}</th>`).join('');
        const body = run.preview_rows.map((row) => {
            const cells = run.headers.map((header) => `<td>${escapeHtml(row[header])}</td>`).join('');

            return `<tr>${cells}</tr>`;
        }).join('');
        const sheets = (run.sheet_names || []).length > 1
            ? `<label>${escapeHtml(t('sheet'))}<select id="sheetflow-sheet">${run.sheet_names.map((name) => `<option value="${escapeHtml(name)}" ${name === run.sheet ? 'selected' : ''}>${escapeHtml(name)}</option>`).join('')}</select></label>`
            : '';

        return `<section><h2>${escapeHtml(t('preview_heading'))}</h2>${sheets}<table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></section>`;
    }

    function mappingSection() {
        const choosesAction = (run.fields || []).some((field) => (field.allowed_invalid_actions || []).length > 1);
        const rows = run.fields.map((field) => {
            const selected = run.column_map[field.name] ?? '';
            const options = [`<option value="">${escapeHtml(t('skip'))}</option>`].concat(
                run.headers.map((header) => {
                    const isSelected = header === selected ? ' selected' : '';

                    return `<option value="${escapeHtml(header)}"${isSelected}>${escapeHtml(header)}</option>`;
                }),
            ).join('');
            const select = `<select data-field="${escapeHtml(field.name)}">${options}</select>`;
            const actionCell = choosesAction ? `<td>${invalidActionControl(field)}</td>` : '';

            return `<tr><td>${escapeHtml(field.label)}${field.required ? ' *' : ''}</td><td>${select}</td>${actionCell}</tr>`;
        }).join('');
        const saveTemplates = run.save_templates !== false;
        const templates = saveTemplates
            ? (run.templates || []).map((template) => `<option value="${template.id}">${escapeHtml(template.name)}</option>`).join('')
            : '';
        const loadControls = saveTemplates && templates
            ? [
                '<div class="row">',
                `<label>${escapeHtml(t('saved_template'))}<select id="sheetflow-template"><option value="">${escapeHtml(t('choose'))}</option>${templates}</select></label>`,
                `<button type="button" id="sheetflow-load-map">${escapeHtml(t('load_mapping'))}</button>`,
                '</div>',
            ].join('')
            : '';
        const saveControls = saveTemplates && mappingVerified()
            ? [
                `<div id="sheetflow-save-controls">`,
                `<label>${escapeHtml(t('template_name'))}<input id="sheetflow-template-name" type="text" maxlength="255"></label>`,
                `<div class="row"><button type="button" id="sheetflow-save-map">${escapeHtml(t('save_mapping'))}</button></div>`,
                '</div>',
            ].join('')
            : '';

        return [
            `<section><h2>${escapeHtml(t('map_heading'))}</h2>`,
            saveTemplates && templates ? loadControls : '',
            `<table><thead><tr><th>${escapeHtml(t('field'))}</th><th>${escapeHtml(t('file_column'))}</th>${choosesAction ? `<th>${escapeHtml(t('on_error'))}</th>` : ''}</tr></thead><tbody>${rows}</tbody></table>`,
            saveControls,
            '</section>',
        ].join('');
    }

    function actionsSection() {
        return [
            `<section><h2>${escapeHtml(t('actions_heading'))}</h2>`,
            '<div class="row">',
            '<button type="button" id="sheetflow-validate" ' + (run.status === 'staged' ? '' : 'disabled') + `>${escapeHtml(t('validate'))}</button>`,
            '<button type="button" id="sheetflow-import" ' + (run.status === 'validated' && run.valid_rows > 0 && !run.stopped_row ? '' : 'disabled') + `>${escapeHtml(t('import_valid'))}</button>`,
            '</div>',
            validationNotice(),
            importNotice(),
            '</section>',
        ].join('');
    }

    function mappingVerified() {
        return !!run
            && ['validated', 'importing', 'completed'].includes(run.status)
            && !run.stopped_row
            && run.failed_rows === 0
            && run.valid_rows > 0;
    }

    function formMatchesVerified() {
        return sameRecord(currentMap(), run.column_map || {})
            && sameRecord(currentInvalidActions(), run.invalid_actions || {});
    }

    function sameRecord(left, right) {
        const keys = new Set([...Object.keys(left), ...Object.keys(right)]);

        for (const key of keys) {
            const current = left[key] ?? null;
            const verified = right[key] ?? null;

            if ((current === '' ? null : current) !== (verified === '' ? null : verified)) {
                return false;
            }
        }

        return true;
    }

    function refreshSaveControls() {
        const controls = document.getElementById('sheetflow-save-controls');

        if (!controls) {
            return;
        }

        controls.hidden = !formMatchesVerified();
    }

    function validationNotice() {
        if (!run || run.status !== 'validated') {
            return '';
        }

        if (run.stopped_row) {
            return `<p class="notice">${escapeHtml(fill('import_stopped', { row: run.stopped_row }))}</p>`;
        }

        const key = run.failed_rows > 0 ? 'validated_partial' : 'validated_ready';

        return `<p class="notice">${escapeHtml(fill(key, {
            valid: run.valid_rows,
            failed: run.failed_rows,
        }))}</p>`;
    }

    function importNotice() {
        if (!run || run.status !== 'completed') {
            return '';
        }

        const key = run.failed_rows > 0 ? 'imported_partial' : 'imported_ready';

        return `<p class="notice">${escapeHtml(fill(key, {
            imported: run.imported_rows,
            failed: run.failed_rows,
        }))}</p>`;
    }

    function showError(error) {
        const node = document.getElementById('sheetflow-error');

        if (node) {
            node.textContent = error.message ?? t('something_wrong');
        }
    }

    function invalidActionControl(field) {
        const allowed = field.allowed_invalid_actions || [];
        const selected = (run.invalid_actions || {})[field.name] || field.invalid_action;

        if (allowed.length < 2) {
            return escapeHtml(t(`invalid_actions.${selected}`));
        }

        const options = allowed.map((action) => {
            const isSelected = action === selected ? ' selected' : '';

            return `<option value="${escapeHtml(action)}"${isSelected}>${escapeHtml(t(`invalid_actions.${action}`))}</option>`;
        }).join('');

        return `<select data-invalid-action="${escapeHtml(field.name)}">${options}</select>`;
    }

    function currentInvalidActions() {
        const actions = { ...(run.invalid_actions || {}) };

        document.querySelectorAll('[data-invalid-action]').forEach((select) => {
            actions[select.dataset.invalidAction] = select.value;
        });

        (run.fields || []).forEach((field) => {
            if (!actions[field.name]) {
                actions[field.name] = field.invalid_action;
            }
        });

        return actions;
    }

    function currentMap() {
        const map = {};

        document.querySelectorAll('[data-field]').forEach((select) => {
            map[select.dataset.field] = select.value === '' ? null : select.value;
        });

        return map;
    }

    async function onUpload(event) {
        event.preventDefault();
        const form = new FormData(event.target);

        try {
            run = await request(`${api}/${definition}/uploads`, {
                method: 'POST',
                headers,
                body: form,
            });
            render();
            watch();
        } catch (error) {
            render();
            showError(error);
        }
    }

    async function onSheet(event) {
        try {
            run = await request(`${api}/runs/${run.id}/sheet`, {
                method: 'PUT',
                headers: { ...headers, 'Content-Type': 'application/json' },
                body: JSON.stringify({ sheet: event.target.value }),
            });
            render();
            watch();
        } catch (error) {
            showError(error);
        }
    }

    function onLoadMap() {
        const selected = document.getElementById('sheetflow-template')?.value ?? '';
        const template = (run.templates || []).find((item) => String(item.id) === selected);
        const headers = new Set(run.headers || []);

        if (!template) {
            return;
        }

        document.querySelectorAll('[data-field]').forEach((select) => {
            const header = template.column_map?.[select.dataset.field] ?? '';

            select.value = headers.has(header) ? header : '';
        });
        document.querySelectorAll('[data-invalid-action]').forEach((select) => {
            const value = (template.invalid_actions || {})[select.dataset.invalidAction];

            if (value && [...select.options].some((option) => option.value === value)) {
                select.value = value;
            }
        });
        refreshSaveControls();
    }

    async function onSaveMap() {
        const name = document.getElementById('sheetflow-template-name')?.value ?? '';

        try {
            run = await request(`${api}/runs/${run.id}/mapping`, {
                method: 'PUT',
                headers: { ...headers, 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    column_map: currentMap(),
                    invalid_actions: currentInvalidActions(),
                    save_template: name !== '',
                    template_name: name,
                }),
            });
            render();
        } catch (error) {
            showError(error);
        }
    }

    async function onValidate() {
        try {
            run = await request(`${api}/runs/${run.id}/validation`, {
                method: 'POST',
                headers: { ...headers, 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    column_map: currentMap(),
                    invalid_actions: currentInvalidActions(),
                }),
            });
            render();
            watch();
        } catch (error) {
            showError(error);
        }
    }

    async function onImport() {
        try {
            run = await request(`${api}/runs/${run.id}/import`, {
                method: 'POST',
                headers,
            });
            render();
            watch();
        } catch (error) {
            showError(error);
        }
    }

    async function onCancel() {
        try {
            run = await request(`${api}/runs/${run.id}/cancel`, {
                method: 'POST',
                headers,
            });
            render();
            watch();
        } catch (error) {
            showError(error);
        }
    }

    async function loadFailures() {
        const payload = await request(`${api}/runs/${run.id}/failures`, { headers });
        const target = document.getElementById('sheetflow-failures');

        if (!target) {
            return;
        }

        const rows = (payload.data || []).map((failure) => {
            const messages = Object.values(failure.errors || {}).flat().join(' ');

            return `<tr><td>${failure.row_number}</td><td>${escapeHtml(messages)}</td></tr>`;
        }).join('');

        target.innerHTML = `<section><h2>${escapeHtml(t('row_errors'))}</h2><table><thead><tr><th>${escapeHtml(t('row'))}</th><th>${escapeHtml(t('error'))}</th></tr></thead><tbody>${rows}</tbody></table></section>`;
    }

    function watch() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }

        if (!run || !['staging', 'validating', 'importing'].includes(run.status)) {
            return;
        }

        timer = setInterval(async () => {
            try {
                run = await request(`${api}/runs/${run.id}`, { headers });
                render();

                if (!['staging', 'validating', 'importing'].includes(run.status)) {
                    clearInterval(timer);
                    timer = null;
                }
            } catch (error) {
                showError(error);
            }
        }, 1000);
    }

    render();
}());
