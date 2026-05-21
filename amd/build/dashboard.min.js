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
        Array.prototype.slice.call(root.querySelectorAll('[data-filter-group]')).forEach(function(group) {
            var key = group.getAttribute('data-filter-group');
            var checked = Array.prototype.slice.call(group.querySelectorAll('[data-filter-option]:checked'))
                .map(function(option) {
                    return option.value;
                });
            filters[key] = checked;
        });

        Array.prototype.slice.call(root.querySelectorAll('select[data-filter-group]')).forEach(function(select) {
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
            var optionhtml = options.length ? options.map(function(option) {
                return '<label class="da-filter-option">'
                    + '<input type="checkbox" data-filter-option value="' + escapeHtml(option.value) + '">'
                    + '<span>' + escapeHtml(option.label) + '</span>'
                    + '</label>';
            }).join('') : '<div class="da-filter-empty">No options</div>';

            return '<div class="da-filter-menu" data-filter-group="' + escapeHtml(group.key) + '">'
                + '<button type="button" class="da-filter-trigger" aria-expanded="false">'
                + '<span>' + escapeHtml(group.label) + '</span>'
                + '<strong data-filter-count>All</strong>'
                + '</button>'
                + '<div class="da-filter-popover" hidden>'
                + optionhtml
                + '</div>'
                + '</div>';
        }).join('');
    };

    var updateFilterCounts = function(root) {
        Array.prototype.slice.call(root.querySelectorAll('[data-filter-group]')).forEach(function(group) {
            var count = group.querySelectorAll('[data-filter-option]:checked').length;
            var label = group.querySelector('[data-filter-count]');
            if (label) {
                label.textContent = count ? count + ' selected' : 'All';
            }
        });
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
                var value = escapeHtml(cellsByKey[column.key] || '');
                var key = column.key;
                if (key === 'status' || key === 'statusbadge') {
                    return '<td><span class="da-badge da-badge-' + value.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '">' + value + '</span></td>';
                }
                if (key === 'action') {
                    return '<td><button type="button" class="da-row-action">' + value + '</button></td>';
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
            state.currentDrilldown = drilldownkey;
            renderDrilldown(root, response);
        }).catch(Notification.exception);
    };

    var refresh = function(root, state) {
        loadKpis(root, state);
        loadDrilldown(root, state, state.currentDrilldown || 'owner_total_active_users');
    };

    var drilldownForTab = function(tabkey) {
        var map = {
            kpis: 'owner_total_active_users',
            overview: 'owner_compliance',
            compliance: 'owner_compliance',
            turnover: 'owner_total_active_users',
            quality: 'owner_compliance',
            proctoring: 'owner_compliance',
            forecast: 'owner_expiring_documents',
            server: 'owner_server_disk',
            capacity: 'owner_server_disk',
            performance: 'owner_server_disk',
            errorlog: 'owner_server_disk',
            settings: 'owner_server_disk'
        };

        return map[tabkey] || 'owner_total_active_users';
    };

    var bindEvents = function(root, state) {
        var timer = null;

        root.addEventListener('change', function(event) {
            if (event.target.matches('[data-filter-option], [data-filter="status"]')) {
                updateFilterCounts(root);
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
            var trigger = event.target.closest('.da-filter-trigger');
            if (trigger && root.contains(trigger)) {
                var menu = trigger.closest('[data-filter-group]');
                var popover = menu ? menu.querySelector('.da-filter-popover') : null;
                var expanded = trigger.getAttribute('aria-expanded') === 'true';
                Array.prototype.slice.call(root.querySelectorAll('.da-filter-trigger')).forEach(function(item) {
                    item.setAttribute('aria-expanded', 'false');
                });
                Array.prototype.slice.call(root.querySelectorAll('.da-filter-popover')).forEach(function(item) {
                    item.hidden = true;
                });
                if (popover) {
                    trigger.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                    popover.hidden = expanded;
                }
                return;
            }

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
                loadDrilldown(root, state, drilldownForTab(tab.getAttribute('data-tab')));
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
            updateFilterCounts(root);
            refresh(root, state);
        });
    };

    return {
        init: init
    };
});
