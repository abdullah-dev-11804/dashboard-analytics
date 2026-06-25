define(['core/ajax', 'core/notification', 'core/str'], function(Ajax, Notification, Str) {
    var SELECTOR = '[data-region="dashboardanalytics"]';
    var modalEventsBound = false;
    var strings = {
        loading: 'Loading...',
        details: 'Details',
        rows: '{$a} rows',
        noMatchingRows: 'No matching rows.',
        noKpi: 'No KPI data found.',
        noVisualData: 'No visual data available.',
        dashboardVisuals: 'Dashboard visuals',
        noFilterOptions: 'No filter options found.',
        allOption: 'All',
        allWithLabel: 'All {$a}',
        allLabels: {},
        activeFiltersAll: 'Active filters: All ×',
        activeFiltersPrefix: 'Active filters: {$a} ×',
        noData: 'No data',
        total: 'total',
        addFilter: '+ Add filter',
        noAvailableFilters: 'No additional filters available.',
        removeFilter: 'Remove filter',
        customStart: 'Start date',
        customEnd: 'End date',
        previous: 'Previous',
        next: 'Next',
        perPage: 'Rows per page',
        page: 'Page {$a}',
        goToServerTab: 'Go to Server tab',
        warningThreshold: 'Warning threshold:',
        criticalThreshold: 'Critical threshold:',
        clearState: 'Clear',
        monitorState: 'Monitor',
        criticalState: 'Critical',
        okSummary: 'OK',
        warningSummary: 'Warning',
        checkSummary: 'Check',
        topActiveCourses: 'Top active courses this month',
        companyHeader: 'Company',
        usersHeader: 'Users',
        complianceHeader: 'Compliance',
        turnoverHeader: 'Turnover',
        trustScoreHeader: 'Trust score',
        completionHeader: 'Completion',
        statusHeader: 'Status',
        reportLabel: 'Report',
        healthyLabel: 'Healthy',
        atRiskLabel: 'At risk',
        onboardingLabel: 'Onboarding',
        period3Months: '3 months',
        period1Year: '1 year',
        period2Years: '2 years',
        periodAllTime: 'All time',
        barChartLabel: 'Bar chart',
        interactiveLabel: 'interactive',
        comboBarLineLabel: 'Combo bar-line',
        chartJsBarLabel: 'Chart.js bar',
        turnoverFormula: 'Formula: Deactivated / Avg active × 100',
        turnoverGood: '<5% Good',
        turnoverMonitor: '5–10% Monitor',
        turnoverHigh: '>10% High'
    };

    var stringList = [
        {key: 'loading', component: 'block_dashboardanalytics'},
        {key: 'js:details', component: 'block_dashboardanalytics'},
        {key: 'js:rows', component: 'block_dashboardanalytics'},
        {key: 'js:nomatchingrows', component: 'block_dashboardanalytics'},
        {key: 'js:nokpi', component: 'block_dashboardanalytics'},
        {key: 'js:novisualdata', component: 'block_dashboardanalytics'},
        {key: 'js:dashboardvisuals', component: 'block_dashboardanalytics'},
        {key: 'js:nofilteroptions', component: 'block_dashboardanalytics'},
        {key: 'filter:alloption', component: 'block_dashboardanalytics'},
        {key: 'filter:allwithlabel', component: 'block_dashboardanalytics'},
        {key: 'filter:clearall', component: 'block_dashboardanalytics'},
        {key: 'filter:activeprefix', component: 'block_dashboardanalytics'},
        {key: 'js:nodata', component: 'block_dashboardanalytics'},
        {key: 'js:total', component: 'block_dashboardanalytics'},
        {key: 'filter:add', component: 'block_dashboardanalytics'},
        {key: 'filter:noavailable', component: 'block_dashboardanalytics'},
        {key: 'filter:remove', component: 'block_dashboardanalytics'},
        {key: 'filter:customstart', component: 'block_dashboardanalytics'},
        {key: 'filter:customend', component: 'block_dashboardanalytics'},
        {key: 'js:previous', component: 'block_dashboardanalytics'},
        {key: 'js:next', component: 'block_dashboardanalytics'},
        {key: 'js:perpage', component: 'block_dashboardanalytics'},
        {key: 'js:page', component: 'block_dashboardanalytics'},
        {key: 'js:gotoservertab', component: 'block_dashboardanalytics'},
        {key: 'js:warningthreshold', component: 'block_dashboardanalytics'},
        {key: 'js:criticalthreshold', component: 'block_dashboardanalytics'},
        {key: 'js:clearstate', component: 'block_dashboardanalytics'},
        {key: 'js:monitorstate', component: 'block_dashboardanalytics'},
        {key: 'js:criticalstate', component: 'block_dashboardanalytics'},
        {key: 'js:oksummary', component: 'block_dashboardanalytics'},
        {key: 'js:warningsummary', component: 'block_dashboardanalytics'},
        {key: 'js:checksummary', component: 'block_dashboardanalytics'},
        {key: 'js:topactivecourses', component: 'block_dashboardanalytics'},
        {key: 'js:companyheader', component: 'block_dashboardanalytics'},
        {key: 'js:usersheader', component: 'block_dashboardanalytics'},
        {key: 'js:complianceheader', component: 'block_dashboardanalytics'},
        {key: 'js:turnoverheader', component: 'block_dashboardanalytics'},
        {key: 'js:trustscoreheader', component: 'block_dashboardanalytics'},
        {key: 'js:completionheader', component: 'block_dashboardanalytics'},
        {key: 'js:statusheader', component: 'block_dashboardanalytics'},
        {key: 'js:reportlabel', component: 'block_dashboardanalytics'},
        {key: 'js:healthylabel', component: 'block_dashboardanalytics'},
        {key: 'js:atrisklabel', component: 'block_dashboardanalytics'},
        {key: 'js:onboardinglabel', component: 'block_dashboardanalytics'},
        {key: 'js:period3months', component: 'block_dashboardanalytics'},
        {key: 'js:period1year', component: 'block_dashboardanalytics'},
        {key: 'js:period2years', component: 'block_dashboardanalytics'},
        {key: 'js:periodalltime', component: 'block_dashboardanalytics'},
        {key: 'js:barchartlabel', component: 'block_dashboardanalytics'},
        {key: 'js:interactivelabel', component: 'block_dashboardanalytics'},
        {key: 'filter:allcompanieslabel', component: 'block_dashboardanalytics'},
        {key: 'filter:allcourseslabel', component: 'block_dashboardanalytics'},
        {key: 'filter:allperiodslabel', component: 'block_dashboardanalytics'},
        {key: 'filter:alldepartmentslabel', component: 'block_dashboardanalytics'},
        {key: 'filter:alllocationslabel', component: 'block_dashboardanalytics'},
        {key: 'filter:allpositionslabel', component: 'block_dashboardanalytics'},
        {key: 'filter:allpersonnelcategorieslabel', component: 'block_dashboardanalytics'},
        {key: 'filter:allsiteslabel', component: 'block_dashboardanalytics'},
        {key: 'filter:alleducationslabel', component: 'block_dashboardanalytics'},
        {key: 'js:combobarlinelabel', component: 'block_dashboardanalytics'},
        {key: 'js:chartjsbarlabel', component: 'block_dashboardanalytics'},
        {key: 'js:turnoverformula', component: 'block_dashboardanalytics'},
        {key: 'js:turnovergood', component: 'block_dashboardanalytics'},
        {key: 'js:turnovermonitor', component: 'block_dashboardanalytics'},
        {key: 'js:turnoverhigh', component: 'block_dashboardanalytics'}
    ];

    var call = function(methodname, args) {
        return Ajax.call([{
            methodname: methodname,
            args: args
        }])[0];
    };

    var text = function(key, fallback) {
        return strings[key] || fallback || key;
    };

    var escapeHtml = function(value) {
        var node = document.createElement('div');
        node.textContent = value === null || value === undefined ? '' : String(value);
        return node.innerHTML;
    };

    var formatString = function(template, value) {
        return String(template).replace('{$a}', value);
    };

    var defaultOptionLabel = function(group) {
        var natural = strings.allLabels || {};
        if (group && group.key && natural[group.key]) {
            return natural[group.key];
        }

        return formatString(text('allWithLabel', 'All {$a}'), group.label);
    };

    var storageKey = function(state) {
        return 'block_dashboardanalytics:' + state.contextid + ':' + state.dashboardkey;
    };

    var readSessionState = function(state) {
        try {
            return JSON.parse(window.sessionStorage.getItem(storageKey(state)) || '{}');
        } catch (error) {
            return {};
        }
    };

    var writeSessionState = function(state, payload) {
        try {
            window.sessionStorage.setItem(storageKey(state), JSON.stringify(payload));
        } catch (error) {
            // Ignore storage write issues.
        }
    };

    var selectedValues = function(select) {
        return Array.prototype.slice.call(select.selectedOptions || [])
            .map(function(option) {
                return option.value;
            })
            .filter(function(value) {
                return value !== '';
            });
    };

    var defaultDateRange = function(state) {
        var options = (((state.filterGroups || {}).daterange || {}).options || []).map(function(option) {
            return option.value;
        });
        if (options.indexOf('year') !== -1) {
            return 'year';
        }
        if (options.indexOf('last12months') !== -1) {
            return 'last12months';
        }
        return options[0] || 'last12months';
    };

    var defaultDrilldownKey = function(state) {
        if (state.dashboardkey === 'company') {
            return 'company_total_active_users';
        }

        if (state.dashboardkey === 'client') {
            return 'client_total_staff';
        }

        return 'employee_documents';
    };

    var readFilters = function(root, state, overrides) {
        var filters = {};
        Array.prototype.slice.call(root.querySelectorAll('select[data-filter-group]')).forEach(function(select) {
            var key = select.getAttribute('data-filter-group');
            if (key === 'daterange') {
                filters[key] = select.value || defaultDateRange(state);
            } else {
                filters[key] = selectedValues(select);
            }
        });

        var start = root.querySelector('[data-filter-custom="customstart"]');
        var end = root.querySelector('[data-filter-custom="customend"]');
        filters.customstart = start ? start.value : '';
        filters.customend = end ? end.value : '';
        filters.status = '';
        filters.search = '';

        if (overrides) {
            Object.keys(overrides).forEach(function(key) {
                filters[key] = overrides[key];
            });
        }

        return filters;
    };

    var persistState = function(root, state) {
        writeSessionState(state, {
            activeFilterKeys: state.activeFilterKeys || [],
            filters: readFilters(root, state),
            currentTab: state.currentTab || 'overview',
            visualOverrides: state.currentVisualOverrides || {}
        });
    };

    var setLoading = function(node) {
        if (node) {
            node.innerHTML = '<div class="da-loading">' + escapeHtml(text('loading', 'Loading...')) + '</div>';
        }
    };

    var activeFilterDefaults = function(state) {
        var defaults = [state.companyFilterKey, 'daterange', 'departments', 'locations', 'positions'];
        return defaults.filter(function(key) {
            return key && state.filterGroups[key];
        });
    };

    var normalizeActiveFilterKeys = function(state) {
        var available = Object.keys(state.filterGroups || {});
        var active = Array.isArray(state.activeFilterKeys) ? state.activeFilterKeys.slice() : [];

        if (!active.length) {
            active = activeFilterDefaults(state);
        }

        active = active.filter(function(key) {
            return available.indexOf(key) !== -1;
        });

        if (state.companyFilterKey && active.indexOf(state.companyFilterKey) === -1) {
            active.unshift(state.companyFilterKey);
        }

        return active.filter(function(key, index) {
            return active.indexOf(key) === index;
        });
    };

    var selectedValueForGroup = function(state, key) {
        var saved = state.persistedFilters || {};
        if (key === 'daterange') {
            return saved.daterange || defaultDateRange(state);
        }
        var values = saved[key];
        if (Array.isArray(values) && values.length) {
            return String(values[0]);
        }
        return '';
    };

    var renderFilterControl = function(state, group) {
        var key = group.key;
        var selected = selectedValueForGroup(state, key);
        var options = group.options || [];
        var includeBlank = key !== 'daterange';
        var allLabel = defaultOptionLabel(group);
        var optionHtml = (includeBlank ? '<option value="">' + escapeHtml(allLabel) + '</option>' : '')
            + options.map(function(option) {
                var isSelected = String(option.value) === selected ? ' selected' : '';
                return '<option value="' + escapeHtml(option.value) + '"' + isSelected + '>' + escapeHtml(option.label) + '</option>';
            }).join('');

        var remove = key !== state.companyFilterKey
            ? '<button type="button" class="da-filter-remove" data-action="remove-filter" data-filter-key="' + escapeHtml(key)
                + '" aria-label="' + escapeHtml(text('removeFilter', 'Remove filter')) + '">×</button>'
            : '';

        var customRange = '';
        if (key === 'daterange' && selected === 'customrange') {
            customRange = '<div class="da-filter-custom-range">'
                + '<label><span>' + escapeHtml(text('customStart', 'Start date')) + '</span>'
                + '<input type="date" class="da-filter-date" data-filter-custom="customstart" value="'
                + escapeHtml((state.persistedFilters || {}).customstart || '') + '"></label>'
                + '<label><span>' + escapeHtml(text('customEnd', 'End date')) + '</span>'
                + '<input type="date" class="da-filter-date" data-filter-custom="customend" value="'
                + escapeHtml((state.persistedFilters || {}).customend || '') + '"></label>'
                + '</div>';
        }

        return '<div class="da-filter-control" data-filter-wrap="' + escapeHtml(key) + '">'
            + '<div class="da-filter-control-inputs">'
            + '<select id="da-filter-' + escapeHtml(key) + '-' + escapeHtml(String(state.contextid)) + '" class="da-filter-select"'
            + ' data-filter-group="' + escapeHtml(key) + '" aria-label="' + escapeHtml(group.label) + '">'
            + optionHtml
            + '</select>'
            + remove
            + '</div>'
            + customRange
            + '</div>';
    };

    var renderAddFilterMenu = function(state) {
        var remaining = Object.keys(state.filterGroups || {}).filter(function(key) {
            return state.activeFilterKeys.indexOf(key) === -1;
        });

        if (!remaining.length) {
            return '<div class="da-filter-add-menu" data-region="add-filter-menu" hidden>'
                + '<div class="da-filter-add-empty">' + escapeHtml(text('noAvailableFilters', 'No additional filters available.')) + '</div>'
                + '</div>';
        }

        return '<div class="da-filter-add-menu" data-region="add-filter-menu" hidden>'
            + remaining.map(function(key) {
                var group = state.filterGroups[key];
                return '<button type="button" class="da-filter-add-option" data-action="add-filter" data-filter-key="'
                    + escapeHtml(key) + '">' + escapeHtml(group.label) + '</button>';
            }).join('')
            + '</div>';
    };

    var renderFilters = function(root, state, groups) {
        var container = root.querySelector('[data-region="filter-bar"]');
        var addButton = root.querySelector('[data-action="toggle-add-filter"]');
        var existingMenus = root.querySelectorAll('[data-region="add-filter-menu"]');
        if (!container) {
            return;
        }

        if (!groups.length) {
            container.innerHTML = '<div class="da-empty">' + escapeHtml(text('noFilterOptions', 'No filter options found.')) + '</div>';
            return;
        }

        state.filterGroups = {};
        groups.forEach(function(group) {
            state.filterGroups[group.key] = group;
        });
        state.companyFilterKey = groups[0] ? groups[0].key : state.companyFilterKey;
        state.activeFilterKeys = normalizeActiveFilterKeys(state);

        container.innerHTML = state.activeFilterKeys.map(function(key) {
            return renderFilterControl(state, state.filterGroups[key]);
        }).join('');

        Array.prototype.slice.call(existingMenus).forEach(function(menu) {
            if (menu && menu.parentNode) {
                menu.parentNode.removeChild(menu);
            }
        });

        if (addButton) {
            addButton.textContent = text('addFilter', '+ Add filter');
            addButton.insertAdjacentHTML('afterend', renderAddFilterMenu(state));
        }

        updateFilterCounts(root, state);
    };

    var updateFilterCounts = function(root, state) {
        var active = [];
        Array.prototype.slice.call(root.querySelectorAll('select[data-filter-group]')).forEach(function(select) {
            var key = select.getAttribute('data-filter-group');
            var option = select.options[select.selectedIndex];
            var defaultRange = defaultDateRange(state);
            if (!option || option.value === '' || (key === 'daterange' && option.value === defaultRange)) {
                return;
            }
            active.push(option.textContent);
        });

        var chip = root.querySelector('[data-action="clear-filters"]');
        if (chip) {
            chip.textContent = active.length
                ? formatString(text('activeFiltersPrefix', 'Active filters: {$a} ×'), active.join(', '))
                : text('activeFiltersAll', 'Active filters: All ×');
        }
    };

    var renderKpis = function(root, cards) {
        var container = root.querySelector('[data-region="kpi-strip"]');
        if (!container) {
            return;
        }

        if (!cards.length) {
            container.innerHTML = '<div class="da-empty">' + escapeHtml(text('noKpi', 'No KPI data found.')) + '</div>';
            return;
        }

        container.innerHTML = cards.map(function(card) {
            return '<button type="button" class="da-kpi da-kpi-' + escapeHtml(card.status)
                + '" data-drilldown="' + escapeHtml(card.drilldownkey)
                + '" title="' + escapeHtml(card.help) + '">'
                + '<span class="da-kpi-label">' + escapeHtml(card.label) + '</span>'
                + '<span class="da-kpi-value">' + escapeHtml(card.value)
                + (card.unit ? ' <small>' + escapeHtml(card.unit) + '</small>' : '')
                + '</span>'
                + (card.trend ? '<span class="da-kpi-trend">' + escapeHtml(card.trend) + '</span>' : '')
                + (card.help ? '<span class="da-kpi-hint">' + escapeHtml(card.help) + '</span>' : '')
                + '</button>';
        }).join('');
    };

    var renderDrilldown = function(root, data, state) {
        var container = root.querySelector('[data-region="drilldown"]');
        var title = root.querySelector('[data-region="drilldown-title"]');
        var count = root.querySelector('[data-region="drilldown-count"]');
        var currentPage = Math.max(0, Number(state.currentDrilldownPage) || 0);
        var perpage = Math.max(10, Number(state.currentDrilldownPerPage) || 20);
        var totalcount = Math.max(0, Number(data.totalcount) || 0);
        var totalpages = Math.max(1, Math.ceil(totalcount / perpage));

        if (title) {
            title.textContent = data.title || text('details', 'Details');
        }
        if (count) {
            count.textContent = totalcount ? formatString(text('rows', '{$a} rows'), String(totalcount)) : '';
        }

        if (!container) {
            return;
        }

        if (data.notice) {
            container.innerHTML = '<div class="da-empty">' + escapeHtml(data.notice) + '</div>';
            return;
        }

        if (!data.rows || !data.rows.length) {
            container.innerHTML = '<div class="da-empty">' + escapeHtml(text('noMatchingRows', 'No matching rows.')) + '</div>';
            return;
        }

        if (state.currentDrilldown === 'company_server_disk') {
            count.textContent = data.description || '';
            var metricTiles = data.rows.map(function(row) {
                var cellsByKey = {};
                (row.cells || []).forEach(function(cell) {
                    cellsByKey[cell.key] = cell.value;
                });
                var statusKey = cellsByKey.statuskey || 'muted';
                var percent = Math.max(0, Math.min(100, Number(cellsByKey.percent) || 0));
                return '<article class="da-server-metric da-server-metric-' + escapeHtml(statusKey) + '">'
                    + '<div class="da-server-metric-head">'
                    + '<strong>' + escapeHtml(cellsByKey.metric || '') + '</strong>'
                    + '<span class="da-server-metric-value da-text-' + escapeHtml(statusKey) + '">' + escapeHtml(cellsByKey.value || '') + '</span>'
                    + '</div>'
                    + '<div class="da-server-metric-track"><span class="da-server-metric-fill da-bar-fill-' + escapeHtml(statusKey) + '" style="width:' + percent + '%"></span></div>'
                    + '<div class="da-server-metric-meta">' + escapeHtml(cellsByKey.meta || '') + '</div>'
                    + '</article>';
            }).join('');

            var serverAction = root.querySelector('[data-tab="server"]')
                ? '<div class="da-server-metric-actions"><button type="button" class="da-row-action" data-action="goto-tab" data-tab="server">'
                    + escapeHtml(text('goToServerTab', 'Go to Server tab')) + '</button></div>'
                : '';

            container.innerHTML = '<div class="da-server-metrics-panel"><div class="da-server-metrics-grid">'
                + metricTiles + '</div>' + serverAction + '</div>';
            return;
        }

        var columns = data.columns || [];
        var head = columns.map(function(column) {
            return '<th scope="col">' + escapeHtml(column.label) + '</th>';
        }).join('');

        var body = data.rows.map(function(row) {
            var cellsByKey = {};
            (row.cells || []).forEach(function(cell) {
                cellsByKey[cell.key] = cell.value;
            });

            return '<tr>' + columns.map(function(column) {
                var value = escapeHtml(cellsByKey[column.key] || '');
                var key = column.key;
                if (key === 'status' || key === 'statusbadge') {
                    return '<td><span class="da-badge da-badge-' + value.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '">' + value + '</span></td>';
                }
                if (key === 'action') {
                    return '<td><button type="button" class="da-row-action" data-action="company-report"'
                        + ' data-company="' + escapeHtml(cellsByKey.company || '') + '"'
                        + ' data-companyid="' + escapeHtml(cellsByKey.companyid || '') + '">'
                        + value + '</button></td>';
                }
                return '<td>' + value + '</td>';
            }).join('') + '</tr>';
        }).join('');

        var description = data.description
            ? '<div class="da-description">' + escapeHtml(data.description) + '</div>'
            : '';

        var pagination = totalcount ? '<div class="da-table-pagination">'
            + '<div class="da-table-pagination-status">' + escapeHtml(formatString(text('page', 'Page {$a}'), String((currentPage + 1) + ' / ' + totalpages))) + '</div>'
            + '<div class="da-table-pagination-controls">'
            + '<label class="da-table-perpage-label"><span>' + escapeHtml(text('perPage', 'Rows per page')) + '</span>'
            + '<select class="da-table-perpage" data-action="drilldown-perpage">'
            + [20, 50, 100].map(function(size) {
                return '<option value="' + size + '"' + (size === perpage ? ' selected' : '') + '>' + size + '</option>';
            }).join('')
            + '</select></label>'
            + '<button type="button" class="da-pagination-button" data-action="drilldown-page" data-page="' + Math.max(0, currentPage - 1) + '"'
            + (currentPage <= 0 ? ' disabled' : '') + '>' + escapeHtml(text('previous', 'Previous')) + '</button>'
            + '<button type="button" class="da-pagination-button" data-action="drilldown-page" data-page="' + Math.min(totalpages - 1, currentPage + 1) + '"'
            + (currentPage >= totalpages - 1 ? ' disabled' : '') + '>' + escapeHtml(text('next', 'Next')) + '</button>'
            + '</div></div>' : '';

        container.innerHTML = description + '<div class="da-table-wrap"><table class="da-table">'
            + '<thead><tr>' + head + '</tr></thead>'
            + '<tbody>' + body + '</tbody>'
            + '</table></div>' + pagination;
    };

    var renderVisuals = function(root, data, state) {
        var container = root.querySelector('[data-region="drilldown"]');
        var title = root.querySelector('[data-region="drilldown-title"]');
        var count = root.querySelector('[data-region="drilldown-count"]');

        if (title) {
            title.textContent = data.title || text('dashboardVisuals', 'Dashboard visuals');
        }
        if (count) {
            count.textContent = data.description || '';
        }
        if (!container) {
            return;
        }

        var panels = data.panels || [];
        var hasServerPanels = panels.some(function(panel) {
            return ['servergauges', 'serverforecast', 'servererrors', 'serversettings'].indexOf(panel.type) !== -1;
        });
        var isFullRowVisualPanel = function(panel) {
            return ['table', 'servererrors', 'serversettings', 'overviewsummary', 'companyhealth', 'alerts'].indexOf(panel.type) !== -1
                || ['coursecompliance', 'newhirerisk'].indexOf(panel.key) !== -1;
        };
        if (!panels.length) {
            container.innerHTML = '<div class="da-empty">' + escapeHtml(text('noVisualData', 'No visual data available.')) + '</div>';
            return;
        }

        container.innerHTML = '<div class="da-visual-grid' + (hasServerPanels ? ' da-visual-grid-server' : '') + '">' + panels.map(function(panel) {
            var items = panel.items || [];
            var body = '';

            if (!items.length) {
                body = '<div class="da-empty">' + escapeHtml(text('noMatchingRows', 'No matching rows.')) + '</div>';
            } else if (panel.type === 'overviewsummary') {
                body = '<div class="da-overview-summary-grid">' + items.map(function(item) {
                    return '<article class="da-overview-summary-card da-overview-summary-card-' + escapeHtml(item.status) + '">'
                        + '<span class="da-overview-summary-label">' + escapeHtml(item.label) + '</span>'
                        + '<strong class="da-overview-summary-value da-text-' + escapeHtml(item.status) + '">' + escapeHtml(item.value) + '</strong>'
                        + '<span class="da-overview-summary-meta">' + escapeHtml(item.meta || '') + '</span>'
                        + '</article>';
                }).join('') + '</div>';
            } else if (panel.type === 'multibars') {
                var isPlatformGrowth = panel.key === 'platformgrowth';
                var platformGrowthPeriod = (((state || {}).currentVisualOverrides) || {}).platformgrowthperiod || '1year';
                var periodButtons = '';
                if (isPlatformGrowth) {
                    var periodLabel = ((((state || {}).filterGroups) || {}).daterange || {}).label || 'Period';
                    var periodOptions = [
                        {key: '3months', label: text('period3Months', '3 months')},
                        {key: '1year', label: text('period1Year', '1 year')},
                        {key: '2years', label: text('period2Years', '2 years')},
                        {key: 'alltime', label: text('periodAllTime', 'All time')}
                    ];
                    periodButtons = '<div class="da-platform-growth-periods"><span class="da-platform-growth-period-label">' + escapeHtml(periodLabel + ':') + '</span>'
                        + periodOptions.map(function(option) {
                            return '<button type="button" class="da-platform-growth-period'
                                + (platformGrowthPeriod === option.key ? ' is-active' : '')
                                + '" data-action="platformgrowth-period" data-period="' + escapeHtml(option.key) + '">'
                                + escapeHtml(option.label) + '</button>';
                        }).join('') + '</div>';
                }
                var legend = (((items[0] || {}).segments) || []).map(function(segment) {
                    return '<span class="da-multibars-legend-item"><span class="da-dot da-dot-' + escapeHtml(segment.status) + '"></span>' + escapeHtml(segment.label) + '</span>';
                }).join('');
                body = (isPlatformGrowth ? '<div class="da-platform-growth-head">' + periodButtons + '</div>' : '')
                    + (legend ? '<div class="da-multibars-legend">' + legend + '</div>' : '')
                    + '<div class="da-multibars-chart' + (isPlatformGrowth ? ' da-multibars-chart-growth' : '') + '">' + items.map(function(item) {
                        var segments = item.segments || [];
                        return '<div class="da-multibars-group">'
                            + '<div class="da-multibars-columns">' + segments.map(function(segment) {
                                var numericPercent = Number(segment.percent) || 0;
                                var height = numericPercent > 0 ? Math.max(6, Math.min(100, numericPercent)) : 0;
                                return '<div class="da-multibars-column-wrap">'
                                    + '<span class="da-multibars-value">' + escapeHtml(segment.value || '') + '</span>'
                                    + '<span class="da-multibars-column da-bar-fill-' + escapeHtml(segment.status) + '" style="height:' + height + '%" title="'
                                        + escapeHtml(segment.label + ': ' + segment.value) + '"></span>'
                                    + '</div>';
                            }).join('') + '</div>'
                            + '<div class="da-multibars-label">' + escapeHtml(item.label) + '</div>'
                            + '</div>';
                    }).join('') + '</div>';
            } else if (panel.type === 'turnovercombo') {
                var turnoverLegend = (((items[0] || {}).segments) || []).slice(0, 3).map(function(segment) {
                    return '<span class="da-turnover-legend-item"><span class="da-dot da-dot-' + escapeHtml(segment.status) + '"></span>'
                        + escapeHtml(segment.label) + '</span>';
                }).join('');
                var comboMax = 1;
                items.forEach(function(item) {
                    (item.segments || []).forEach(function(segment) {
                        comboMax = Math.max(comboMax, Math.abs(Number(segment.value) || 0));
                    });
                });
                var topY = 14;
                var zeroY = 54;
                var bottomY = 94;
                var halfHeight = zeroY - topY;
                var barWidth = 4;
                var step = items.length > 0 ? (100 / items.length) : 100;
                var blueBars = [];
                var redBars = [];
                var netPoints = [];
                var axisLabels = [];

                items.forEach(function(item, index) {
                    var center = (step * index) + (step / 2);
                    var segments = item.segments || [];
                    var newSegment = segments[0] || {value: '0', percent: 0, status: 'info'};
                    var deactivatedSegment = segments[1] || {value: '0', percent: 0, status: 'danger'};
                    var netSegment = segments[2] || {value: '0', percent: 0, status: 'ok'};
                    var newHeight = ((Number(newSegment.percent) || 0) / 100) * halfHeight;
                    var deactivatedHeight = ((Number(deactivatedSegment.percent) || 0) / 100) * halfHeight;
                    var netValue = Number(netSegment.value) || 0;
                    var netHeight = ((Number(netSegment.percent) || 0) / 100) * halfHeight;
                    var netY = zeroY - (netValue >= 0 ? netHeight : (-1 * netHeight));

                    blueBars.push('<rect x="' + (center - barWidth - 0.5).toFixed(1) + '" y="' + (zeroY - newHeight).toFixed(1)
                        + '" width="' + barWidth + '" height="' + Math.max(2, newHeight).toFixed(1)
                        + '" rx="1.2" class="da-turnover-bar-positive"></rect>');
                    redBars.push('<rect x="' + (center + 0.5).toFixed(1) + '" y="' + zeroY.toFixed(1)
                        + '" width="' + barWidth + '" height="' + Math.max(2, deactivatedHeight).toFixed(1)
                        + '" rx="1.2" class="da-turnover-bar-negative"></rect>');
                    netPoints.push(center.toFixed(1) + ',' + netY.toFixed(1));
                    axisLabels.push('<div class="da-turnover-axis-label" style="left:' + center.toFixed(1) + '%">' + escapeHtml(item.label) + '</div>');
                });

                body = '<div class="da-turnover-combo-wrap">'
                    + '<div class="da-turnover-combo-chart">'
                    + '<svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">'
                    + '<line x1="0" y1="' + zeroY + '" x2="100" y2="' + zeroY + '" class="da-turnover-zero-line"></line>'
                    + blueBars.join('')
                    + redBars.join('')
                    + '<polyline points="' + escapeHtml(netPoints.join(' ')) + '" class="da-turnover-net-line"></polyline>'
                    + '</svg>'
                    + '<div class="da-turnover-axis-labels">' + axisLabels.join('') + '</div>'
                    + '</div>'
                    + '<div class="da-turnover-legend">' + turnoverLegend + '</div>'
                    + '</div>';
            } else if (panel.type === 'turnoverbars') {
                body = '<div class="da-turnover-bars-wrap">'
                    + '<div class="da-turnover-rate-list">' + items.map(function(item) {
                        var width = Math.max(8, Math.min(100, Number(item.percent) || 0));
                        return '<div class="da-turnover-rate-row">'
                            + '<div class="da-turnover-rate-label">' + escapeHtml(item.label) + '</div>'
                            + '<div class="da-turnover-rate-track"><span class="da-turnover-rate-fill da-bar-fill-' + escapeHtml(item.status)
                            + '" style="width:' + width + '%"><span class="da-turnover-rate-value">' + escapeHtml(item.value) + '</span></span></div>'
                            + '</div>';
                    }).join('') + '</div>'
                    + '<div class="da-turnover-rate-legend">'
                    + '<span class="da-turnover-legend-item"><span class="da-dot da-dot-ok"></span>' + escapeHtml(text('turnoverGood', '<5% Good')) + '</span>'
                    + '<span class="da-turnover-legend-item"><span class="da-dot da-dot-warning"></span>' + escapeHtml(text('turnoverMonitor', '5–10% Monitor')) + '</span>'
                    + '<span class="da-turnover-legend-item"><span class="da-dot da-dot-danger"></span>' + escapeHtml(text('turnoverHigh', '>10% High')) + '</span>'
                    + '</div>'
                    + '<div class="da-turnover-rate-formula">' + escapeHtml(text('turnoverFormula', 'Formula: Deactivated / Avg active × 100')) + '</div>'
                    + '</div>';
            } else if (panel.key === 'newhirerisk') {
                body = '<div class="da-newhire-risk-wrap">'
                    + '<div class="da-newhire-risk-list">' + items.map(function(item) {
                        var width = Math.max(0, Math.min(100, Number(item.percent) || 0));
                        var segments = item.segments || [];
                        var riskSegment = segments[0] || {value: '0', label: 'at risk', status: item.status};
                        return '<div class="da-newhire-risk-row">'
                            + '<div class="da-newhire-risk-left">'
                            + '<div class="da-newhire-risk-company">' + escapeHtml(item.label) + '</div>'
                            + '<span class="da-newhire-risk-badge da-newhire-risk-badge-' + escapeHtml(riskSegment.status || item.status) + '">'
                            + escapeHtml((riskSegment.value || '0') + ' ' + (riskSegment.label || 'at risk')) + '</span>'
                            + '</div>'
                            + '<div class="da-newhire-risk-track"><span class="da-newhire-risk-fill da-bar-fill-' + escapeHtml(item.status)
                            + '" style="width:' + width + '%"></span></div>'
                            + '<div class="da-newhire-risk-value da-text-' + escapeHtml(item.status) + '">' + escapeHtml(item.value) + '</div>'
                            + '</div>';
                    }).join('') + '</div>'
                    + '<div class="da-turnover-rate-formula">' + escapeHtml(panel.description || '') + '</div>'
                    + '</div>';
            } else if (panel.type === 'activitysnapshot') {
                var metrics = items.slice(0, 4);
                var courses = items.slice(4);
                body = '<div class="da-activity-snapshot-grid">' + metrics.map(function(item) {
                    return '<article class="da-activity-snapshot-card da-activity-snapshot-card-' + escapeHtml(item.status) + '">'
                        + '<strong class="da-activity-snapshot-value da-text-' + escapeHtml(item.status) + '">' + escapeHtml(item.value) + '</strong>'
                        + '<span class="da-activity-snapshot-label">' + escapeHtml(item.label) + '</span>'
                        + '<span class="da-activity-snapshot-meta">' + escapeHtml(item.meta || '') + '</span>'
                        + '</article>';
                }).join('') + '</div>'
                    + '<div class="da-activity-courses">'
                    + '<h6>' + escapeHtml(text('topActiveCourses', 'Top active courses this month')) + '</h6>'
                    + '<div class="da-activity-course-list">' + courses.map(function(item) {
                        var width = Math.max(0, Math.min(100, Number(item.percent) || 0));
                        return '<div class="da-activity-course-row">'
                            + '<span class="da-activity-course-name">' + escapeHtml(item.label) + '</span>'
                            + '<div class="da-activity-course-progress"><span class="da-bar-fill da-bar-fill-' + escapeHtml(item.status) + '" style="width:' + width + '%"></span></div>'
                            + '<strong class="da-activity-course-value">' + escapeHtml(item.value) + '</strong>'
                            + '</div>';
                    }).join('') + '</div></div>';
            } else if (panel.type === 'companyhealth') {
                var statusLabel = function(token) {
                    if (token === 'healthy') {
                        return text('healthyLabel', 'Healthy');
                    }
                    if (token === 'atrisk') {
                        return text('atRiskLabel', 'At risk');
                    }
                    if (token === 'critical') {
                        return text('criticalState', 'Critical');
                    }
                    if (token === 'onboarding') {
                        return text('onboardingLabel', 'Onboarding');
                    }
                    return token;
                };
                body = '<div class="da-company-health-table-wrap"><table class="da-table da-company-health-table">'
                    + '<thead><tr>'
                    + '<th scope="col">' + escapeHtml(text('companyHeader', 'Company')) + '</th>'
                    + '<th scope="col">' + escapeHtml(text('usersHeader', 'Users')) + '</th>'
                    + '<th scope="col">' + escapeHtml(text('complianceHeader', 'Compliance')) + '</th>'
                    + '<th scope="col">' + escapeHtml(text('turnoverHeader', 'Turnover')) + '</th>'
                    + '<th scope="col">' + escapeHtml(text('trustScoreHeader', 'Trust score')) + '</th>'
                    + '<th scope="col">' + escapeHtml(text('completionHeader', 'Completion')) + '</th>'
                    + '<th scope="col">' + escapeHtml(text('statusHeader', 'Status')) + '</th>'
                    + '<th scope="col"></th>'
                    + '</tr></thead><tbody>'
                    + items.map(function(item) {
                        var segments = item.segments || [];
                        var compliance = segments[0] || {value: '—', status: 'muted'};
                        var trust = segments[1] || {value: '—', status: 'muted'};
                        var completion = segments[2] || {value: '—', status: 'muted'};
                        var action = segments[3] || {value: 'Report', status: 'info'};
                        var companyIdSegment = segments[4] || {value: ''};
                        return '<tr>'
                            + '<td><span class="da-company-health-name">' + escapeHtml(item.label) + '</span></td>'
                            + '<td>' + escapeHtml(item.value) + '</td>'
                            + '<td><span class="da-company-health-number da-text-' + escapeHtml(compliance.status) + '">' + escapeHtml(compliance.value) + '</span></td>'
                            + '<td><span class="da-company-health-number">' + escapeHtml(item.meta || '—') + '</span></td>'
                            + '<td><span class="da-company-health-number da-text-' + escapeHtml(trust.status) + '">' + escapeHtml(trust.value) + '</span></td>'
                            + '<td><span class="da-company-health-number da-text-' + escapeHtml(completion.status) + '">' + escapeHtml(completion.value) + '</span></td>'
                            + '<td><span class="da-badge da-badge-' + escapeHtml(item.status) + '">' + escapeHtml(statusLabel(item.status)) + '</span></td>'
                            + '<td><button type="button" class="da-row-action" data-action="company-summary-modal" data-company="'
                                + escapeHtml(item.label) + '" data-companyid="' + escapeHtml(companyIdSegment.value || '') + '">'
                                + escapeHtml(action.value || text('reportLabel', 'Report')) + '</button></td>'
                            + '</tr>';
                    }).join('')
                    + '</tbody></table></div>';
            } else if (panel.type === 'alerts') {
                body = '<div class="da-alerts-grid">' + items.map(function(item) {
                    var actionAttributes = '';
                    if ((item.value || '') === text('goToServerTab', 'Go to Server tab')) {
                        actionAttributes = ' data-action="goto-tab" data-tab="server"';
                    }
                    return '<article class="da-alert-card da-alert-card-' + escapeHtml(item.status) + '">'
                        + '<div class="da-alert-card-title da-text-' + escapeHtml(item.status) + '">' + escapeHtml(item.label) + '</div>'
                        + '<div class="da-alert-card-meta">' + escapeHtml(item.meta || '') + '</div>'
                        + '<div class="da-alert-card-actions"><button type="button" class="da-row-action"' + actionAttributes + '>'
                            + escapeHtml(item.value || '') + '</button></div>'
                        + '</article>';
                }).join('') + '</div>';
            } else if (panel.type === 'servergauges') {
                body = '<div class="da-server-thresholds">'
                    + '<span class="da-server-threshold-label">' + escapeHtml(text('warningThreshold', 'Warning threshold:')) + '</span>'
                    + '<span class="da-server-threshold-pill da-server-threshold-pill-warning">70%</span>'
                    + '<span class="da-server-threshold-label">' + escapeHtml(text('criticalThreshold', 'Critical threshold:')) + '</span>'
                    + '<span class="da-server-threshold-pill da-server-threshold-pill-danger">90%</span>'
                    + '</div>'
                    + '<div class="da-server-capacity-grid">' + items.map(function(item) {
                        var percent = Math.max(0, Math.min(100, Number(item.percent) || 0));
                        return '<article class="da-server-capacity-card da-server-capacity-card-' + escapeHtml(item.status) + '">'
                            + '<div class="da-server-capacity-head">'
                            + '<strong>' + escapeHtml(item.label) + '</strong>'
                            + '<span class="da-server-capacity-value da-text-' + escapeHtml(item.status) + '">' + escapeHtml(item.value) + '</span>'
                            + '</div>'
                            + '<div class="da-server-capacity-track"><span class="da-server-capacity-fill da-bar-fill-' + escapeHtml(item.status) + '" style="width:' + percent + '%"></span></div>'
                            + '<div class="da-server-capacity-meta">' + escapeHtml(item.meta) + '</div>'
                            + '</article>';
                    }).join('') + '</div>';
            } else if (panel.type === 'serverforecast') {
                var forecast = items[0] || {segments: [], value: '', status: 'muted', meta: ''};
                var points = (forecast.segments || []).map(function(segment, index, segments) {
                    var x = segments.length <= 1 ? 0 : (index / (segments.length - 1)) * 100;
                    var y = 100 - Math.max(0, Math.min(100, Number(segment.percent) || 0));
                    return {x: x, y: y, status: segment.status || 'historical', label: segment.label || ''};
                });
                var historical = points.filter(function(point) {
                    return point.status === 'historical';
                });
                var projected = points.filter(function(point) {
                    return point.status === 'projected';
                });
                var historicalLine = historical.map(function(point) {
                    return point.x.toFixed(1) + ',' + point.y.toFixed(1);
                }).join(' ');
                var projectedLine = [];
                if (projected.length) {
                    if (historical.length) {
                        projectedLine.push(historical[historical.length - 1].x.toFixed(1) + ',' + historical[historical.length - 1].y.toFixed(1));
                    }
                    projectedLine = projectedLine.concat(projected.map(function(point) {
                        return point.x.toFixed(1) + ',' + point.y.toFixed(1);
                    }));
                }
                var areaPoints = projectedLine.length
                    ? projectedLine + ' 100,100 ' + (historical.length ? historical[historical.length - 1].x.toFixed(1) : '0') + ',100'
                    : '';
                body = '<div class="da-server-forecast-wrap">'
                    + '<div class="da-server-thresholds da-server-thresholds-tight">'
                    + '<span class="da-server-threshold-label">' + escapeHtml(text('criticalThreshold', 'Critical threshold:')) + '</span>'
                    + '<span class="da-server-threshold-pill da-server-threshold-pill-danger">90%</span>'
                    + '</div>'
                    + '<div class="da-server-forecast-canvas">'
                    + '<svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">'
                    + '<line x1="0" y1="10" x2="100" y2="10" class="da-server-forecast-threshold"></line>'
                    + (areaPoints ? '<polygon points="' + escapeHtml(areaPoints) + '" class="da-server-forecast-area"></polygon>' : '')
                    + (historicalLine ? '<polyline points="' + escapeHtml(historicalLine) + '" class="da-server-forecast-historical"></polyline>' : '')
                    + (projectedLine.length ? '<polyline points="' + escapeHtml(projectedLine.join(' ')) + '" class="da-server-forecast-projected"></polyline>' : '')
                    + '</svg>'
                    + '<div class="da-server-forecast-annotation da-text-' + escapeHtml(forecast.status || 'muted') + '">' + escapeHtml(forecast.value || '') + '</div>'
                    + '</div>'
                    + '<div class="da-server-forecast-meta">' + escapeHtml(forecast.meta || '') + '</div>'
                    + '</div>';
            } else if (panel.type === 'compliancetrendchart') {
                var monthLabels = ((items[0] || {}).segments || []).map(function(segment) {
                    return segment.label || '';
                });
                var chartLeft = 18;
                var chartRight = 4;
                var chartTop = 14;
                var chartBottom = 18;
                var chartWidth = 100 - chartLeft - chartRight;
                var chartHeight = 100 - chartTop - chartBottom;
                var xForIndex = function(index, total) {
                    if (total <= 1) {
                        return chartLeft;
                    }

                    return chartLeft + ((index / (total - 1)) * chartWidth);
                };
                var yForPercent = function(percent) {
                    var safePercent = Math.max(0, Math.min(100, Number(percent) || 0));
                    return chartTop + ((100 - safePercent) / 100) * chartHeight;
                };
                var xLabels = monthLabels.map(function(label, index) {
                    var shouldShow = index === 0 || index === monthLabels.length - 1 || index % 3 === 0;
                    if (!shouldShow) {
                        return '';
                    }

                    var left = xForIndex(index, monthLabels.length);
                    return '<span class="da-compliance-trend-x-label" style="left:' + left + '%">' + escapeHtml(label) + '</span>';
                }).join('');
                var trendSeries = items.map(function(item) {
                    var points = (item.segments || []).map(function(segment, index, segments) {
                        var x = xForIndex(index, segments.length);
                        var y = yForPercent(segment.percent);
                        return x.toFixed(1) + ',' + y.toFixed(1);
                    }).join(' ');
                    var firstSegment = (item.segments || [])[0] || {percent: 0};
                    var firstX = xForIndex(0, (item.segments || []).length || 1);
                    var labelY = Math.max(chartTop + 1, Math.min(100 - chartBottom - 3, yForPercent(firstSegment.percent) - 4));
                    return {
                        path: '<polyline points="' + escapeHtml(points) + '" class="da-compliance-trend-path da-compliance-trend-path-' + escapeHtml(item.status) + '"></polyline>',
                        points: (item.segments || []).map(function(segment, index, segments) {
                            return '<circle cx="' + xForIndex(index, segments.length).toFixed(1) + '" cy="' + yForPercent(segment.percent).toFixed(1) + '" r="0.7" class="da-compliance-trend-point da-compliance-trend-point-' + escapeHtml(item.status) + '"></circle>';
                        }).join(''),
                        labelLeft: firstX,
                        labelTop: labelY,
                        labelText: item.label + ' ' + item.value,
                        labelStatus: item.status
                    };
                });
                var sortedLabels = trendSeries.map(function(series, index) {
                    return {
                        index: index,
                        top: series.labelTop
                    };
                }).sort(function(a, b) {
                    return a.top - b.top;
                });
                var minLabelGap = 8;
                sortedLabels.forEach(function(entry, index) {
                    if (index === 0) {
                        return;
                    }

                    var previous = sortedLabels[index - 1];
                    if (entry.top - previous.top < minLabelGap) {
                        entry.top = previous.top + minLabelGap;
                    }
                });
                for (var labelIndex = sortedLabels.length - 2; labelIndex >= 0; labelIndex--) {
                    var currentLabel = sortedLabels[labelIndex];
                    var nextLabel = sortedLabels[labelIndex + 1];
                    if (nextLabel.top > 92) {
                        currentLabel.top = Math.min(currentLabel.top, nextLabel.top - minLabelGap);
                    }
                }
                sortedLabels.forEach(function(entry) {
                    trendSeries[entry.index].labelTop = Math.max(chartTop + 1, Math.min(92, entry.top));
                });
                var referenceY = yForPercent(80);
                body = '<div class="da-compliance-trend-wrap">'
                    + '<div class="da-compliance-trend-chart">'
                    + '<svg class="da-compliance-trend-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">'
                    + '<line x1="' + chartLeft + '" y1="' + referenceY.toFixed(1) + '" x2="' + (100 - chartRight) + '" y2="' + referenceY.toFixed(1) + '" class="da-compliance-trend-reference"></line>'
                    + trendSeries.map(function(series) { return series.path; }).join('')
                    + trendSeries.map(function(series) { return series.points; }).join('')
                    + '</svg>'
                    + trendSeries.map(function(series) {
                        return '<span class="da-compliance-trend-series-label da-text-' + escapeHtml(series.labelStatus) + '" style="left:' + series.labelLeft.toFixed(1) + '%; top:' + series.labelTop.toFixed(1) + '%">'
                            + escapeHtml(series.labelText) + '</span>';
                    }).join('')
                    + '<span class="da-compliance-trend-target-label" style="top:' + Math.max(2, referenceY - 4).toFixed(1) + '%">80% target</span>'
                    + '<div class="da-compliance-trend-x-axis">' + xLabels + '</div>'
                    + '</div>'
                    + '</div>';
            } else if (panel.type === 'compliancesnapshot') {
                body = '<div class="da-compliance-snapshot-wrap">'
                    + '<div class="da-compliance-snapshot-list">' + items.map(function(item) {
                        var width = Math.max(0, Math.min(100, Number(item.percent) || 0));
                        return '<div class="da-compliance-snapshot-row">'
                            + '<div class="da-compliance-snapshot-label">' + escapeHtml(item.label) + '</div>'
                            + '<div class="da-compliance-snapshot-track">'
                            + '<span class="da-compliance-snapshot-reference" style="left:80%"></span>'
                            + '<span class="da-compliance-snapshot-fill da-bar-fill-' + escapeHtml(item.status) + '" style="width:' + width + '%">'
                            + '<span class="da-compliance-snapshot-fill-value">' + escapeHtml(item.value) + '</span>'
                            + '</span>'
                            + '</div>'
                            + '<div class="da-compliance-snapshot-value da-text-' + escapeHtml(item.status) + '">' + escapeHtml(item.value) + '</div>'
                            + '</div>';
                    }).join('') + '</div>'
                    + '<div class="da-compliance-snapshot-legend">'
                    + '<span class="da-turnover-legend-item"><span class="da-dot da-dot-ok"></span>&ge;80% Compliant</span>'
                    + '<span class="da-turnover-legend-item"><span class="da-dot da-dot-warning"></span>70-79% At risk</span>'
                    + '<span class="da-turnover-legend-item"><span class="da-dot da-dot-danger"></span>&lt;70% Critical</span>'
                    + '</div>'
                    + '</div>';
            } else if (panel.type === 'servererrors') {
                body = '<div class="da-server-error-list">' + items.map(function(item) {
                    return '<div class="da-server-error-row da-server-error-row-' + escapeHtml(item.status) + '">'
                        + '<div class="da-server-error-label">' + escapeHtml(item.label) + '</div>'
                        + '<div class="da-server-error-count da-text-' + escapeHtml(item.status) + '">' + escapeHtml(item.value) + '</div>'
                        + '<div class="da-server-error-meta">' + escapeHtml(item.meta) + '</div>'
                        + '<div class="da-server-error-state da-text-' + escapeHtml(item.status) + '">' + escapeHtml(item.status === 'ok' ? text('clearState', 'Clear') : (item.status === 'danger' ? text('criticalState', 'Critical') : text('monitorState', 'Monitor'))) + '</div>'
                        + '</div>';
                }).join('') + '</div>';
            } else if (panel.type === 'serversettings') {
                var summary = items.reduce(function(result, item) {
                    if (item.status === 'ok') {
                        result.ok++;
                    } else if (item.status === 'warning' || item.status === 'danger') {
                        result.warning++;
                    } else {
                        result.check++;
                    }
                    return result;
                }, {ok: 0, warning: 0, check: 0});
                body = '<div class="da-server-settings-wrap">'
                    + '<div class="da-server-settings-badges">'
                    + '<span class="da-badge da-badge-ok">' + escapeHtml(String(summary.ok) + ' ' + text('okSummary', 'OK')) + '</span>'
                    + '<span class="da-badge da-badge-warning">' + escapeHtml(String(summary.warning) + ' ' + text('warningSummary', 'Warning')) + '</span>'
                    + '<span class="da-badge da-badge-info">' + escapeHtml(String(summary.check) + ' ' + text('checkSummary', 'Check')) + '</span>'
                    + '</div>'
                    + '<div class="da-server-settings-table">' + items.map(function(item) {
                        return '<div class="da-server-settings-row">'
                            + '<div class="da-server-settings-label">' + escapeHtml(item.label) + '</div>'
                            + '<div class="da-server-settings-value">' + escapeHtml(item.value) + '</div>'
                            + '<div class="da-server-settings-status"><span class="da-badge da-badge-' + escapeHtml(item.status) + '">' + escapeHtml(item.meta) + '</span></div>'
                            + '</div>';
                    }).join('') + '</div>'
                    + '</div>';
            } else if (panel.type === 'line') {
                body = '<div class="da-line-chart">' + items.map(function(item) {
                    var segments = item.segments || [];
                    var points = segments.map(function(segment, index) {
                        var x = segments.length <= 1 ? 0 : (index / (segments.length - 1)) * 100;
                        var y = 100 - Math.max(0, Math.min(100, Number(segment.percent) || 0));
                        return x.toFixed(1) + ',' + y.toFixed(1);
                    }).join(' ');
                    var ticks = segments.map(function(segment) {
                        return '<span title="' + escapeHtml(segment.label + ': ' + segment.value + '%') + '"></span>';
                    }).join('');
                    return '<div class="da-line-row">'
                        + '<div class="da-line-label"><span>' + escapeHtml(item.label) + '</span><strong>' + escapeHtml(item.value) + '</strong></div>'
                        + '<svg class="da-line-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">'
                        + '<line x1="0" y1="20" x2="100" y2="20" class="da-line-reference"></line>'
                        + '<polyline points="' + escapeHtml(points) + '" class="da-line-path da-line-path-' + escapeHtml(item.status) + '"></polyline>'
                        + '</svg>'
                        + '<div class="da-line-ticks">' + ticks + '</div>'
                        + '<div class="da-bar-meta">' + escapeHtml(item.meta) + '</div>'
                        + '</div>';
                }).join('') + '</div>';
            } else if (panel.type === 'cards') {
                body = '<div class="da-mini-cards">' + items.map(function(item) {
                    var width = Math.max(0, Math.min(100, Number(item.percent) || 0));
                    var progress = width > 0 ? '<div class="da-mini-card-progress"><span class="da-mini-card-progress-fill da-bar-fill-' + escapeHtml(item.status) + '" style="width:' + width + '%"></span></div>' : '';
                    return '<div class="da-mini-card da-mini-card-' + escapeHtml(item.status) + '">'
                        + '<span>' + escapeHtml(item.label) + '</span>'
                        + '<strong>' + escapeHtml(item.value) + '</strong>'
                        + progress
                        + '<em>' + escapeHtml(item.meta) + '</em>'
                        + '</div>';
                }).join('') + '</div>';
            } else if (panel.type === 'donut') {
                body = '<div class="da-donut-wrap">'
                    + '<canvas class="da-donut-canvas" width="180" height="180" data-donut="' + escapeHtml(panel.key) + '"></canvas>'
                    + '<div class="da-donut-list">' + items.map(function(item) {
                        return '<div class="da-donut-row">'
                            + '<span class="da-dot da-dot-' + escapeHtml(item.status) + '"></span>'
                            + '<span>' + escapeHtml(item.label) + '</span>'
                            + '<strong>' + escapeHtml(item.value) + '</strong>'
                            + '<em>' + escapeHtml(item.meta) + '</em>'
                            + '</div>';
                    }).join('') + '</div>'
                    + '</div>';
            } else if (panel.type === 'histogram') {
                body = '<div class="da-histogram">' + items.map(function(item) {
                    var height = Math.max(5, Math.min(100, Number(item.percent) || 0));
                    return '<div class="da-histogram-bar">'
                        + '<strong>' + escapeHtml(item.value) + '</strong>'
                        + '<span class="da-histogram-fill da-bar-fill-' + escapeHtml(item.status) + '" style="height:' + height + '%"></span>'
                        + '<em>' + escapeHtml(item.label) + '</em>'
                        + '</div>';
                }).join('') + '</div>';
            } else if (panel.type === 'grouped') {
                body = '<div class="da-grouped-bars">' + items.map(function(item) {
                    var segments = item.segments || [];
                    return '<div class="da-grouped-row">'
                        + '<div class="da-grouped-label">' + escapeHtml(item.label) + '</div>'
                        + '<div class="da-grouped-series">' + segments.map(function(segment) {
                            var width = Math.max(2, Math.min(100, Number(segment.percent) || 0));
                            return '<div class="da-grouped-segment">'
                                + '<span class="da-grouped-fill da-bar-fill-' + escapeHtml(segment.status) + '" style="width:' + width + '%"></span>'
                                + '<small>' + escapeHtml(segment.label) + ': ' + escapeHtml(segment.value) + '</small>'
                                + '</div>';
                        }).join('') + '</div>'
                        + '</div>';
                }).join('') + '</div>';
            } else if (panel.type === 'stacked') {
                body = '<div class="da-stacked-bars">' + items.map(function(item) {
                    var segments = item.segments || [];
                    return '<div class="da-stacked-row">'
                        + '<div class="da-bar-label"><span>' + escapeHtml(item.label) + '</span><strong>' + escapeHtml(item.value) + '</strong></div>'
                        + '<div class="da-stacked-track">' + segments.map(function(segment) {
                            var width = Math.max(0, Math.min(100, Number(segment.percent) || 0));
                            return '<span class="da-stacked-segment da-bar-fill-' + escapeHtml(segment.status) + '" style="width:' + width + '%" title="'
                                + escapeHtml(segment.label) + ': ' + escapeHtml(segment.value) + '"></span>';
                        }).join('') + '</div>'
                        + '<div class="da-bar-meta">' + segments.map(function(segment) {
                            return escapeHtml(segment.label) + ' ' + escapeHtml(segment.value);
                        }).join(' · ') + '</div>'
                        + '</div>';
                }).join('') + '</div>';
            } else {
                body = '<div class="da-bars">' + items.map(function(item) {
                    var width = Math.max(0, Math.min(100, Number(item.percent) || 0));
                    return '<div class="da-bar-row">'
                        + '<div class="da-bar-label"><span>' + escapeHtml(item.label) + '</span><strong>' + escapeHtml(item.value) + '</strong></div>'
                        + '<div class="da-bar-track"><div class="da-bar-fill da-bar-fill-' + escapeHtml(item.status) + '" style="width:' + width + '%"></div></div>'
                        + '<div class="da-bar-meta">' + escapeHtml(item.meta) + '</div>'
                        + '</div>';
                }).join('') + '</div>';
            }

            return '<article class="da-visual-panel' + (isFullRowVisualPanel(panel) ? ' da-visual-panel-fullrow' : '') + '" data-panel-key="' + escapeHtml(panel.key) + '">'
                + '<h5>' + escapeHtml(panel.title) + '</h5>'
                + '<p>' + escapeHtml(panel.description) + '</p>'
                + body
                + '</article>';
        }).join('') + '</div>';

        drawDoughnuts(root, panels);
    };

    var colorForStatus = function(status) {
        var map = {
            ok: '#639922',
            green: '#639922',
            warning: '#EF9F27',
            amber: '#EF9F27',
            danger: '#E24B4A',
            red: '#E24B4A',
            muted: '#B4B2A9',
            info: '#378ADD'
        };
        return map[status] || '#378ADD';
    };

    var drawDoughnuts = function(root, panels) {
        panels.forEach(function(panel) {
            if (panel.type !== 'donut') {
                return;
            }

            var canvas = root.querySelector('[data-donut="' + panel.key + '"]');
            if (!canvas || !canvas.getContext) {
                return;
            }

            var ctx = canvas.getContext('2d');
            var items = (panel.items || []).filter(function(item) {
                return Number(item.value) > 0 || Number(item.percent) > 0;
            });
            var total = items.reduce(function(sum, item) {
                return sum + Math.max(0, Number(item.value) || Number(item.percent) || 0);
            }, 0);

            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.lineWidth = 26;
            ctx.lineCap = 'butt';

            var cx = canvas.width / 2;
            var cy = canvas.height / 2;
            var radius = 58;

            if (!total) {
                ctx.beginPath();
                ctx.strokeStyle = '#edf1f6';
                ctx.arc(cx, cy, radius, 0, Math.PI * 2);
                ctx.stroke();
                ctx.fillStyle = '#5d6878';
                ctx.font = '600 14px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(text('noData', 'No data'), cx, cy);
                return;
            }

            var start = -Math.PI / 2;
            items.forEach(function(item) {
                var value = Math.max(0, Number(item.value) || Number(item.percent) || 0);
                var end = start + (value / total) * Math.PI * 2;
                ctx.beginPath();
                ctx.strokeStyle = colorForStatus(item.status);
                ctx.arc(cx, cy, radius, start, end);
                ctx.stroke();
                start = end;
            });

            ctx.fillStyle = '#1f2937';
            ctx.font = '700 22px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(String(Math.round(total)), cx, cy - 4);
            ctx.fillStyle = '#5d6878';
            ctx.font = '500 11px sans-serif';
            ctx.fillText(text('total', 'total'), cx, cy + 17);
        });
    };

    var modalRoot = function() {
        var existing = document.querySelector('[data-region="da-company-summary-modal"]');
        if (existing) {
            return existing;
        }

        var node = document.createElement('div');
        node.className = 'da-company-summary-modal-root';
        node.setAttribute('data-region', 'da-company-summary-modal');
        node.hidden = true;
        document.body.appendChild(node);
        return node;
    };

    var closeCompanySummaryModal = function() {
        var root = modalRoot();
        root.hidden = true;
        root.innerHTML = '';
        document.body.classList.remove('da-modal-open');
    };

    var renderCompanySummaryModal = function(data) {
        var root = modalRoot();
        var summaryCards = (data.summarycards || []).map(function(card) {
            return '<article class="da-company-summary-stat da-company-summary-stat-' + escapeHtml(card.status) + '">'
                + '<strong class="da-company-summary-stat-value da-text-' + escapeHtml(card.status) + '">' + escapeHtml(card.value) + '</strong>'
                + '<span class="da-company-summary-stat-label">' + escapeHtml(card.label) + '</span>'
                + '</article>';
        }).join('');

        var courseRows = (data.courseitems || []).map(function(item) {
            var width = Math.max(0, Math.min(100, Number(item.percent) || 0));
            return '<div class="da-company-summary-course-row">'
                + '<span class="da-company-summary-course-name">' + escapeHtml(item.label) + '</span>'
                + '<div class="da-company-summary-course-track"><span class="da-company-summary-course-fill da-bar-fill-' + escapeHtml(item.status) + '" style="width:' + width + '%"></span></div>'
                + '<strong class="da-company-summary-course-value da-text-' + escapeHtml(item.status) + '">' + escapeHtml(item.value) + '</strong>'
                + '</div>';
        }).join('');

        var additionalCards = (data.additionalcards || []).map(function(card) {
            return '<article class="da-company-summary-stat da-company-summary-stat-' + escapeHtml(card.status) + '">'
                + '<strong class="da-company-summary-stat-value da-text-' + escapeHtml(card.status) + '">' + escapeHtml(card.value) + '</strong>'
                + '<span class="da-company-summary-stat-label">' + escapeHtml(card.label) + '</span>'
                + '</article>';
        }).join('');

        root.innerHTML = '<div class="da-company-summary-backdrop" data-action="close-company-summary"></div>'
            + '<section class="da-company-summary-modal" role="dialog" aria-modal="true" aria-label="' + escapeHtml(data.title || '') + '">'
            + '<header class="da-company-summary-header">'
            + '<div><h3>' + escapeHtml(data.title || '') + '</h3><p>' + escapeHtml(data.subtitle || '') + '</p></div>'
            + '<button type="button" class="da-company-summary-close" data-action="close-company-summary" aria-label="' + escapeHtml(data.closebutton || 'Close') + '">×</button>'
            + '</header>'
            + '<div class="da-company-summary-body">'
            + '<div class="da-company-summary-grid">' + summaryCards + '</div>'
            + '<section class="da-company-summary-section"><h4>' + escapeHtml(data.courseheading || '') + '</h4><div class="da-company-summary-course-list">' + courseRows + '</div></section>'
            + '<section class="da-company-summary-section"><h4>' + escapeHtml(data.metricsheading || '') + '</h4><div class="da-company-summary-grid da-company-summary-grid-secondary">' + additionalCards + '</div></section>'
            + '<footer class="da-company-summary-footer"><div class="da-company-summary-status">'
            + '<span>' + escapeHtml(text('statusHeader', 'Status')) + ': </span>'
            + '<span class="da-badge da-badge-' + escapeHtml(data.statuskey || 'muted') + '">' + escapeHtml(data.statuslabel || '') + '</span>'
            + '</div><div class="da-company-summary-actions">'
            + '<button type="button" class="da-row-action" data-action="close-company-summary">' + escapeHtml(data.closebutton || 'Close') + '</button>'
            + '<button type="button" class="da-row-action da-company-summary-export" data-action="company-summary-export">' + escapeHtml(data.exportbutton || '') + '</button>'
            + '</div></footer>'
            + '</div></section>';
        root.hidden = false;
        document.body.classList.add('da-modal-open');
    };

    var loadCompanySummaryModal = function(root, state, companyName, companyId) {
        var modal = modalRoot();
        modal.hidden = false;
        modal.innerHTML = '<div class="da-company-summary-backdrop"></div><section class="da-company-summary-modal da-company-summary-modal-loading"><div class="da-loading">'
            + escapeHtml(text('loading', 'Loading...')) + '</div></section>';
        document.body.classList.add('da-modal-open');

        return call('block_dashboardanalytics_get_company_summary_modal', {
            contextid: state.contextid,
            dashboardkey: state.dashboardkey,
            companyname: companyName,
            companyid: Number(companyId) || 0,
            filters: JSON.stringify(readFilters(root, state, state.currentVisualOverrides || {}))
        }).then(function(response) {
            renderCompanySummaryModal(response);
        }).catch(function(error) {
            closeCompanySummaryModal();
            Notification.exception(error);
        });
    };

    var loadFilters = function(root, state) {
        var container = root.querySelector('[data-region="filter-bar"]');
        setLoading(container);

        return call('block_dashboardanalytics_get_filter_options', {
            contextid: state.contextid
        }).then(function(response) {
            renderFilters(root, state, response.groups || []);
            persistState(root, state);
        }).catch(Notification.exception);
    };

    var loadKpis = function(root, state) {
        var container = root.querySelector('[data-region="kpi-strip"]');
        setLoading(container);

        return call('block_dashboardanalytics_get_kpis', {
            contextid: state.contextid,
            dashboardkey: state.dashboardkey,
            filters: JSON.stringify(readFilters(root, state))
        }).then(function(response) {
            renderKpis(root, response.cards || []);
        }).catch(Notification.exception);
    };

    var loadDrilldown = function(root, state, drilldownkey, filterOverrides, page, perpage) {
        var container = root.querySelector('[data-region="drilldown"]');
        setLoading(container);
        var targetPage = typeof page === 'number' ? page : (state.currentDrilldownPage || 0);
        var targetPerPage = typeof perpage === 'number' ? perpage : (state.currentDrilldownPerPage || 20);
        var overrides = typeof filterOverrides !== 'undefined' ? filterOverrides : state.currentDrilldownOverrides;

        return call('block_dashboardanalytics_get_drilldown', {
            contextid: state.contextid,
            dashboardkey: state.dashboardkey,
            drilldownkey: drilldownkey,
            filters: JSON.stringify(readFilters(root, state, overrides)),
            page: targetPage,
            perpage: targetPerPage
        }).then(function(response) {
            state.currentDrilldown = drilldownkey;
            state.currentDrilldownOverrides = overrides || null;
            state.currentDrilldownPage = targetPage;
            state.currentDrilldownPerPage = targetPerPage;
            renderDrilldown(root, response, state);
            persistState(root, state);
        }).catch(Notification.exception);
    };

    var loadVisuals = function(root, state, tabkey, overrides) {
        var container = root.querySelector('[data-region="drilldown"]');
        setLoading(container);
        var visualOverrides = typeof overrides !== 'undefined'
            ? overrides
            : (state.currentVisualOverrides || {});
        var requestFilters = readFilters(root, state, visualOverrides);

        return call('block_dashboardanalytics_get_visuals', {
            contextid: state.contextid,
            dashboardkey: state.dashboardkey,
            tabkey: tabkey,
            filters: JSON.stringify(requestFilters)
        }).then(function(response) {
            state.currentTab = tabkey;
            state.currentVisualOverrides = visualOverrides || {};
            setActiveTab(root, tabkey);
            renderVisuals(root, response, state);
            persistState(root, state);
        }).catch(Notification.exception);
    };

    var refresh = function(root, state) {
        persistState(root, state);
        loadKpis(root, state);
        if (state.currentTab === 'kpis') {
            loadDrilldown(root, state, state.currentDrilldown || defaultDrilldownKey(state));
            return;
        }
        if (state.currentDrilldown) {
            loadDrilldown(root, state, state.currentDrilldown);
            return;
        }
        loadVisuals(root, state, state.currentTab || 'overview');
    };

    var setActiveTab = function(root, tabkey) {
        Array.prototype.slice.call(root.querySelectorAll('[data-tab]')).forEach(function(item) {
            var active = item.getAttribute('data-tab') === tabkey;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    };

    var toggleAddFilterMenu = function(root) {
        var menu = root.querySelector('[data-region="add-filter-menu"]');
        if (!menu) {
            return;
        }
        menu.hidden = !menu.hidden;
    };

    var bindEvents = function(root, state) {
        var timer = null;

        root.addEventListener('change', function(event) {
            if (event.target.matches('select[data-filter-group]')) {
                if (event.target.getAttribute('data-filter-group') === 'daterange') {
                    state.persistedFilters = readFilters(root, state);
                    renderFilters(root, state, Object.keys(state.filterGroups).map(function(key) {
                        return state.filterGroups[key];
                    }));
                }
                state.currentDrilldownPage = 0;
                updateFilterCounts(root, state);
                refresh(root, state);
                return;
            }

            if (event.target.matches('[data-filter-custom]')) {
                state.currentDrilldownPage = 0;
                refresh(root, state);
                return;
            }

            if (event.target.matches('[data-action="drilldown-perpage"]')) {
                state.currentDrilldownPage = 0;
                state.currentDrilldownPerPage = Number(event.target.value) || 20;
                if (state.currentDrilldown) {
                    loadDrilldown(root, state, state.currentDrilldown, undefined, 0, state.currentDrilldownPerPage);
                }
            }
        });

        root.addEventListener('input', function(event) {
            if (!event.target.matches('[data-filter="search"]')) {
                return;
            }
            window.clearTimeout(timer);
            timer = window.setTimeout(function() {
                refresh(root, state);
            }, 350);
        });

        root.addEventListener('click', function(event) {
            var addFilterToggle = event.target.closest('[data-action="toggle-add-filter"]');
            if (addFilterToggle && root.contains(addFilterToggle)) {
                toggleAddFilterMenu(root);
                return;
            }

            var addFilter = event.target.closest('[data-action="add-filter"]');
            if (addFilter && root.contains(addFilter)) {
                var addKey = addFilter.getAttribute('data-filter-key');
                if (addKey && state.activeFilterKeys.indexOf(addKey) === -1) {
                    state.persistedFilters = readFilters(root, state);
                    state.activeFilterKeys.push(addKey);
                    renderFilters(root, state, Object.keys(state.filterGroups).map(function(key) {
                        return state.filterGroups[key];
                    }));
                    refresh(root, state);
                }
                var addMenu = root.querySelector('[data-region="add-filter-menu"]');
                if (addMenu) {
                    addMenu.hidden = true;
                }
                return;
            }

            var removeFilter = event.target.closest('[data-action="remove-filter"]');
            if (removeFilter && root.contains(removeFilter)) {
                var removeKey = removeFilter.getAttribute('data-filter-key');
                state.persistedFilters = readFilters(root, state);
                state.activeFilterKeys = state.activeFilterKeys.filter(function(key) {
                    return key !== removeKey;
                });
                delete state.persistedFilters[removeKey];
                renderFilters(root, state, Object.keys(state.filterGroups).map(function(key) {
                    return state.filterGroups[key];
                }));
                state.currentDrilldownPage = 0;
                refresh(root, state);
                return;
            }

            var clearFilters = event.target.closest('[data-action="clear-filters"]');
            if (clearFilters && root.contains(clearFilters)) {
                Array.prototype.slice.call(root.querySelectorAll('select[data-filter-group]')).forEach(function(select) {
                    select.value = select.getAttribute('data-filter-group') === 'daterange' ? defaultDateRange(state) : '';
                });
                Array.prototype.slice.call(root.querySelectorAll('[data-filter-custom]')).forEach(function(input) {
                    input.value = '';
                });
                state.currentDrilldownPage = 0;
                updateFilterCounts(root, state);
                refresh(root, state);
                return;
            }

            var rowAction = event.target.closest('[data-action="company-report"]');
            if (rowAction && root.contains(rowAction)) {
                var companyid = rowAction.getAttribute('data-companyid');
                var company = rowAction.getAttribute('data-company');
                var overrides = {};

                if (companyid && companyid !== '0') {
                    overrides.companyids = [companyid];
                } else if (company) {
                    overrides.companies = [company];
                }

                overrides.status = '';
                state.currentDrilldown = 'company_compliance';
                state.currentDrilldownPage = 0;
                loadDrilldown(root, state, 'company_compliance', overrides, 0, state.currentDrilldownPerPage || 20);
                return;
            }

            var companySummaryModal = event.target.closest('[data-action="company-summary-modal"]');
            if (companySummaryModal && root.contains(companySummaryModal)) {
                loadCompanySummaryModal(
                    root,
                    state,
                    companySummaryModal.getAttribute('data-company') || '',
                    companySummaryModal.getAttribute('data-companyid') || '0'
                );
                return;
            }

            var gotoTab = event.target.closest('[data-action="goto-tab"]');
            if (gotoTab && root.contains(gotoTab)) {
                var targettab = gotoTab.getAttribute('data-tab');
                if (targettab && root.querySelector('[data-tab="' + targettab + '"]')) {
                    setActiveTab(root, targettab);
                    state.currentTab = targettab;
                    state.currentDrilldown = '';
                    state.currentDrilldownOverrides = null;
                    state.currentDrilldownPage = 0;
                    loadVisuals(root, state, targettab);
                }
                return;
            }

            var platformGrowthPeriod = event.target.closest('[data-action="platformgrowth-period"]');
            if (platformGrowthPeriod && root.contains(platformGrowthPeriod)) {
                state.currentDrilldown = '';
                state.currentDrilldownOverrides = null;
                state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, {
                    platformgrowthperiod: platformGrowthPeriod.getAttribute('data-period') || '1year'
                });
                loadVisuals(root, state, state.currentTab || 'overview', state.currentVisualOverrides);
                return;
            }

            var pager = event.target.closest('[data-action="drilldown-page"]');
            if (pager && root.contains(pager) && !pager.disabled && state.currentDrilldown) {
                loadDrilldown(
                    root,
                    state,
                    state.currentDrilldown,
                    undefined,
                    Number(pager.getAttribute('data-page')) || 0,
                    state.currentDrilldownPerPage || 20
                );
                return;
            }

            var kpi = event.target.closest('[data-drilldown]');
            if (kpi && root.contains(kpi)) {
                state.currentTab = 'kpis';
                setActiveTab(root, 'kpis');
                state.currentDrilldownPage = 0;
                loadDrilldown(root, state, kpi.getAttribute('data-drilldown'), undefined, 0, state.currentDrilldownPerPage || 20);
                return;
            }

            var tab = event.target.closest('[data-tab]');
            if (tab && root.contains(tab)) {
                var tabkey = tab.getAttribute('data-tab');
                setActiveTab(root, tabkey);
                state.currentTab = tabkey;
                state.currentDrilldownPage = 0;
                if (tabkey === 'kpis') {
                    state.currentDrilldown = defaultDrilldownKey(state);
                    loadDrilldown(root, state, state.currentDrilldown, undefined, 0, state.currentDrilldownPerPage || 20);
                    return;
                }
                state.currentDrilldown = '';
                state.currentDrilldownOverrides = null;
                loadVisuals(root, state, tabkey);
                return;
            }

            if (!event.target.closest('[data-region="add-filter-menu"]') && !event.target.closest('[data-action="toggle-add-filter"]')) {
                var menu = root.querySelector('[data-region="add-filter-menu"]');
                if (menu) {
                    menu.hidden = true;
                }
            }

        });

        if (!modalEventsBound) {
            document.addEventListener('click', function(event) {
                if (event.target.closest('[data-action="close-company-summary"]') || event.target.closest('.da-company-summary-backdrop')) {
                    closeCompanySummaryModal();
                    return;
                }

                if (event.target.closest('[data-action="company-summary-export"]')) {
                    window.print();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeCompanySummaryModal();
                }
            });

            modalEventsBound = true;
        }
    };

    var init = function(contextid) {
        var root = document.querySelector(SELECTOR + '[data-contextid="' + contextid + '"]');
        if (!root) {
            return;
        }

        var activeTab = root.querySelector('[data-tab].is-active');
        var state = {
            contextid: contextid,
            dashboardkey: root.getAttribute('data-dashboardkey') || '',
            currentTab: activeTab ? activeTab.getAttribute('data-tab') : 'overview',
            filterGroups: {},
            companyFilterKey: '',
            activeFilterKeys: [],
            persistedFilters: {},
            currentDrilldown: '',
            currentDrilldownPage: 0,
            currentDrilldownPerPage: 20,
            currentDrilldownOverrides: null,
            currentVisualOverrides: {}
        };

        var saved = readSessionState(state);
        state.activeFilterKeys = Array.isArray(saved.activeFilterKeys) ? saved.activeFilterKeys : [];
        state.persistedFilters = saved.filters || {};
        state.currentTab = saved.currentTab || state.currentTab;
        state.currentVisualOverrides = saved.visualOverrides || {};
        if (!root.querySelector('[data-tab="' + state.currentTab + '"]')) {
            state.currentTab = activeTab ? activeTab.getAttribute('data-tab') : 'overview';
        }
        setActiveTab(root, state.currentTab);

        Str.get_strings(stringList).then(function(values) {
            strings.loading = values[0];
            strings.details = values[1];
            strings.rows = values[2];
            strings.noMatchingRows = values[3];
            strings.noKpi = values[4];
            strings.noVisualData = values[5];
            strings.dashboardVisuals = values[6];
            strings.noFilterOptions = values[7];
            strings.allOption = values[8];
            strings.allWithLabel = values[9];
            strings.activeFiltersAll = values[10];
            strings.activeFiltersPrefix = values[11];
            strings.noData = values[12];
            strings.total = values[13];
            strings.addFilter = values[14];
            strings.noAvailableFilters = values[15];
            strings.removeFilter = values[16];
            strings.customStart = values[17];
            strings.customEnd = values[18];
            strings.previous = values[19];
            strings.next = values[20];
            strings.perPage = values[21];
            strings.page = values[22];
            strings.goToServerTab = values[23];
            strings.warningThreshold = values[24];
            strings.criticalThreshold = values[25];
            strings.clearState = values[26];
            strings.monitorState = values[27];
            strings.criticalState = values[28];
            strings.okSummary = values[29];
            strings.warningSummary = values[30];
            strings.checkSummary = values[31];
            strings.topActiveCourses = values[32];
            strings.companyHeader = values[33];
            strings.usersHeader = values[34];
            strings.complianceHeader = values[35];
            strings.turnoverHeader = values[36];
            strings.trustScoreHeader = values[37];
            strings.completionHeader = values[38];
            strings.statusHeader = values[39];
            strings.reportLabel = values[40];
            strings.healthyLabel = values[41];
            strings.atRiskLabel = values[42];
            strings.onboardingLabel = values[43];
            strings.period3Months = values[44];
            strings.period1Year = values[45];
            strings.period2Years = values[46];
            strings.periodAllTime = values[47];
            strings.barChartLabel = values[48];
            strings.interactiveLabel = values[49];
            strings.allLabels = {
                companies: values[50],
                courses: values[51],
                daterange: values[52],
                departments: values[53],
                locations: values[54],
                positions: values[55],
                personnelcategories: values[56],
                sites: values[57],
                educations: values[58]
            };
            strings.comboBarLineLabel = values[59];
            strings.chartJsBarLabel = values[60];
            strings.turnoverFormula = values[61];
            strings.turnoverGood = values[62];
            strings.turnoverMonitor = values[63];
            strings.turnoverHigh = values[64];

            bindEvents(root, state);
            loadFilters(root, state).then(function() {
                updateFilterCounts(root, state);
                refresh(root, state);
            });
        }).catch(Notification.exception);
    };

    return {
        init: init
    };
});
