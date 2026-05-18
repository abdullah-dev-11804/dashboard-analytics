define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    var SELECTOR = '[data-region="dashboardanalytics"]';

    var call = function(methodname, args) {
        return Ajax.call([{
            methodname: methodname,
            args: args
        }])[0];
    };

    var escapeHtml = function(value) {
        var node = document.createElement('div');
        node.textContent = value === null || value === undefined ? '' : String(value);
        return node.innerHTML;
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

    var readFilters = function(root) {
        var filters = {};
        Array.prototype.slice.call(root.querySelectorAll('[data-filter-group]')).forEach(function(select) {
            filters[select.getAttribute('data-filter-group')] = selectedValues(select);
        });

        var status = root.querySelector('[data-filter="status"]');
        var search = root.querySelector('[data-filter="search"]');
        filters.status = status ? status.value : '';
        filters.search = search ? search.value : '';

        return filters;
    };

    var setLoading = function(node) {
        if (node) {
            node.innerHTML = '<div class="da-loading">Loading...</div>';
        }
    };

    var renderFilters = function(root, groups) {
        var container = root.querySelector('[data-region="filter-bar"]');
        if (!container) {
            return;
        }

        if (!groups.length) {
            container.innerHTML = '<div class="da-empty">No filter options found.</div>';
            return;
        }

        container.innerHTML = groups.map(function(group) {
            var options = group.options || [];
            var optionhtml = options.map(function(option) {
                return '<option value="' + escapeHtml(option.value) + '">' + escapeHtml(option.label) + '</option>';
            }).join('');

            if (!optionhtml) {
                optionhtml = '<option value="">No options</option>';
            }

            return '<label class="da-field">'
                + '<span>' + escapeHtml(group.label) + '</span>'
                + '<select data-filter-group="' + escapeHtml(group.key) + '" '
                + (group.multiple ? 'multiple size="3"' : '') + '>'
                + optionhtml
                + '</select>'
                + '</label>';
        }).join('');
    };

    var renderKpis = function(root, cards) {
        var container = root.querySelector('[data-region="kpi-strip"]');
        if (!container) {
            return;
        }

        if (!cards.length) {
            container.innerHTML = '<div class="da-empty">No KPI data found.</div>';
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
                + '</button>';
        }).join('');
    };

    var renderDrilldown = function(root, data) {
        var container = root.querySelector('[data-region="drilldown"]');
        var title = root.querySelector('[data-region="drilldown-title"]');
        var count = root.querySelector('[data-region="drilldown-count"]');

        if (title) {
            title.textContent = data.title || 'Details';
        }
        if (count) {
            count.textContent = data.totalcount ? data.totalcount + ' rows' : '';
        }

        if (!container) {
            return;
        }

        if (data.notice) {
            container.innerHTML = '<div class="da-empty">' + escapeHtml(data.notice) + '</div>';
            return;
        }

        if (!data.rows || !data.rows.length) {
            container.innerHTML = '<div class="da-empty">No matching rows.</div>';
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
                return '<td>' + escapeHtml(cellsByKey[column.key] || '') + '</td>';
            }).join('') + '</tr>';
        }).join('');

        container.innerHTML = '<div class="da-table-wrap"><table class="da-table">'
            + '<thead><tr>' + head + '</tr></thead>'
            + '<tbody>' + body + '</tbody>'
            + '</table></div>';
    };

    var loadFilters = function(root, state) {
        var container = root.querySelector('[data-region="filter-bar"]');
        setLoading(container);

        return call('block_dashboardanalytics_get_filter_options', {
            contextid: state.contextid
        }).then(function(response) {
            renderFilters(root, response.groups || []);
        }).catch(Notification.exception);
    };

    var loadKpis = function(root, state) {
        var container = root.querySelector('[data-region="kpi-strip"]');
        setLoading(container);

        return call('block_dashboardanalytics_get_kpis', {
            contextid: state.contextid,
            dashboardkey: state.dashboardkey,
            filters: JSON.stringify(readFilters(root))
        }).then(function(response) {
            renderKpis(root, response.cards || []);
        }).catch(Notification.exception);
    };

    var loadDrilldown = function(root, state, drilldownkey) {
        var container = root.querySelector('[data-region="drilldown"]');
        setLoading(container);

        return call('block_dashboardanalytics_get_drilldown', {
            contextid: state.contextid,
            dashboardkey: state.dashboardkey,
            drilldownkey: drilldownkey,
            filters: JSON.stringify(readFilters(root)),
            page: 0,
            perpage: 25
        }).then(function(response) {
            renderDrilldown(root, response);
        }).catch(Notification.exception);
    };

    var refresh = function(root, state) {
        loadKpis(root, state);
        loadDrilldown(root, state, 'compliance_action_table');
    };

    var bindEvents = function(root, state) {
        var timer = null;

        root.addEventListener('change', function(event) {
            if (event.target.matches('[data-filter-group], [data-filter="status"]')) {
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
            var kpi = event.target.closest('[data-drilldown]');
            if (kpi && root.contains(kpi)) {
                loadDrilldown(root, state, kpi.getAttribute('data-drilldown'));
                return;
            }

            var tab = event.target.closest('[data-tab]');
            if (tab && root.contains(tab)) {
                Array.prototype.slice.call(root.querySelectorAll('[data-tab]')).forEach(function(item) {
                    var active = item === tab;
                    item.classList.toggle('is-active', active);
                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                });
            }
        });
    };

    var init = function(contextid) {
        var root = document.querySelector(SELECTOR + '[data-contextid="' + contextid + '"]');
        if (!root) {
            return;
        }

        var state = {
            contextid: contextid,
            dashboardkey: root.getAttribute('data-dashboardkey') || ''
        };

        bindEvents(root, state);
        loadFilters(root, state).then(function() {
            refresh(root, state);
        });
    };

    return {
        init: init
    };
});

