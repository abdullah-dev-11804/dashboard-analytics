define([], function() {
    return {
        init: function(deps) {
            deps = deps || {};

            var text = deps.text || function(key, fallback) {
                return fallback || key;
            };
            var escapeHtml = deps.escapeHtml || function(value) {
                return String(value == null ? '' : value);
            };
            var formatString = deps.formatString || function(value) {
                return value;
            };
            var call = deps.call;
            var Notification = deps.Notification || window.Notification;
            var M = deps.M || window.M || {cfg: {wwwroot: '', sesskey: ''}};
            var fillSelect = deps.fillSelect || function(select, options, selectedValue) {
                if (!select) {
                    return;
                }
                var selectedValues = Array.isArray(selectedValue)
                    ? selectedValue.map(function(value) {
                        return String(value);
                    })
                    : [String(selectedValue)];
                select.innerHTML = (options || []).map(function(option) {
                    var selected = selectedValues.indexOf(String(option.value)) !== -1 ? ' selected' : '';
                    return '<option value="' + escapeHtml(option.value) + '"' + selected + '>' + escapeHtml(option.label) + '</option>';
                }).join('');
            };
            var setLoading = deps.setLoading || function(node) {
                if (node) {
                    node.innerHTML = '<div class="da-empty">' + escapeHtml(text('loading', 'Loading...')) + '</div>';
                }
            };
            var buildDrilldownTableResultsMarkup = deps.buildDrilldownTableResultsMarkup || function() {
                return {results: ''};
            };
            var rememberCurrentState = deps.rememberCurrentState || function() {};
            var readDashboardFilters = deps.readFilters || function() {
                return {};
            };

            var reportBuilderRoot = function(root) {
                return root.querySelector('[data-region="report-builder"]');
            };

            var reportBuilderConfigTemplates = function(state) {
                return (((state || {}).currentReportBuilderConfig) || {}).templates || [];
            };

            var reportBuilderTemplateById = function(state, templateid) {
                var id = String(templateid || 0);
                var templates = reportBuilderConfigTemplates(state);
                for (var i = 0; i < templates.length; i++) {
                    if (String((templates[i] || {}).id || 0) === id) {
                        return templates[i];
                    }
                }
                return null;
            };

            var reportBuilderField = function(root, name) {
                var panel = reportBuilderRoot(root);
                return panel ? panel.querySelector('[data-report-field="' + name + '"]') : null;
            };

            var reportBuilderSelectValues = function(field, values) {
                if (!field) {
                    return;
                }

                var selected = Array.isArray(values) ? values.map(function(value) {
                    return String(value);
                }) : [String(values)];
                Array.prototype.slice.call(field.options || []).forEach(function(option) {
                    option.selected = selected.indexOf(String(option.value)) !== -1;
                });
            };

            var reportBuilderSelectedValues = function(field) {
                if (!field) {
                    return [];
                }

                return Array.prototype.slice.call(field.options || []).filter(function(option) {
                    return option.selected;
                }).map(function(option) {
                    return option.value;
                });
            };

            var reportBuilderMultiSelectOptions = function(name, options, selectedValues) {
                var selected = Array.isArray(selectedValues) ? selectedValues.map(function(value) {
                    return String(value);
                }) : [String(selectedValues || '')];

                return (options || []).map(function(option) {
                    var value = String(option.value);
                    var checked = selected.indexOf(value) !== -1 ? ' checked' : '';
                    return '<label class="da-report-builder-multi-option" data-action="report-builder-multi-option" data-report-multi="' + escapeHtml(name) + '" data-report-multi-value="' + escapeHtml(value) + '">'
                        + '<input type="checkbox" data-report-multi="' + escapeHtml(name) + '" value="' + escapeHtml(value) + '"' + checked + '>'
                        + '<span>' + escapeHtml(option.label || '') + '</span>'
                        + '</label>';
                }).join('');
            };

            var reportBuilderMultiSelectMarkup = function(name, label, options, selectedValues) {
                var selected = Array.isArray(selectedValues) ? selectedValues.map(function(value) {
                    return String(value);
                }) : [String(selectedValues || '')];
                var optionMarkup = reportBuilderMultiSelectOptions(name, options, selected);
                var selectOptions = (options || []).map(function(option) {
                    var value = String(option.value);
                    var selectedAttr = selected.indexOf(value) !== -1 ? ' selected' : '';
                    return '<option value="' + escapeHtml(value) + '"' + selectedAttr + '>'
                        + escapeHtml(option.label || '') + '</option>';
                }).join('');

                return '<div class="da-report-builder-multi-control">'
                    + '<span>' + escapeHtml(label) + '</span>'
                    + '<select class="da-report-builder-hidden-select" multiple data-report-field="' + escapeHtml(name) + '">' + selectOptions + '</select>'
                    + '<div class="da-report-builder-multiselect" data-report-multiselect="' + escapeHtml(name) + '">'
                    + '<button type="button" class="da-report-builder-multiselect-toggle" data-action="report-builder-multiselect-toggle" data-report-multi="' + escapeHtml(name) + '">'
                    + '<span data-region="report-builder-multiselect-label" data-report-multi="' + escapeHtml(name) + '"></span>'
                    + '<span class="da-report-builder-multiselect-caret">▾</span>'
                    + '</button>'
                    + '<div class="da-report-builder-multiselect-panel">'
                    + '<div class="da-report-builder-multiselect-tools">'
                    + '<button type="button" class="da-row-action da-mini-button" data-action="report-builder-multi-all" data-report-multi="' + escapeHtml(name) + '">' + escapeHtml(text('allOption', 'All')) + '</button>'
                    + '<button type="button" class="da-row-action da-mini-button" data-action="report-builder-multi-clear" data-report-multi="' + escapeHtml(name) + '">' + escapeHtml(text('clearState', 'Clear')) + '</button>'
                    + '</div>'
                    + '<div class="da-report-builder-multiselect-options">' + optionMarkup + '</div>'
                    + '</div>'
                    + '</div>'
                    + '</div>';
            };

            var reportBuilderSyncMultiSelectLabels = function(root) {
                var panel = reportBuilderRoot(root);
                if (!panel) {
                    return;
                }

                Array.prototype.slice.call(panel.querySelectorAll('[data-report-multiselect]')).forEach(function(wrapper) {
                    var name = wrapper.getAttribute('data-report-multiselect') || '';
                    var field = reportBuilderField(root, name);
                    var selectedOptions = field ? Array.prototype.slice.call(field.options || []).filter(function(option) {
                        return option.selected;
                    }) : [];
                    var selectedValues = selectedOptions.map(function(option) {
                        return String(option.value);
                    });
                    Array.prototype.slice.call(wrapper.querySelectorAll('[data-action="report-builder-multi-option"] input[type="checkbox"]')).forEach(function(input) {
                        input.checked = selectedValues.indexOf(String(input.value)) !== -1;
                    });

                    var label = wrapper.querySelector('[data-region="report-builder-multiselect-label"]');
                    if (label) {
                        label.textContent = selectedOptions.length
                            ? selectedOptions.map(function(option) {
                                return option.textContent || option.value;
                            }).join(', ')
                            : text('allOption', 'All');
                    }
                });
            };

            var reportBuilderRefreshMultiSelect = function(root, name, options, selectedValues) {
                var panel = reportBuilderRoot(root);
                var field = reportBuilderField(root, name);
                if (!panel || !field) {
                    return;
                }

                field.innerHTML = (options || []).map(function(option) {
                    var value = String(option.value);
                    var selected = Array.isArray(selectedValues) ? selectedValues.map(String).indexOf(value) !== -1 : String(selectedValues || '') === value;
                    return '<option value="' + escapeHtml(value) + '"' + (selected ? ' selected' : '') + '>'
                        + escapeHtml(option.label || '') + '</option>';
                }).join('');

                var wrapper = panel.querySelector('[data-report-multiselect="' + escapeHtml(name) + '"]');
                if (!wrapper) {
                    return;
                }

                var optionsRegion = wrapper.querySelector('.da-report-builder-multiselect-options');
                if (optionsRegion) {
                    optionsRegion.innerHTML = reportBuilderMultiSelectOptions(name, options, selectedValues);
                }
                reportBuilderSyncMultiSelectLabels(root);
            };

            var reportBuilderSetMultiSelectValues = function(root, name, values) {
                var field = reportBuilderField(root, name);
                reportBuilderSelectValues(field, values);
                reportBuilderSyncMultiSelectLabels(root);
            };

            var reportBuilderPromptTemplateName = function(defaultName) {
                return new Promise(function(resolve) {
                    var overlay = document.createElement('div');
                    overlay.className = 'da-report-builder-modal-backdrop';
                    overlay.innerHTML = '<div class="da-report-builder-modal" role="dialog" aria-modal="true">'
                        + '<h5>' + escapeHtml(text('reportBuilderTemplateName', 'Template name')) + '</h5>'
                        + '<input type="text" maxlength="80" value="' + escapeHtml(defaultName || '') + '">'
                        + '<div class="da-report-builder-modal-actions">'
                        + '<button type="button" class="da-row-action" data-action="report-builder-modal-cancel">' + escapeHtml(text('cancel', 'Cancel')) + '</button>'
                        + '<button type="button" class="da-row-action da-primary-action" data-action="report-builder-modal-save">' + escapeHtml(text('reportBuilderSaveTemplate', 'Save template')) + '</button>'
                        + '</div>'
                        + '</div>';

                    var finish = function(value) {
                        if (overlay.parentNode) {
                            overlay.parentNode.removeChild(overlay);
                        }
                        resolve(value);
                    };
                    var input = overlay.querySelector('input');
                    overlay.addEventListener('click', function(event) {
                        if (event.target === overlay || event.target.closest('[data-action="report-builder-modal-cancel"]')) {
                            finish(null);
                            return;
                        }
                        if (event.target.closest('[data-action="report-builder-modal-save"]')) {
                            var name = input ? String(input.value || '').trim() : '';
                            finish(name || null);
                        }
                    });
                    overlay.addEventListener('keydown', function(event) {
                        if (event.key === 'Escape') {
                            finish(null);
                            return;
                        }
                        if (event.key === 'Enter') {
                            var name = input ? String(input.value || '').trim() : '';
                            finish(name || null);
                        }
                    });

                    document.body.appendChild(overlay);
                    if (input) {
                        input.focus();
                        input.select();
                    }
                });
            };

            var reportBuilderState = function(state) {
                return (state.currentVisualOverrides || {}).reportbuilder || {};
            };

            var reportBuilderColumnDefinitions = function(state) {
                return (((state || {}).currentReportBuilderConfig) || {}).columns || [];
            };

            var reportBuilderColumnDefinitionByKey = function(state, key) {
                var columns = reportBuilderColumnDefinitions(state);
                for (var i = 0; i < columns.length; i++) {
                    if ((columns[i] || {}).key === key) {
                        return columns[i];
                    }
                }
                return null;
            };

            var setReportBuilderState = function(state, updates) {
                state.currentVisualOverrides = state.currentVisualOverrides || {};
                state.currentVisualOverrides.reportbuilder = Object.assign({}, reportBuilderState(state), updates || {});
            };

            var reportBuilderSelectedColumns = function(state) {
                var selected = reportBuilderState(state).columns || [];
                return Array.isArray(selected) ? selected.map(function(column) {
                    return String(column || '');
                }).filter(function(column) {
                    return !!column;
                }) : [];
            };

            var reportBuilderSetSelectedColumns = function(root, state, columns) {
                var selected = Array.isArray(columns) ? columns.map(function(column) {
                    return String(column || '');
                }).filter(function(column) {
                    return !!column;
                }) : [];
                setReportBuilderState(state, Object.assign({}, reportBuilderState(state), {
                    columns: selected,
                    page: 0
                }));
                reportBuilderRenderColumnsPicker(root, state);
            };

            var reportBuilderMoveSelectedColumn = function(root, state, column, direction) {
                var selected = reportBuilderSelectedColumns(state);
                var index = selected.indexOf(String(column || ''));
                if (index === -1) {
                    return;
                }

                var target = index + direction;
                if (target < 0 || target >= selected.length) {
                    return;
                }

                var moved = selected.slice();
                moved.splice(target, 0, moved.splice(index, 1)[0]);
                reportBuilderSetSelectedColumns(root, state, moved);
            };

            var reportBuilderAddSelectedColumn = function(root, state, column) {
                var selected = reportBuilderSelectedColumns(state);
                var key = String(column || '');
                if (!key || selected.indexOf(key) !== -1) {
                    return;
                }

                selected.push(key);
                reportBuilderSetSelectedColumns(root, state, selected);
            };

            var reportBuilderRemoveSelectedColumn = function(root, state, column) {
                var key = String(column || '');
                var selected = reportBuilderSelectedColumns(state).filter(function(item) {
                    return item !== key;
                });
                reportBuilderSetSelectedColumns(root, state, selected);
            };

            var reportBuilderRenderColumnsPicker = function(root, state) {
                var panel = reportBuilderRoot(root);
                if (!panel) {
                    return;
                }

                var selected = reportBuilderSelectedColumns(state);
                var columns = reportBuilderColumnDefinitions(state);
                var searchField = panel.querySelector('[data-report-field="columnsearch"]');
                var search = searchField ? String(searchField.value || '').trim().toLowerCase() : '';
                var selectedHtml = selected.map(function(column, index) {
                    var definition = reportBuilderColumnDefinitionByKey(state, column) || {label: column};
                    return '<div class="da-report-builder-field is-selected" data-report-column-item="' + escapeHtml(column) + '" draggable="true">'
                        + '<span class="da-report-builder-field-handle">⋮⋮</span>'
                        + '<span class="da-report-builder-field-index">' + escapeHtml(String(index + 1)) + '</span>'
                        + '<span class="da-report-builder-field-label">' + escapeHtml(definition.label || column) + '</span>'
                        + '<div class="da-report-builder-field-actions">'
                        + '<button type="button" class="da-row-action da-mini-button" data-action="report-builder-column-up" data-report-column="' + escapeHtml(column) + '"' + (index <= 0 ? ' disabled' : '') + '>↑</button>'
                        + '<button type="button" class="da-row-action da-mini-button" data-action="report-builder-column-down" data-report-column="' + escapeHtml(column) + '"' + (index >= selected.length - 1 ? ' disabled' : '') + '>↓</button>'
                        + '<button type="button" class="da-row-action da-mini-button" data-action="report-builder-column-remove" data-report-column="' + escapeHtml(column) + '">×</button>'
                        + '</div>'
                        + '</div>';
                }).join('');
                var availableHtml = columns.filter(function(column) {
                    return selected.indexOf(column.key) === -1;
                }).filter(function(column) {
                    if (!search) {
                        return true;
                    }
                    return String(column.label || '').toLowerCase().indexOf(search) !== -1
                        || String(column.key || '').toLowerCase().indexOf(search) !== -1;
                }).map(function(column) {
                    return '<button type="button" class="da-report-builder-field is-available" data-action="report-builder-column-add" data-report-column="' + escapeHtml(column.key) + '">'
                        + '<span class="da-report-builder-field-label">' + escapeHtml(column.label || column.key) + '</span>'
                        + '<span class="da-report-builder-field-plus">+</span>'
                        + '</button>';
                }).join('');

                var selectedRegion = panel.querySelector('[data-region="report-builder-selected"]');
                if (selectedRegion) {
                    selectedRegion.innerHTML = selectedHtml || '<div class="da-empty">' + escapeHtml(text('reportBuilderNoColumns', 'Select at least one field.')) + '</div>';
                }

                var availableRegion = panel.querySelector('[data-region="report-builder-available"]');
                if (availableRegion) {
                    availableRegion.innerHTML = availableHtml || '<div class="da-empty">' + escapeHtml(text('reportBuilderNoAvailableFields', 'No more fields available.')) + '</div>';
                }

                var selectedCount = panel.querySelector('[data-region="report-builder-selected-count"]');
                if (selectedCount) {
                    selectedCount.textContent = String(selected.length);
                }
                var availableCount = panel.querySelector('[data-region="report-builder-available-count"]');
                if (availableCount) {
                    availableCount.textContent = String(Math.max(0, columns.length - selected.length));
                }
            };

            var reportBuilderSetColumns = reportBuilderSetSelectedColumns;

            var reportBuilderSyncPeriodUi = function(root) {
                var panel = reportBuilderRoot(root);
                if (!panel) {
                    return;
                }

                var periodmode = reportBuilderField(root, 'periodmode');
                var isCustom = periodmode && periodmode.value === 'custom';
                Array.prototype.slice.call(panel.querySelectorAll('[data-report-field="month"], [data-report-field="year"]')).forEach(function(field) {
                    var label = field.closest('[data-report-period-control]') || field.closest('label');
                    if (label) {
                        label.hidden = isCustom;
                        label.style.display = isCustom ? 'none' : '';
                    }
                });
                Array.prototype.slice.call(panel.querySelectorAll('[data-report-field="customstart"], [data-report-field="customend"]')).forEach(function(field) {
                    var label = field.closest('label');
                    if (label) {
                        label.hidden = !isCustom;
                        label.style.display = isCustom ? '' : 'none';
                    }
                });
            };

            var reportBuilderApplyTemplate = function(root, state, templateid) {
                var template = reportBuilderTemplateById(state, templateid);
                var config = state.currentReportBuilderConfig || {};
                var current = reportBuilderState(state);
                var builder = Object.assign({}, current, {
                    templateid: Number(templateid) || 0
                });

                if (template) {
                    builder.templatename = template.name || builder.templatename || config.defaulttemplate || text('reportBuilderUntitled', 'Untitled report');
                    builder.columns = Array.isArray(template.columns) && template.columns.length ? template.columns.slice() : (config.defaultcolumns || []);
                    var filters = template.filters || {};
                    builder.companyid = Number(filters.companyid || builder.companyid || 0) || 0;
                    builder.periodmode = filters.periodmode || builder.periodmode || 'month';
                    builder.months = Array.isArray(filters.months) && filters.months.length
                        ? filters.months.map(Number)
                        : [Number(filters.month || builder.month || config.defaultmonth || (new Date().getMonth() + 1)) || 0];
                    builder.years = Array.isArray(filters.years) && filters.years.length
                        ? filters.years.map(Number)
                        : [Number(filters.year || builder.year || config.defaultyear || (new Date().getFullYear())) || 0];
                    builder.month = Number(builder.months[0] || config.defaultmonth || (new Date().getMonth() + 1)) || 0;
                    builder.year = Number(builder.years[0] || config.defaultyear || (new Date().getFullYear())) || 0;
                    builder.customstart = filters.customstart || builder.customstart || '';
                    builder.customend = filters.customend || builder.customend || '';
                    builder.search = filters.search || builder.search || '';
                    builder.sortkey = filters.sortkey || builder.sortkey || 'completiondate';
                    builder.sortdir = filters.sortdir || builder.sortdir || 'asc';
                    builder.page = Number(filters.page || builder.page || 0) || 0;
                    builder.perpage = Number(filters.perpage || builder.perpage || 20) || 20;
                } else {
                    builder.templatename = builder.templatename || config.defaulttemplate || text('reportBuilderUntitled', 'Untitled report');
                    builder.columns = builder.columns || config.defaultcolumns || [];
                    builder.months = Array.isArray(builder.months) && builder.months.length ? builder.months : [builder.month || config.defaultmonth || (new Date().getMonth() + 1)];
                    builder.years = Array.isArray(builder.years) && builder.years.length ? builder.years : [builder.year || config.defaultyear || (new Date().getFullYear())];
                }

                state.currentVisualOverrides = state.currentVisualOverrides || {};
                state.currentVisualOverrides.reportbuilder = builder;

                var templateField = reportBuilderField(root, 'templateid');
                if (templateField) {
                    templateField.value = String(builder.templateid || 0);
                }
                var templateName = reportBuilderField(root, 'templatename');
                if (templateName) {
                    templateName.value = builder.templatename || '';
                }
                var companyField = reportBuilderField(root, 'companyid');
                if (companyField) {
                    companyField.value = String(builder.companyid || '');
                }
                var periodField = reportBuilderField(root, 'periodmode');
                if (periodField) {
                    periodField.value = builder.periodmode || 'month';
                }
                var monthField = reportBuilderField(root, 'month');
                if (monthField) {
                    reportBuilderSetMultiSelectValues(root, 'month', builder.months || [builder.month || '']);
                }
                var yearField = reportBuilderField(root, 'year');
                if (yearField) {
                    reportBuilderSetMultiSelectValues(root, 'year', builder.years || [builder.year || '']);
                }
                var customStartField = reportBuilderField(root, 'customstart');
                if (customStartField) {
                    customStartField.value = builder.customstart || '';
                }
                var customEndField = reportBuilderField(root, 'customend');
                if (customEndField) {
                    customEndField.value = builder.customend || '';
                }
                var searchField = reportBuilderField(root, 'search');
                if (searchField) {
                    searchField.value = builder.search || '';
                }
                reportBuilderSetColumns(root, state, builder.columns || []);
                reportBuilderSyncPeriodUi(root);
                return api.loadRows(root, state, 'replace');
            };

            var reportBuilderRenderTemplateList = function(root, state, selectedId) {
                var panel = reportBuilderRoot(root);
                if (!panel) {
                    return;
                }

                var templates = reportBuilderConfigTemplates(state);
                var selected = String(selectedId || reportBuilderState(state).templateid || 0);
                var region = panel.querySelector('[data-region="report-builder-templates"]');
                if (!region) {
                    return;
                }

                if (!templates.length) {
                    region.innerHTML = '<div class="da-empty">' + escapeHtml(text('reportBuilderNoTemplates', 'No saved templates.')) + '</div>';
                    return;
                }

                region.innerHTML = templates.map(function(template) {
                    var templateid = String(template.id || 0);
                    var active = templateid === selected ? ' is-active' : '';
                    var columns = Array.isArray(template.columns) ? template.columns.length : 0;
                    var templatefilters = template.filters || {};
                    var periodlabel = templatefilters.periodmode === 'custom'
                        ? text('reportBuilderPeriodCustom', 'Custom range')
                        : text('reportBuilderPeriodMonth', 'Month / Year');
                    return '<article class="da-report-builder-template-card' + active + '" data-template-id="' + escapeHtml(templateid) + '">'
                        + '<button type="button" class="da-report-builder-template-card-main" data-action="report-builder-template-select" data-template-id="' + escapeHtml(templateid) + '">'
                        + '<span class="da-report-builder-template-card-name">' + escapeHtml(template.name || text('reportBuilderUntitled', 'Untitled report')) + '</span>'
                        + '<span class="da-report-builder-template-card-meta">' + escapeHtml(String(columns)) + ' · ' + escapeHtml(periodlabel) + '</span>'
                        + '</button>'
                        + '<div class="da-report-builder-template-card-actions">'
                        + '<button type="button" class="da-row-action da-mini-button" data-action="report-builder-template-select" data-template-id="' + escapeHtml(templateid) + '">' + escapeHtml(text('reportBuilderLoad', 'Load report')) + '</button>'
                        + '<button type="button" class="da-row-action da-mini-button" data-action="report-builder-delete-template" data-template-id="' + escapeHtml(templateid) + '">' + escapeHtml(text('reportBuilderDeleteTemplate', 'Delete template')) + '</button>'
                        + '</div>'
                        + '</article>';
                }).join('');
            };

            var reportBuilderReadForm = function(root, state) {
                var valueOf = function(name) {
                    var field = reportBuilderField(root, name);
                    return field ? field.value : '';
                };

                var columns = reportBuilderSelectedColumns(state);
                var months = reportBuilderSelectedValues(reportBuilderField(root, 'month')).map(Number).filter(function(value) {
                    return value >= 1 && value <= 12;
                });
                var years = reportBuilderSelectedValues(reportBuilderField(root, 'year')).map(Number).filter(function(value) {
                    return value >= 2000 && value <= 2100;
                });
                return {
                    templateid: Number(valueOf('templateid')) || 0,
                    templatename: valueOf('templatename'),
                    companyid: Number(valueOf('companyid')) || 0,
                    periodmode: valueOf('periodmode') || 'month',
                    month: months[0] || Number(valueOf('month')) || 0,
                    year: years[0] || Number(valueOf('year')) || 0,
                    months: months,
                    years: years,
                    customstart: valueOf('customstart'),
                    customend: valueOf('customend'),
                    search: valueOf('search'),
                    columns: columns.length ? columns : ['lastname', 'firstname', 'course', 'status', 'completiondate']
                };
            };

            var reportBuilderFilters = function(root, state, form) {
                var filters = Object.assign({}, readDashboardFilters(root, state) || {});
                filters.companyids = form.companyid ? [form.companyid] : (filters.companyids || []);
                filters.status = '';
                filters.search = '';
                return filters;
            };

            var reportBuilderExportUrl = function(root, state, scope, format, page, perpage, sortkey, sortdir, form) {
                var params = new URLSearchParams();
                params.set('contextid', String(state.contextid));
                params.set('dashboardkey', String(state.dashboardkey || ''));
                params.set('reportbuilder', '1');
                params.set('filters', JSON.stringify(reportBuilderFilters(root, state, form)));
                params.set('options', JSON.stringify({
                    columns: form.columns || [],
                    search: form.search || '',
                    templatename: form.templatename || '',
                    periodmode: form.periodmode || 'month',
                    months: form.periodmode === 'custom' ? [] : (form.months && form.months.length ? form.months : [form.month || new Date().getMonth() + 1]),
                    years: form.periodmode === 'custom' ? [] : (form.years && form.years.length ? form.years : [form.year || new Date().getFullYear()]),
                    customstart: form.customstart || '',
                    customend: form.customend || ''
                }));
                params.set('format', String(format || 'xlsx'));
                params.set('scope', scope === 'all' ? 'all' : 'visible');
                params.set('page', String(Math.max(0, Number(page) || 0)));
                params.set('perpage', String(Math.max(10, Number(perpage) || 20)));
                params.set('sortkey', String(sortkey || 'completiondate'));
                params.set('sortdir', String(sortdir || 'asc'));
                params.set('sesskey', M.cfg.sesskey);
                return M.cfg.wwwroot + '/blocks/dashboardanalytics/export.php?' + params.toString();
            };

            var renderReportBuilderPanel = function(state) {
                var builder = reportBuilderState(state);
                var config = state.currentReportBuilderConfig || {};
                var companyvisible = !!config.companyselector;
                var companyOptions = (config.companies || []).map(function(option) {
                    return '<option value="' + escapeHtml(String(option.value)) + '">' + escapeHtml(option.label || '') + '</option>';
                }).join('');
                var previewTitle = text('reportBuilderPreview', 'Preview');
                var downloadXlsxLabel = text('reportBuilderDownloadXlsx', 'Download XLSX');
                var downloadZipLabel = text('reportBuilderDownloadZip', 'Download documents (ZIP)');
                var selectedColumns = reportBuilderSelectedColumns(state);
                var initialMonthYear = builder.periodmode === 'custom' ? ' hidden' : '';
                var initialCustomFields = builder.periodmode === 'custom' ? '' : ' hidden';
                var selectedMonths = Array.isArray(builder.months) && builder.months.length
                    ? builder.months
                    : [builder.month || config.defaultmonth || (new Date().getMonth() + 1)];
                var selectedYears = Array.isArray(builder.years) && builder.years.length
                    ? builder.years
                    : [builder.year || config.defaultyear || (new Date().getFullYear())];

                return '<div class="da-report-builder" data-region="report-builder">'
                    + '<section class="da-report-builder-section">'
                    + '<div class="da-report-builder-section-head">'
                    + '<h5>' + escapeHtml(text('reportBuilderTemplates', 'Saved templates')) + '</h5>'
                    + '</div>'
                    + '<div class="da-report-builder-templates" data-region="report-builder-templates"></div>'
                    + '</section>'
                    + '<section class="da-report-builder-section">'
                    + '<div class="da-report-builder-section-head">'
                    + '<h5>' + escapeHtml(text('reportBuilderPanelTitle', 'Report builder')) + '</h5>'
                    + '<div class="da-report-builder-template-actions">'
                    + '<button type="button" class="da-row-action" data-action="report-builder-save-template">' + escapeHtml(text('reportBuilderSaveTemplate', 'Save template')) + '</button>'
                    + '<button type="button" class="da-row-action" data-action="report-builder-save-as-template">' + escapeHtml(text('reportBuilderSaveAsTemplate', 'Save as new')) + '</button>'
                    + '</div>'
                    + '</div>'
                    + '<input type="hidden" data-report-field="templateid" value="' + escapeHtml(String(builder.templateid || 0)) + '">'
                    + '<input type="hidden" data-report-field="templatename" value="' + escapeHtml(String(builder.templatename || config.defaulttemplate || text('reportBuilderUntitled', 'Untitled report'))) + '">'
                    + '<div class="da-report-builder-controls">'
                    + (companyvisible ? '<label><span>' + escapeHtml(text('companyHeader', 'Company')) + '</span><select data-report-field="companyid">' + companyOptions + '</select></label>' : '')
                    + '<label><span>' + escapeHtml(text('forecastPeriodLabel', 'Period')) + '</span><select data-report-field="periodmode">'
                    + '<option value="month">' + escapeHtml(text('reportBuilderPeriodMonth', 'Month / Year')) + '</option>'
                    + '<option value="custom">' + escapeHtml(text('reportBuilderPeriodCustom', 'Custom range')) + '</option>'
                    + '</select></label>'
                    + '<label' + initialCustomFields + '><span>' + escapeHtml(text('forecastCustomStart', 'Start date')) + '</span><input type="date" data-report-field="customstart"></label>'
                    + '<label' + initialCustomFields + '><span>' + escapeHtml(text('forecastCustomEnd', 'End date')) + '</span><input type="date" data-report-field="customend"></label>'
                    + '<div' + initialMonthYear + ' data-report-period-control="month">' + reportBuilderMultiSelectMarkup('month', text('reportBuilderMonthLabel', 'Month'), config.months || [], selectedMonths) + '</div>'
                    + '<div' + initialMonthYear + ' data-report-period-control="year">' + reportBuilderMultiSelectMarkup('year', text('reportBuilderYearLabel', 'Year'), config.years || [], selectedYears) + '</div>'
                    + '<label class="da-report-builder-search"><span>' + escapeHtml(text('reportBuilderSearch', 'Search records')) + '</span><input type="search" data-report-field="search" placeholder="' + escapeHtml(formatString(text('searchPlaceholder', 'Search {$a}'), 'records')) + '"></label>'
                    + '</div>'
                    + '<div class="da-report-builder-fields">'
                    + '<div class="da-report-builder-fields-pane">'
                    + '<div class="da-report-builder-fields-head"><span>' + escapeHtml(text('reportBuilderColumns', 'Selected fields')) + '</span><b data-region="report-builder-selected-count">' + escapeHtml(String(selectedColumns.length)) + '</b></div>'
                    + '<div class="da-report-builder-fields-body" data-region="report-builder-selected"></div>'
                    + '</div>'
                    + '<div class="da-report-builder-fields-pane">'
                    + '<div class="da-report-builder-fields-head"><span>' + escapeHtml(text('reportBuilderAvailableFields', 'Available fields')) + '</span><b data-region="report-builder-available-count">' + escapeHtml(String(Math.max(0, (((config.columns || []).length) - selectedColumns.length))) ) + '</b></div>'
                    + '<label class="da-report-builder-field-search"><span class="da-sr-only">' + escapeHtml(text('reportBuilderFieldSearch', 'Search field')) + '</span><input type="search" data-report-field="columnsearch" placeholder="' + escapeHtml(text('reportBuilderFieldSearch', 'Search field')) + '"></label>'
                    + '<div class="da-report-builder-fields-body" data-region="report-builder-available"></div>'
                    + '</div>'
                    + '</div>'
                    + '<div class="da-report-builder-summary" data-region="report-builder-summary"></div>'
                    + '<div class="da-report-builder-card-head">'
                    + '<h5>' + escapeHtml(previewTitle) + '</h5>'
                    + '<div class="da-report-builder-card-actions">'
                    + '<button type="button" class="da-row-action" data-action="report-builder-load">' + escapeHtml(text('reportBuilderLoad', 'Load report')) + '</button>'
                    + '<a class="da-row-action" data-report-download="xlsx" href="#">' + escapeHtml(downloadXlsxLabel) + '</a>'
                    + '<a class="da-row-action" data-report-download="zip" href="#">' + escapeHtml(downloadZipLabel) + '</a>'
                    + '</div>'
                    + '</div>'
                    + '<div class="da-report-builder-results" data-region="report-builder-results">'
                    + '<div class="da-empty">' + escapeHtml(text('reportBuilderNoResults', 'Select company and period, then load the report.')) + '</div>'
                    + '</div>'
                    + '</section>'
                    + '</div>';
            };

            var renderReportBuilderSummary = function(root, summary) {
                var panel = reportBuilderRoot(root);
                if (!panel) {
                    return;
                }

                var region = panel.querySelector('[data-region="report-builder-summary"]');
                if (!region) {
                    return;
                }

                region.innerHTML = [
                    {label: text('reportBuilderRecords', 'Records'), value: summary.total || 0},
                    {label: text('reportBuilderOnline', 'Online'), value: summary.online || 0},
                    {label: text('reportBuilderOffline', 'Offline'), value: summary.offline || 0},
                    {label: text('reportBuilderActive', 'Active'), value: summary.active || 0}
                ].map(function(item) {
                    return '<article class="da-report-builder-summary-card"><span>' + escapeHtml(item.label) + '</span><strong>' + escapeHtml(String(item.value)) + '</strong></article>';
                }).join('');
            };

            var renderReportBuilderResults = function(root, state, response) {
                var panel = reportBuilderRoot(root);
                if (!panel) {
                    return;
                }

                var results = panel.querySelector('[data-region="report-builder-results"]');
                if (!results) {
                    return;
                }

                var form = reportBuilderState(state);
                var currentPage = Math.max(0, Number(response.page) || 0);
                var perpage = Math.max(10, Number(response.perpage) || 20);
                response.exporturl = reportBuilderExportUrl(root, state, 'all', 'xlsx', currentPage, perpage, form.sortkey || 'completiondate', form.sortdir || 'asc', form);
                response.exportallurl = reportBuilderExportUrl(root, state, 'all', 'zip', currentPage, perpage, form.sortkey || 'completiondate', form.sortdir || 'asc', form);
                response.sortableallcolumns = true;
                response.description = response.description || '';
                results.innerHTML = buildDrilldownTableResultsMarkup(root, response, state, {
                    page: currentPage,
                    perpage: perpage,
                    drilldownkey: 'reportbuilder',
                    overrides: {
                        search: form.search || '',
                        sortkey: form.sortkey || 'completiondate',
                        sortdir: form.sortdir || 'asc'
                    },
                    exportlabel: text('reportBuilderDownloadXlsx', 'Download XLSX'),
                    exportalllabel: text('reportBuilderDownloadZip', 'Download documents (ZIP)'),
                    actionPrefix: 'report-builder',
                    sortableallcolumns: true,
                    hideexports: true
                }).results;
                var xlsxButton = panel.querySelector('[data-report-download="xlsx"]');
                if (xlsxButton) {
                    xlsxButton.href = response.exporturl;
                }
                var zipButton = panel.querySelector('[data-report-download="zip"]');
                if (zipButton) {
                    zipButton.href = response.exportallurl;
                }
                renderReportBuilderSummary(root, response.summary || {});
            };

            var loadReportBuilderConfig = function(root, state) {
                var panel = reportBuilderRoot(root);
                if (!panel || panel.getAttribute('data-config-loaded') === '1') {
                    return;
                }

                panel.setAttribute('data-config-loaded', '1');

                return call('block_dashboardanalytics_get_report_builder_config', {
                    contextid: state.contextid
                }).then(function(response) {
                    var config = {};
                    try {
                        config = JSON.parse(response.json || '{}') || {};
                    } catch (e) {
                        config = {};
                    }
                    state.currentReportBuilderConfig = config;

                    var builder = reportBuilderState(state);
                    if (!builder.companyid && (config.companies || []).length) {
                        builder.companyid = Number((config.companies[0] || {}).value) || 0;
                    }
                    builder.periodmode = builder.periodmode || 'month';
                    builder.month = builder.month || Number(config.defaultmonth) || (new Date().getMonth() + 1);
                    builder.year = builder.year || Number(config.defaultyear) || (new Date().getFullYear());
                    builder.months = Array.isArray(builder.months) && builder.months.length ? builder.months : [builder.month];
                    builder.years = Array.isArray(builder.years) && builder.years.length ? builder.years : [builder.year];
                    builder.columns = Array.isArray(builder.columns) && builder.columns.length ? builder.columns : (config.defaultcolumns || []);
                    builder.templatename = builder.templatename || config.defaulttemplate || text('reportBuilderUntitled', 'Untitled report');
                    state.currentVisualOverrides = state.currentVisualOverrides || {};
                    state.currentVisualOverrides.reportbuilder = builder;

                    fillSelect(reportBuilderField(root, 'companyid'), config.companyselector ? (config.companies || []) : [], String(builder.companyid || ''));
                    reportBuilderRefreshMultiSelect(root, 'month', config.months || [], builder.months);
                    reportBuilderRefreshMultiSelect(root, 'year', config.years || [], builder.years);

                    var periodmode = reportBuilderField(root, 'periodmode');
                    if (periodmode) {
                        periodmode.value = builder.periodmode || 'month';
                    }

                    var templateName = reportBuilderField(root, 'templatename');
                    if (templateName && !templateName.value) {
                        templateName.value = builder.templatename || config.defaulttemplate || text('reportBuilderUntitled', 'Untitled report');
                    }

                    reportBuilderRenderTemplateList(root, state, builder.templateid);
                    reportBuilderRenderColumnsPicker(root, state);
                    reportBuilderSyncPeriodUi(root);
                    return api.loadRows(root, state, 'replace');
                }).catch(Notification.exception);
            };

            var loadReportBuilderRows = function(root, state, renderMode) {
                var panel = reportBuilderRoot(root);
                if (!panel) {
                    return Promise.resolve();
                }

                var form = reportBuilderReadForm(root, state);
                var builder = Object.assign({}, reportBuilderState(state), form);
                builder.page = Number(builder.page || 0) || 0;
                builder.perpage = Number(builder.perpage || 20) || 20;
                setReportBuilderState(state, builder);

                var responseTarget = panel.querySelector('[data-region="report-builder-results"]');
                if (responseTarget) {
                    setLoading(responseTarget);
                }

                var monthValues = builder.periodmode === 'custom' ? [] : (Array.isArray(builder.months) && builder.months.length ? builder.months : [builder.month]);
                var yearValues = builder.periodmode === 'custom' ? [] : (Array.isArray(builder.years) && builder.years.length ? builder.years : [builder.year]);
                var filters = reportBuilderFilters(root, state, builder);

                return call('block_dashboardanalytics_get_report_builder_rows', {
                    contextid: state.contextid,
                    dashboardkey: state.dashboardkey,
                    filters: JSON.stringify(filters),
                    options: JSON.stringify({
                        columns: builder.columns,
                        search: builder.search,
                        periodmode: builder.periodmode,
                        months: monthValues,
                        years: yearValues,
                        customstart: builder.customstart,
                        customend: builder.customend
                    }),
                    page: builder.page,
                    perpage: builder.perpage,
                    sortkey: builder.sortkey || 'completiondate',
                    sortdir: builder.sortdir || 'asc'
                }).then(function(response) {
                    var payload = {};
                    try {
                        payload = JSON.parse(response.json || '{}') || {};
                    } catch (e) {
                        payload = {};
                    }
                    state.currentReportBuilderRows = payload;
                    renderReportBuilderResults(root, state, payload);
                }).catch(Notification.exception);
            };

            var clearReportBuilderForm = function(root, state) {
                var panel = reportBuilderRoot(root);
                if (!panel) {
                    return;
                }

                var defaults = state.currentReportBuilderConfig || {};
                var builder = {
                    templateid: 0,
                    templatename: defaults.defaulttemplate || text('reportBuilderUntitled', 'Untitled report'),
                    companyid: Number(((defaults.companies || [])[0] || {}).value) || 0,
                    periodmode: 'month',
                    month: Number(defaults.defaultmonth) || (new Date().getMonth() + 1),
                    year: Number(defaults.defaultyear) || (new Date().getFullYear()),
                    months: [Number(defaults.defaultmonth) || (new Date().getMonth() + 1)],
                    years: [Number(defaults.defaultyear) || (new Date().getFullYear())],
                    customstart: '',
                    customend: '',
                    search: '',
                    columns: defaults.defaultcolumns || [],
                    sortkey: 'completiondate',
                    sortdir: 'asc',
                    page: 0,
                    perpage: 20
                };

                state.currentVisualOverrides = state.currentVisualOverrides || {};
                state.currentVisualOverrides.reportbuilder = builder;
                fillSelect(reportBuilderField(root, 'companyid'), defaults.companyselector ? (defaults.companies || []) : [], String(builder.companyid || ''));
                reportBuilderRefreshMultiSelect(root, 'month', defaults.months || [], builder.months);
                reportBuilderRefreshMultiSelect(root, 'year', defaults.years || [], builder.years);
                var templateName = reportBuilderField(root, 'templatename');
                if (templateName) {
                    templateName.value = builder.templatename;
                }
                var customStart = reportBuilderField(root, 'customstart');
                var customEnd = reportBuilderField(root, 'customend');
                var search = reportBuilderField(root, 'search');
                var periodmode = reportBuilderField(root, 'periodmode');
                if (periodmode) {
                    periodmode.value = 'month';
                }
                if (customStart) {
                    customStart.value = '';
                }
                if (customEnd) {
                    customEnd.value = '';
                }
                if (search) {
                    search.value = '';
                }
                reportBuilderRenderTemplateList(root, state, 0);
                reportBuilderSetColumns(root, state, builder.columns || []);
                reportBuilderSyncPeriodUi(root);
                return api.loadRows(root, state, 'replace');
            };

            var handleFieldChange = function(root, state, event) {
                if (!event || !event.target || !event.target.matches('[data-report-field]')) {
                    return false;
                }

                var reportField = event.target.getAttribute('data-report-field');
                if (reportField === 'templateid') {
                    rememberCurrentState(root, state);
                    reportBuilderApplyTemplate(root, state, event.target.value || '0');
                    return true;
                }
                if (reportField === 'periodmode') {
                    rememberCurrentState(root, state);
                    setReportBuilderState(state, Object.assign({}, reportBuilderState(state), {
                        periodmode: event.target.value || 'month',
                        page: 0
                    }));
                    reportBuilderSyncPeriodUi(root);
                    api.loadRows(root, state, 'replace');
                    return true;
                }

                if (['companyid', 'month', 'year', 'customstart', 'customend', 'templatename'].indexOf(reportField) !== -1) {
                    rememberCurrentState(root, state);
                    api.loadRows(root, state, 'replace');
                    return true;
                }

                return false;
            };

            var handleInput = function(root, state, event) {
                if (!event || !event.target) {
                    return false;
                }

                if (event.target.matches('[data-action="report-builder-perpage"]')) {
                    rememberCurrentState(root, state);
                    setReportBuilderState(state, Object.assign({}, reportBuilderState(state), {
                        perpage: Number(event.target.value) || 20,
                        page: 0
                    }));
                    api.loadRows(root, state, 'replace');
                    return true;
                }

                if (event.target.matches('[data-action="report-builder-search"]')) {
                    window.clearTimeout(deps.timer);
                    deps.timer = window.setTimeout(function() {
                        rememberCurrentState(root, state);
                        setReportBuilderState(state, Object.assign({}, reportBuilderState(state), {
                            search: event.target.value || '',
                            page: 0
                        }));
                        api.loadRows(root, state, 'replace');
                    }, 250);
                    return true;
                }
                if (event.target.matches('[data-report-field="columnsearch"]')) {
                    reportBuilderRenderColumnsPicker(root, state);
                    return true;
                }

                return false;
            };

            var handleClick = function(root, state, event) {
                if (!event || !event.target) {
                    return false;
                }

                var reportBuilderMultiToggle = event.target.closest('[data-action="report-builder-multiselect-toggle"]');
                if (reportBuilderMultiToggle && root.contains(reportBuilderMultiToggle)) {
                    var reportBuilderMulti = reportBuilderMultiToggle.closest('[data-report-multiselect]');
                    if (reportBuilderMulti) {
                        Array.prototype.slice.call((reportBuilderRoot(root) || root).querySelectorAll('[data-report-multiselect]')).forEach(function(other) {
                            if (other !== reportBuilderMulti) {
                                other.classList.remove('is-open');
                            }
                        });
                        reportBuilderMulti.classList.toggle('is-open');
                    }
                    return true;
                }

                var reportBuilderMultiAction = event.target.closest('[data-action="report-builder-multi-all"], [data-action="report-builder-multi-clear"], .da-report-builder-multi-option');
                if (reportBuilderMultiAction && root.contains(reportBuilderMultiAction)) {
                    var reportBuilderMultiKey = reportBuilderMultiAction.getAttribute('data-report-multi') || '';
                    var reportBuilderMultiField = reportBuilderField(root, reportBuilderMultiKey);
                    if (!reportBuilderMultiField) {
                        return true;
                    }

                    if (reportBuilderMultiAction.getAttribute('data-action') === 'report-builder-multi-all') {
                        Array.prototype.slice.call(reportBuilderMultiField.options || []).forEach(function(option) {
                            option.selected = true;
                        });
                    } else if (reportBuilderMultiAction.getAttribute('data-action') === 'report-builder-multi-clear') {
                        Array.prototype.slice.call(reportBuilderMultiField.options || []).forEach(function(option) {
                            option.selected = false;
                        });
                    } else {
                        var input = reportBuilderMultiAction.querySelector('input[type="checkbox"]');
                        var hiddenSelectOptions = Array.prototype.slice.call(reportBuilderMultiField.options || []);
                        hiddenSelectOptions.forEach(function(option) {
                            if (input && String(option.value) === String(input.value)) {
                                option.selected = !!input.checked;
                            }
                        });
                    }

                    reportBuilderSyncMultiSelectLabels(root);
                    rememberCurrentState(root, state);
                    setReportBuilderState(state, Object.assign({}, reportBuilderState(state), {
                        page: 0,
                        month: Number(reportBuilderSelectedValues(reportBuilderField(root, 'month'))[0] || reportBuilderState(state).month || 0),
                        year: Number(reportBuilderSelectedValues(reportBuilderField(root, 'year'))[0] || reportBuilderState(state).year || 0),
                        months: reportBuilderSelectedValues(reportBuilderField(root, 'month')).map(Number).filter(function(value) {
                            return value >= 1 && value <= 12;
                        }),
                        years: reportBuilderSelectedValues(reportBuilderField(root, 'year')).map(Number).filter(function(value) {
                            return value >= 2000 && value <= 2100;
                        })
                    }));
                    api.loadRows(root, state, 'replace');
                    return true;
                }

                var reportBuilderColumnAction = event.target.closest('[data-action="report-builder-column-add"], [data-action="report-builder-column-remove"], [data-action="report-builder-column-up"], [data-action="report-builder-column-down"]');
                if (reportBuilderColumnAction && root.contains(reportBuilderColumnAction)) {
                    rememberCurrentState(root, state);
                    var reportBuilderColumnKey = reportBuilderColumnAction.getAttribute('data-report-column') || '';
                    if (reportBuilderColumnAction.getAttribute('data-action') === 'report-builder-column-add') {
                        reportBuilderAddSelectedColumn(root, state, reportBuilderColumnKey);
                    } else if (reportBuilderColumnAction.getAttribute('data-action') === 'report-builder-column-remove') {
                        reportBuilderRemoveSelectedColumn(root, state, reportBuilderColumnKey);
                    } else if (reportBuilderColumnAction.getAttribute('data-action') === 'report-builder-column-up') {
                        reportBuilderMoveSelectedColumn(root, state, reportBuilderColumnKey, -1);
                    } else if (reportBuilderColumnAction.getAttribute('data-action') === 'report-builder-column-down') {
                        reportBuilderMoveSelectedColumn(root, state, reportBuilderColumnKey, 1);
                    }
                    api.loadRows(root, state, 'replace');
                    return true;
                }

                var reportBuilderLoad = event.target.closest('[data-action="report-builder-load"]');
                if (reportBuilderLoad && root.contains(reportBuilderLoad)) {
                    rememberCurrentState(root, state);
                    api.loadRows(root, state, 'replace');
                    return true;
                }

                var reportBuilderTemplateSelect = event.target.closest('[data-action="report-builder-template-select"]');
                if (reportBuilderTemplateSelect && root.contains(reportBuilderTemplateSelect)) {
                    rememberCurrentState(root, state);
                    reportBuilderApplyTemplate(root, state, reportBuilderTemplateSelect.getAttribute('data-template-id') || '0');
                    return true;
                }

                var reportBuilderSaveTemplate = event.target.closest('[data-action="report-builder-save-template"]');
                if (reportBuilderSaveTemplate && root.contains(reportBuilderSaveTemplate)) {
                    rememberCurrentState(root, state);
                    reportBuilderSaveTemplate.disabled = true;
                    var builderState = reportBuilderState(state);
                    var reportBuilderForm = reportBuilderReadForm(root, state);
                    call('block_dashboardanalytics_save_report_template', {
                        contextid: state.contextid,
                        templateid: Number(reportBuilderForm.templateid) || 0,
                        name: reportBuilderForm.templatename || '',
                        columns: JSON.stringify(reportBuilderForm.columns || []),
                        filters: JSON.stringify({
                            companyid: reportBuilderForm.companyid || 0,
                            periodmode: reportBuilderForm.periodmode || 'month',
                            month: reportBuilderForm.month || 0,
                            year: reportBuilderForm.year || 0,
                            months: reportBuilderForm.months || [],
                            years: reportBuilderForm.years || [],
                            customstart: reportBuilderForm.customstart || '',
                            customend: reportBuilderForm.customend || '',
                            search: reportBuilderForm.search || '',
                            sortkey: builderState.sortkey || 'completiondate',
                            sortdir: builderState.sortdir || 'asc',
                            page: builderState.page || 0,
                            perpage: builderState.perpage || 20
                        })
                    }).then(function(response) {
                        var payload = {};
                        try {
                            payload = JSON.parse(response.json || '{}') || {};
                        } catch (e) {
                            payload = {};
                        }

                        if (payload.templates) {
                            state.currentReportBuilderConfig = state.currentReportBuilderConfig || {};
                            state.currentReportBuilderConfig.templates = payload.templates;
                        }
                        if (payload.template) {
                            state.currentVisualOverrides = state.currentVisualOverrides || {};
                            state.currentVisualOverrides.reportbuilder = Object.assign({}, reportBuilderState(state), {
                                templateid: Number(payload.template.id) || 0,
                                templatename: payload.template.name || ''
                            });
                            reportBuilderRenderTemplateList(root, state, payload.template.id);
                            var templateField = reportBuilderField(root, 'templatename');
                            if (templateField) {
                                templateField.value = payload.template.name || '';
                            }
                        }
                        Notification.addNotification({
                            message: 'Report template saved.',
                            type: 'success'
                        });
                        return api.loadRows(root, state, 'replace');
                    }).catch(function(error) {
                        Notification.exception(error);
                    }).finally(function() {
                        reportBuilderSaveTemplate.disabled = false;
                    });
                    return true;
                }

                var reportBuilderSaveAsTemplate = event.target.closest('[data-action="report-builder-save-as-template"]');
                if (reportBuilderSaveAsTemplate && root.contains(reportBuilderSaveAsTemplate)) {
                    rememberCurrentState(root, state);
                    reportBuilderSaveAsTemplate.disabled = true;
                    reportBuilderPromptTemplateName(reportBuilderState(state).templatename || text('reportBuilderUntitled', 'Untitled report')).then(function(name) {
                        if (!name) {
                            return null;
                        }

                        var reportBuilderAsForm = reportBuilderReadForm(root, state);
                        var reportBuilderAsState = reportBuilderState(state);
                        return call('block_dashboardanalytics_save_report_template', {
                            contextid: state.contextid,
                            templateid: 0,
                            name: name,
                            columns: JSON.stringify(reportBuilderAsForm.columns || []),
                            filters: JSON.stringify({
                                companyid: reportBuilderAsForm.companyid || 0,
                                periodmode: reportBuilderAsForm.periodmode || 'month',
                                month: reportBuilderAsForm.month || 0,
                                year: reportBuilderAsForm.year || 0,
                                months: reportBuilderAsForm.months || [],
                                years: reportBuilderAsForm.years || [],
                                customstart: reportBuilderAsForm.customstart || '',
                                customend: reportBuilderAsForm.customend || '',
                                search: reportBuilderAsForm.search || '',
                                sortkey: reportBuilderAsState.sortkey || 'completiondate',
                                sortdir: reportBuilderAsState.sortdir || 'asc',
                                page: reportBuilderAsState.page || 0,
                                perpage: reportBuilderAsState.perpage || 20
                            })
                        });
                    }).then(function(response) {
                        if (!response) {
                            return;
                        }

                        var payload = {};
                        try {
                            payload = JSON.parse(response.json || '{}') || {};
                        } catch (e) {
                            payload = {};
                        }
                        if (payload.templates) {
                            state.currentReportBuilderConfig = state.currentReportBuilderConfig || {};
                            state.currentReportBuilderConfig.templates = payload.templates;
                        }
                        if (payload.template) {
                            state.currentVisualOverrides = state.currentVisualOverrides || {};
                            state.currentVisualOverrides.reportbuilder = Object.assign({}, reportBuilderState(state), {
                                templateid: Number(payload.template.id) || 0,
                                templatename: payload.template.name || ''
                            });
                            reportBuilderRenderTemplateList(root, state, payload.template.id);
                            var reportBuilderAsTemplateField = reportBuilderField(root, 'templatename');
                            if (reportBuilderAsTemplateField) {
                                reportBuilderAsTemplateField.value = payload.template.name || '';
                            }
                        }
                        Notification.addNotification({
                            message: 'Report template saved.',
                            type: 'success'
                        });
                        return api.loadRows(root, state, 'replace');
                    }).catch(function(error) {
                        if (error) {
                            Notification.exception(error);
                        }
                    }).finally(function() {
                        reportBuilderSaveAsTemplate.disabled = false;
                    });
                    return true;
                }

                var reportBuilderDeleteTemplate = event.target.closest('[data-action="report-builder-delete-template"]');
                if (reportBuilderDeleteTemplate && root.contains(reportBuilderDeleteTemplate)) {
                    rememberCurrentState(root, state);
                    var reportBuilderTemplateId = Number((reportBuilderField(root, 'templateid') || {}).value) || 0;
                    if (!reportBuilderTemplateId) {
                        Notification.addNotification({
                            message: 'Select a template first.',
                            type: 'info'
                        });
                        return true;
                    }
                    reportBuilderDeleteTemplate.disabled = true;
                    call('block_dashboardanalytics_delete_report_template', {
                        contextid: state.contextid,
                        templateid: reportBuilderTemplateId
                    }).then(function(response) {
                        var payload = {};
                        try {
                            payload = JSON.parse(response.json || '{}') || {};
                        } catch (e) {
                            payload = {};
                        }
                        if (payload.templates) {
                            state.currentReportBuilderConfig = state.currentReportBuilderConfig || {};
                            state.currentReportBuilderConfig.templates = payload.templates;
                        }
                        state.currentVisualOverrides = state.currentVisualOverrides || {};
                        state.currentVisualOverrides.reportbuilder = Object.assign({}, reportBuilderState(state), {
                            templateid: 0
                        });
                        reportBuilderRenderTemplateList(root, state, 0);
                        Notification.addNotification({
                            message: 'Report template deleted.',
                            type: 'success'
                        });
                        return api.loadRows(root, state, 'replace');
                    }).catch(function(error) {
                        Notification.exception(error);
                    }).finally(function() {
                        reportBuilderDeleteTemplate.disabled = false;
                    });
                    return true;
                }

                var reportBuilderSort = event.target.closest('[data-action="drilldown-sort"]');
                if (reportBuilderSort && root.contains(reportBuilderSort) && reportBuilderSort.closest('[data-region="report-builder"]')) {
                    rememberCurrentState(root, state);
                    var currentReportBuilder = reportBuilderState(state);
                    setReportBuilderState(state, Object.assign({}, currentReportBuilder, {
                        sortkey: reportBuilderSort.getAttribute('data-sort-key') || 'completiondate',
                        sortdir: reportBuilderSort.getAttribute('data-sort-dir') === 'desc' ? 'desc' : 'asc',
                        page: 0
                    }));
                    api.loadRows(root, state, 'replace');
                    return true;
                }

                var reportBuilderPage = event.target.closest('[data-action="report-builder-page"]');
                if (reportBuilderPage && root.contains(reportBuilderPage) && !reportBuilderPage.disabled) {
                    rememberCurrentState(root, state);
                    setReportBuilderState(state, Object.assign({}, reportBuilderState(state), {
                        page: Number(reportBuilderPage.getAttribute('data-page')) || 0
                    }));
                    api.loadRows(root, state, 'replace');
                    return true;
                }

                return false;
            };

            var api = {
                root: reportBuilderRoot,
                configTemplates: reportBuilderConfigTemplates,
                templateById: reportBuilderTemplateById,
                field: reportBuilderField,
                selectValues: reportBuilderSelectValues,
                selectedValues: reportBuilderSelectedValues,
                multiSelectMarkup: reportBuilderMultiSelectMarkup,
                syncMultiSelectLabels: reportBuilderSyncMultiSelectLabels,
                refreshMultiSelect: reportBuilderRefreshMultiSelect,
                setMultiSelectValues: reportBuilderSetMultiSelectValues,
                promptTemplateName: reportBuilderPromptTemplateName,
                state: reportBuilderState,
                columnDefinitions: reportBuilderColumnDefinitions,
                columnDefinitionByKey: reportBuilderColumnDefinitionByKey,
                setState: setReportBuilderState,
                selectedColumns: reportBuilderSelectedColumns,
                setSelectedColumns: reportBuilderSetSelectedColumns,
                moveSelectedColumn: reportBuilderMoveSelectedColumn,
                addSelectedColumn: reportBuilderAddSelectedColumn,
                removeSelectedColumn: reportBuilderRemoveSelectedColumn,
                renderColumnsPicker: reportBuilderRenderColumnsPicker,
                setColumns: reportBuilderSetColumns,
                syncPeriodUi: reportBuilderSyncPeriodUi,
                applyTemplate: reportBuilderApplyTemplate,
                renderTemplateList: reportBuilderRenderTemplateList,
                refreshTemplateSelect: reportBuilderRenderTemplateList,
                readForm: reportBuilderReadForm,
                exportUrl: reportBuilderExportUrl,
                renderPanel: renderReportBuilderPanel,
                renderSummary: renderReportBuilderSummary,
                renderResults: renderReportBuilderResults,
                loadConfig: loadReportBuilderConfig,
                loadRows: loadReportBuilderRows,
                clearForm: clearReportBuilderForm,
                handleChange: handleFieldChange,
                handleInput: handleInput,
                handleClick: handleClick
            };

            return api;
        }
    };
});
