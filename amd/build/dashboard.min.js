define(['core/ajax', 'core/notification', 'core/str'], function(Ajax, Notification, Str) {
    var SELECTOR = '[data-region="dashboardanalytics"]';
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
        activeFiltersAll: 'Active filters: All ×',
        activeFiltersPrefix: 'Active filters: {$a} ×',
        noData: 'No data',
        total: 'total',
        addFilter: '+ Add filter',
        noAvailableFilters: 'No additional filters available.',
        removeFilter: 'Remove filter',
        customStart: 'Start date',
        customEnd: 'End date'
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
        {key: 'filter:clearall', component: 'block_dashboardanalytics'},
        {key: 'filter:activeprefix', component: 'block_dashboardanalytics'},
        {key: 'js:nodata', component: 'block_dashboardanalytics'},
        {key: 'js:total', component: 'block_dashboardanalytics'},
        {key: 'filter:add', component: 'block_dashboardanalytics'},
        {key: 'filter:noavailable', component: 'block_dashboardanalytics'},
        {key: 'filter:remove', component: 'block_dashboardanalytics'},
        {key: 'filter:customstart', component: 'block_dashboardanalytics'},
        {key: 'filter:customend', component: 'block_dashboardanalytics'}
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
            currentTab: state.currentTab || 'overview'
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
        var optionHtml = (includeBlank ? '<option value="">' + escapeHtml(text('allOption', 'All')) + '</option>' : '')
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
            + '<label class="da-filter-control-label" for="da-filter-' + escapeHtml(key) + '-' + escapeHtml(String(state.contextid)) + '">'
            + escapeHtml(group.label) + '</label>'
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

    var renderDrilldown = function(root, data) {
        var container = root.querySelector('[data-region="drilldown"]');
        var title = root.querySelector('[data-region="drilldown-title"]');
        var count = root.querySelector('[data-region="drilldown-count"]');

        if (title) {
            title.textContent = data.title || text('details', 'Details');
        }
        if (count) {
            count.textContent = data.totalcount ? formatString(text('rows', '{$a} rows'), String(data.totalcount)) : '';
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

        container.innerHTML = description + '<div class="da-table-wrap"><table class="da-table">'
            + '<thead><tr>' + head + '</tr></thead>'
            + '<tbody>' + body + '</tbody>'
            + '</table></div>';
    };

    var renderVisuals = function(root, data) {
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
        if (!panels.length) {
            container.innerHTML = '<div class="da-empty">' + escapeHtml(text('noVisualData', 'No visual data available.')) + '</div>';
            return;
        }

        container.innerHTML = '<div class="da-visual-grid">' + panels.map(function(panel) {
            var items = panel.items || [];
            var body = '';

            if (!items.length) {
                body = '<div class="da-empty">' + escapeHtml(text('noMatchingRows', 'No matching rows.')) + '</div>';
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

            return '<article class="da-visual-panel" data-panel-key="' + escapeHtml(panel.key) + '">'
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

    var loadDrilldown = function(root, state, drilldownkey, filterOverrides) {
        var container = root.querySelector('[data-region="drilldown"]');
        setLoading(container);

        return call('block_dashboardanalytics_get_drilldown', {
            contextid: state.contextid,
            dashboardkey: state.dashboardkey,
            drilldownkey: drilldownkey,
            filters: JSON.stringify(readFilters(root, state, filterOverrides)),
            page: 0,
            perpage: 20
        }).then(function(response) {
            state.currentDrilldown = drilldownkey;
            renderDrilldown(root, response);
            persistState(root, state);
        }).catch(Notification.exception);
    };

    var loadVisuals = function(root, state, tabkey) {
        var container = root.querySelector('[data-region="drilldown"]');
        setLoading(container);

        return call('block_dashboardanalytics_get_visuals', {
            contextid: state.contextid,
            dashboardkey: state.dashboardkey,
            tabkey: tabkey,
            filters: JSON.stringify(readFilters(root, state))
        }).then(function(response) {
            state.currentTab = tabkey;
            setActiveTab(root, tabkey);
            renderVisuals(root, response);
            persistState(root, state);
        }).catch(Notification.exception);
    };

    var refresh = function(root, state) {
        persistState(root, state);
        loadKpis(root, state);
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
                updateFilterCounts(root, state);
                refresh(root, state);
                return;
            }

            if (event.target.matches('[data-filter-custom]')) {
                refresh(root, state);
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
                loadDrilldown(root, state, 'company_compliance', overrides);
                return;
            }

            var kpi = event.target.closest('[data-drilldown]');
            if (kpi && root.contains(kpi)) {
                state.currentTab = '';
                loadDrilldown(root, state, kpi.getAttribute('data-drilldown'));
                return;
            }

            var tab = event.target.closest('[data-tab]');
            if (tab && root.contains(tab)) {
                setActiveTab(root, tab.getAttribute('data-tab'));
                state.currentDrilldown = '';
                loadVisuals(root, state, tab.getAttribute('data-tab'));
                return;
            }

            if (!event.target.closest('[data-region="add-filter-menu"]') && !event.target.closest('[data-action="toggle-add-filter"]')) {
                var menu = root.querySelector('[data-region="add-filter-menu"]');
                if (menu) {
                    menu.hidden = true;
                }
            }
        });
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
            persistedFilters: {}
        };

        var saved = readSessionState(state);
        state.activeFilterKeys = Array.isArray(saved.activeFilterKeys) ? saved.activeFilterKeys : [];
        state.persistedFilters = saved.filters || {};
        state.currentTab = saved.currentTab || state.currentTab;
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
            strings.activeFiltersAll = values[9];
            strings.activeFiltersPrefix = values[10];
            strings.noData = values[11];
            strings.total = values[12];
            strings.addFilter = values[13];
            strings.noAvailableFilters = values[14];
            strings.removeFilter = values[15];
            strings.customStart = values[16];
            strings.customEnd = values[17];

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
