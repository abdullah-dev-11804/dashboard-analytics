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
        turnoverHigh: '>10% High',
        heatmapAllCombined: 'All companies combined',
        heatmapCompliantLegend: '>=80% Compliant',
        heatmapRiskLegend: '70–79% At risk',
        heatmapCriticalLegend: '<70% Critical',
        heatmapCorner: 'Personnel category',
        searchPlaceholder: 'Search {$a}',
        currentCompliance: 'Current compliance',
        compliantThresholdTitle: 'Compliant threshold',
        criticalThresholdTitle: 'Critical threshold',
        pointsVsLastMonth: '{$a} pts vs last month',
        noChangeVsLastMonth: 'No change vs last month',
        months3Short: '3M',
        months6Short: '6M',
        months12Short: '12M',
        sortWorstBest: 'Worst to best',
        sortBestWorst: 'Best to worst',
        qualityCourseHeader: 'Course',
        qualityRatingHeader: 'Rating',
        qualityReviewsHeader: 'Reviews',
        qualityNpsHeader: 'NPS',
        qualityFeedbackHeader: 'Latest feedback',
        qualityRelevanceHeader: 'Relevance',
        qualityNoFeedback: 'No review text available',
        courseAnalyticsSearch: 'Search courses',
        courseAnalyticsIncluded: 'Included',
        courseAnalyticsExcluded: 'Excluded',
        courseAnalyticsVisible: 'Visible',
        courseAnalyticsHidden: 'Hidden',
        courseAnalyticsToggleOn: 'On',
        courseAnalyticsToggleOff: 'Off',
        courseAnalyticsNoResults: 'No matching courses found.',
        courseAnalyticsHelp: 'Hidden courses are excluded automatically. Turning analytics off here also excludes the course from dashboard calculations.',
        courseAnalyticsSaved: 'Course analytics setting updated.',
        courseAnalyticsLoadError: 'Unable to load course analytics controls.',
        courseAnalyticsSaveError: 'Unable to update the course analytics setting.',
        courseAnalyticsHeaderCourse: 'Course',
        courseAnalyticsHeaderVisibility: 'Visibility',
        courseAnalyticsHeaderAnalytics: 'Analytics',
        courseAnalyticsHeaderToggle: 'Toggle',
        formulaTooltip: 'Formula',
        exportLabel: 'Export',
        exportAllLabel: 'Export all',
        hideSidebar: 'Hide sidebar',
        showSidebar: 'Show sidebar',
        forecastSummaryTitle: 'Course summary',
        forecastSummaryEmpty: 'Click a bar label to see the course breakdown.',
        forecastSummaryTotal: 'Total',
        forecastTableEmpty: 'Click a bar or a course segment to open The Learning Matrix.',
        forecastTableLoading: 'Loading Learning Matrix...',
        forecastUsersLabel: '{$a} users',
        forecastInWindowLabel: 'In this window',
        forecastRenewalsLabel: '{$a} renewals',
        forecastClearCourseLabel: 'Clear course filter',
        learningMatrixTitle: 'The Learning Matrix'
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
        {key: 'js:turnoverhigh', component: 'block_dashboardanalytics'},
        {key: 'js:heatmapallcombined', component: 'block_dashboardanalytics'},
        {key: 'js:heatmapcompliantlegend', component: 'block_dashboardanalytics'},
        {key: 'js:heatmaprisklegend', component: 'block_dashboardanalytics'},
        {key: 'js:heatmapcriticallegend', component: 'block_dashboardanalytics'},
        {key: 'js:heatmapcorner', component: 'block_dashboardanalytics'},
        {key: 'js:searchplaceholder', component: 'block_dashboardanalytics'},
        {key: 'js:currentcompliance', component: 'block_dashboardanalytics'},
        {key: 'js:compliantthreshold', component: 'block_dashboardanalytics'},
        {key: 'js:criticalthresholdtitle', component: 'block_dashboardanalytics'},
        {key: 'js:pointsvslastmonth', component: 'block_dashboardanalytics'},
        {key: 'js:nochangevslastmonth', component: 'block_dashboardanalytics'},
        {key: 'js:months3short', component: 'block_dashboardanalytics'},
        {key: 'js:months6short', component: 'block_dashboardanalytics'},
        {key: 'js:months12short', component: 'block_dashboardanalytics'},
        {key: 'js:sortworstbest', component: 'block_dashboardanalytics'},
        {key: 'js:sortbestworst', component: 'block_dashboardanalytics'},
        {key: 'js:qualitycourseheader', component: 'block_dashboardanalytics'},
        {key: 'js:qualityratingheader', component: 'block_dashboardanalytics'},
        {key: 'js:qualityreviewsheader', component: 'block_dashboardanalytics'},
        {key: 'js:qualitynpsheader', component: 'block_dashboardanalytics'},
        {key: 'js:qualityfeedbackheader', component: 'block_dashboardanalytics'},
        {key: 'js:qualityrelevanceheader', component: 'block_dashboardanalytics'},
        {key: 'js:qualitynofeedback', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticssearch', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticsincluded', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticsexcluded', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticsvisible', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticshidden', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticstoggleon', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticstoggleoff', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticsnoresults', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticshelp', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticssaved', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticsloaderror', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticssaveerror', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticsheadercourse', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticsheadervisibility', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticsheaderanalytics', component: 'block_dashboardanalytics'},
        {key: 'js:courseanalyticsheadertoggle', component: 'block_dashboardanalytics'},
        {key: 'js:formulatooltip', component: 'block_dashboardanalytics'},
        {key: 'js:export', component: 'block_dashboardanalytics'},
        {key: 'js:exportall', component: 'block_dashboardanalytics'},
        {key: 'js:exportcsv', component: 'block_dashboardanalytics'},
        {key: 'view:hidesidebar', component: 'block_dashboardanalytics'},
        {key: 'view:showsidebar', component: 'block_dashboardanalytics'},
        {key: 'forecast:summary:title', component: 'block_dashboardanalytics'},
        {key: 'forecast:summary:empty', component: 'block_dashboardanalytics'},
        {key: 'forecast:summary:total', component: 'block_dashboardanalytics'},
        {key: 'forecast:table:empty', component: 'block_dashboardanalytics'},
        {key: 'forecast:table:loading', component: 'block_dashboardanalytics'},
        {key: 'forecast:tooltip:count', component: 'block_dashboardanalytics'},
        {key: 'forecast:toolbar:window', component: 'block_dashboardanalytics'},
        {key: 'forecast:toolbar:renewals', component: 'block_dashboardanalytics'},
        {key: 'forecast:table:clearcourse', component: 'block_dashboardanalytics'},
        {key: 'complianceactiontable', component: 'block_dashboardanalytics'}
    ];

    var stringTargets = [
        'loading',
        'details',
        'rows',
        'noMatchingRows',
        'noKpi',
        'noVisualData',
        'dashboardVisuals',
        'noFilterOptions',
        'allOption',
        'allWithLabel',
        'activeFiltersAll',
        'activeFiltersPrefix',
        'noData',
        'total',
        'addFilter',
        'noAvailableFilters',
        'removeFilter',
        'customStart',
        'customEnd',
        'previous',
        'next',
        'perPage',
        'page',
        'goToServerTab',
        'warningThreshold',
        'criticalThreshold',
        'clearState',
        'monitorState',
        'criticalState',
        'okSummary',
        'warningSummary',
        'checkSummary',
        'topActiveCourses',
        'companyHeader',
        'usersHeader',
        'complianceHeader',
        'turnoverHeader',
        'trustScoreHeader',
        'completionHeader',
        'statusHeader',
        'reportLabel',
        'healthyLabel',
        'atRiskLabel',
        'onboardingLabel',
        'period3Months',
        'period1Year',
        'period2Years',
        'periodAllTime',
        'barChartLabel',
        'interactiveLabel',
        'allcompanieslabel',
        'allcourseslabel',
        'allperiodslabel',
        'alldepartmentslabel',
        'alllocationslabel',
        'allpositionslabel',
        'allpersonnelcategorieslabel',
        'allsiteslabel',
        'alleducationslabel',
        'comboBarLineLabel',
        'chartJsBarLabel',
        'turnoverFormula',
        'turnoverGood',
        'turnoverMonitor',
        'turnoverHigh',
        'heatmapAllCombined',
        'heatmapCompliantLegend',
        'heatmapRiskLegend',
        'heatmapCriticalLegend',
        'heatmapCorner',
        'searchPlaceholder',
        'currentCompliance',
        'compliantThresholdTitle',
        'criticalThresholdTitle',
        'pointsVsLastMonth',
        'noChangeVsLastMonth',
        'months3Short',
        'months6Short',
        'months12Short',
        'sortWorstBest',
        'sortBestWorst',
        'qualityCourseHeader',
        'qualityRatingHeader',
        'qualityReviewsHeader',
        'qualityNpsHeader',
        'qualityFeedbackHeader',
        'qualityRelevanceHeader',
        'qualityNoFeedback',
        'courseAnalyticsSearch',
        'courseAnalyticsIncluded',
        'courseAnalyticsExcluded',
        'courseAnalyticsVisible',
        'courseAnalyticsHidden',
        'courseAnalyticsToggleOn',
        'courseAnalyticsToggleOff',
        'courseAnalyticsNoResults',
        'courseAnalyticsHelp',
        'courseAnalyticsSaved',
        'courseAnalyticsLoadError',
        'courseAnalyticsSaveError',
        'courseAnalyticsHeaderCourse',
        'courseAnalyticsHeaderVisibility',
        'courseAnalyticsHeaderAnalytics',
        'courseAnalyticsHeaderToggle',
        'formulaTooltip',
        'exportLabel',
        'exportAllLabel',
        'exportCsv',
        'hideSidebar',
        'showSidebar',
        'forecastSummaryTitle',
        'forecastSummaryEmpty',
        'forecastSummaryTotal',
        'forecastTableEmpty',
        'forecastTableLoading',
        'forecastUsersLabel',
        'forecastInWindowLabel',
        'forecastRenewalsLabel',
        'forecastClearCourseLabel',
        'learningMatrixTitle'
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

    var formatPercent = function(value) {
        var numeric = Number(value) || 0;
        var rounded = Math.round(numeric * 10) / 10;
        if (Math.abs(rounded - Math.round(rounded)) < 0.05) {
            return String(Math.round(rounded));
        }
        return rounded.toFixed(1);
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

    var actionHistoryKey = function(state) {
        return storageKey(state) + ':history';
    };

    var readActionHistory = function(state) {
        try {
            return JSON.parse(window.sessionStorage.getItem(actionHistoryKey(state)) || '[]');
        } catch (error) {
            return [];
        }
    };

    var writeActionHistory = function(state, payload) {
        try {
            window.sessionStorage.setItem(actionHistoryKey(state), JSON.stringify(payload));
        } catch (error) {
            // Ignore storage write issues.
        }
    };

    var snapshotState = function(root, state) {
        return {
            activeFilterKeys: (state.activeFilterKeys || []).slice(),
            filters: Object.keys(state.filterGroups || {}).length ? readFilters(root, state) : Object.assign({}, state.persistedFilters || {}),
            currentTab: state.currentTab || 'overview',
            currentDrilldown: state.currentDrilldown || '',
            currentDrilldownPage: Math.max(0, Number(state.currentDrilldownPage) || 0),
            currentDrilldownPerPage: Math.max(10, Number(state.currentDrilldownPerPage) || 20),
            currentDrilldownOverrides: state.currentDrilldownOverrides || null,
            visualOverrides: Object.assign({}, state.currentVisualOverrides || {})
        };
    };

    var snapshotsEqual = function(a, b) {
        return JSON.stringify(a || {}) === JSON.stringify(b || {});
    };

    var updateBackButtonState = function() {};

    var rememberCurrentState = function(root, state) {
        var history = readActionHistory(state);
        var snapshot = snapshotState(root, state);
        if (!history.length || !snapshotsEqual(history[history.length - 1], snapshot)) {
            history.push(snapshot);
            if (history.length > 40) {
                history = history.slice(history.length - 40);
            }
            writeActionHistory(state, history);
        }
        updateBackButtonState(state);
    };

    var applySnapshotToState = function(state, snapshot) {
        if (!snapshot) {
            return;
        }
        state.activeFilterKeys = Array.isArray(snapshot.activeFilterKeys) ? snapshot.activeFilterKeys.slice() : [];
        state.persistedFilters = snapshot.filters || {};
        state.currentTab = snapshot.currentTab || 'overview';
        state.currentDrilldown = snapshot.currentDrilldown || '';
        state.currentDrilldownPage = Math.max(0, Number(snapshot.currentDrilldownPage) || 0);
        state.currentDrilldownPerPage = Math.max(10, Number(snapshot.currentDrilldownPerPage) || 20);
        state.currentDrilldownOverrides = snapshot.currentDrilldownOverrides || null;
        state.currentVisualOverrides = snapshot.visualOverrides || {};
    };

    var restorePreviousState = function(root, state) {
        var history = readActionHistory(state);
        var snapshot = history.pop();
        if (!snapshot) {
            updateBackButtonState(state);
            return false;
        }

        writeActionHistory(state, history);
        applySnapshotToState(state, snapshot);
        syncStatusModeUi(root, state);
        setActiveTab(root, state.currentTab);

        if (Object.keys(state.filterGroups || {}).length) {
            renderFilters(root, state, Object.keys(state.filterGroups).map(function(key) {
                return state.filterGroups[key];
            }));
            updateFilterCounts(root, state);
        }

        updateBackButtonState(state);

        if (state.currentTab === 'kpis') {
            loadDrilldown(
                root,
                state,
                state.currentDrilldown || defaultDrilldownKey(state),
                state.currentDrilldownOverrides,
                state.currentDrilldownPage,
                state.currentDrilldownPerPage
            );
            return true;
        }

        if (state.currentDrilldown) {
            loadDrilldown(
                root,
                state,
                state.currentDrilldown,
                state.currentDrilldownOverrides,
                state.currentDrilldownPage,
                state.currentDrilldownPerPage
            );
            return true;
        }

        loadVisuals(root, state, state.currentTab || 'overview', state.currentVisualOverrides);
        return true;
    };

    var browserHistoryState = function(root, state) {
        return {
            marker: 'block_dashboardanalytics',
            contextid: state.contextid,
            dashboardkey: state.dashboardkey,
            snapshot: snapshotState(root, state)
        };
    };

    var matchesBrowserHistoryState = function(payload, state) {
        return !!(payload
            && payload.marker === 'block_dashboardanalytics'
            && Number(payload.contextid) === Number(state.contextid)
            && String(payload.dashboardkey || '') === String(state.dashboardkey || '')
            && payload.snapshot);
    };

    var commitBrowserHistoryState = function(root, state, mode) {
        if (!window.history || !window.history.pushState || !window.history.replaceState) {
            return;
        }

        var payload = browserHistoryState(root, state);
        var current = window.history.state;
        if (mode !== 'replace' && matchesBrowserHistoryState(current, state)
            && snapshotsEqual(current.snapshot, payload.snapshot)) {
            return;
        }

        if (mode === 'replace') {
            window.history.replaceState(payload, document.title, window.location.href);
            return;
        }

        if (mode === 'push') {
            window.history.pushState(payload, document.title, window.location.href);
        }
    };

    var restoreBrowserHistoryState = function(root, state, payload) {
        if (!matchesBrowserHistoryState(payload, state)) {
            return false;
        }

        applySnapshotToState(state, payload.snapshot);
        syncStatusModeUi(root, state);
        setActiveTab(root, state.currentTab);

        if (Object.keys(state.filterGroups || {}).length) {
            renderFilters(root, state, Object.keys(state.filterGroups).map(function(key) {
                return state.filterGroups[key];
            }));
            updateFilterCounts(root, state);
        }

        if (state.currentTab === 'kpis') {
            loadDrilldown(
                root,
                state,
                state.currentDrilldown || defaultDrilldownKey(state),
                state.currentDrilldownOverrides,
                state.currentDrilldownPage,
                state.currentDrilldownPerPage,
                'skip'
            );
            return true;
        }

        if (state.currentDrilldown) {
            loadDrilldown(
                root,
                state,
                state.currentDrilldown,
                state.currentDrilldownOverrides,
                state.currentDrilldownPage,
                state.currentDrilldownPerPage,
                'skip'
            );
            return true;
        }

        loadVisuals(root, state, state.currentTab || 'overview', state.currentVisualOverrides, 'skip');
        return true;
    };

    var initViewStretchToggle = function(root, state) {
        var button = document.querySelector('[data-action="view-stretch-toggle"]');
        if (!document.body.classList.contains('path-block-dashboardanalytics-view')) {
            return;
        }

        var storage = 'block_dashboardanalytics:view:stretch';
        var apply = function(enabled) {
            if (button) {
                document.body.classList.toggle('da-view-stretched', !!enabled);
                button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
                button.textContent = enabled
                    ? text('showSidebar', 'Show sidebar')
                    : text('hideSidebar', 'Hide sidebar');
            }
        };

        apply(window.localStorage.getItem(storage) === '1');

        if (button && button.getAttribute('data-bound') !== '1') {
            button.setAttribute('data-bound', '1');
            button.addEventListener('click', function() {
                var next = !document.body.classList.contains('da-view-stretched');
                apply(next);
                try {
                    window.localStorage.setItem(storage, next ? '1' : '0');
                } catch (error) {
                    // Ignore storage write issues.
                }
            });
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

    var getStatusModeInput = function(root) {
        return root.querySelector('input[type="hidden"][data-filter-group="statusmode"]');
    };

    var currentStatusMode = function(root, state) {
        var input = getStatusModeInput(root);
        if (input && input.value) {
            return input.value === 'employee' ? 'employee' : 'course';
        }

        return (((state || {}).persistedFilters || {}).statusmode === 'employee') ? 'employee' : 'course';
    };

    var syncStatusModeUi = function(root, state) {
        var mode = currentStatusMode(root, state);
        var input = getStatusModeInput(root);
        if (input) {
            input.value = mode;
        }

        Array.prototype.slice.call(document.querySelectorAll('[data-action="statusmode-toggle"]')).forEach(function(button) {
            var active = (button.getAttribute('data-statusmode') || 'course') === mode;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    var initStatusModeToggle = function(root, state) {
        syncStatusModeUi(root, state);

        Array.prototype.slice.call(document.querySelectorAll('[data-action="statusmode-toggle"]')).forEach(function(button) {
            if (button.getAttribute('data-bound') === '1') {
                return;
            }

            button.setAttribute('data-bound', '1');
            button.addEventListener('click', function() {
                var nextMode = button.getAttribute('data-statusmode') === 'employee' ? 'employee' : 'course';
                if (nextMode === currentStatusMode(root, state)) {
                    return;
                }

                rememberCurrentState(root, state);
                var input = getStatusModeInput(root);
                if (input) {
                    input.value = nextMode;
                }
                state.persistedFilters = Object.assign({}, state.persistedFilters || {}, {
                    statusmode: nextMode
                });
                state.currentDrilldownPage = 0;
                syncStatusModeUi(root, state);
                refresh(root, state);
            });
        });
    };

    var readFilters = function(root, state, overrides) {
        var filters = {};
        Array.prototype.slice.call(root.querySelectorAll('[data-filter-group]')).forEach(function(field) {
            var key = field.getAttribute('data-filter-group');
            if (field.tagName === 'SELECT') {
                if (key === 'daterange') {
                    filters[key] = field.value || defaultDateRange(state);
                } else {
                    filters[key] = selectedValues(field);
                }
                return;
            }

            if (field.type === 'hidden') {
                if (key === 'statusmode') {
                    filters[key] = field.value === 'employee' ? 'employee' : 'course';
                    return;
                }
                filters[key] = field.value ? [field.value] : [];
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
        var defaults = [state.companyFilterKey, 'locations', 'sites', 'departments', 'personnelcategories', 'positions', 'userids'];
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

    var selectedLabelForGroup = function(state, key) {
        var selected = selectedValueForGroup(state, key);
        if (selected === '') {
            return '';
        }

        var group = (state.filterGroups || {})[key] || {};
        var match = (group.options || []).find(function(option) {
            return String(option.value) === String(selected);
        });

        return match ? String(match.label) : '';
    };

    var isSearchableGroup = function(group) {
        return !!(group && group.searchable);
    };

    var renderFilterControl = function(state, group) {
        var key = group.key;
        var selected = selectedValueForGroup(state, key);
        var selectedLabel = selectedLabelForGroup(state, key);
        var options = group.options || [];
        var includeBlank = key !== 'daterange' && !isSearchableGroup(group);
        var allLabel = defaultOptionLabel(group);
        var optionHtml = '';

        if (isSearchableGroup(group)) {
            var listId = 'da-filter-list-' + escapeHtml(key) + '-' + escapeHtml(String(state.contextid));
            optionHtml = '<input type="search" class="da-filter-select da-filter-searchable-input"'
                + ' data-filter-search="' + escapeHtml(key) + '"'
                + ' list="' + listId + '"'
                + ' value="' + escapeHtml(selectedLabel) + '"'
                + ' placeholder="' + escapeHtml(formatString(text('searchPlaceholder', 'Search {$a}'), group.label)) + '"'
                + ' autocomplete="off" aria-label="' + escapeHtml(group.label) + '">'
                + '<input type="hidden" data-filter-group="' + escapeHtml(key) + '" value="' + escapeHtml(selected) + '">'
                + '<datalist id="' + listId + '">'
                + options.map(function(option) {
                    return '<option value="' + escapeHtml(option.label) + '" data-value="' + escapeHtml(option.value) + '"></option>';
                }).join('')
                + '</datalist>';
        } else {
            optionHtml = '<select id="da-filter-' + escapeHtml(key) + '-' + escapeHtml(String(state.contextid)) + '" class="da-filter-select"'
                + ' data-filter-group="' + escapeHtml(key) + '" aria-label="' + escapeHtml(group.label) + '">'
                + (includeBlank ? '<option value="">' + escapeHtml(allLabel) + '</option>' : '')
                + options.map(function(option) {
                    var isSelected = String(option.value) === selected ? ' selected' : '';
                    return '<option value="' + escapeHtml(option.value) + '"' + isSelected + '>' + escapeHtml(option.label) + '</option>';
                }).join('')
                + '</select>';
        }

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
            + optionHtml
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
        state.companyFilterKey = ((groups || []).find(function(group) {
            return group.key === 'companyids' || group.key === 'companies';
        }) || {}).key || '';
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

        (state.activeFilterKeys || []).forEach(function(key) {
            var wrap = root.querySelector('[data-filter-wrap="' + key + '"]');
            var hidden = wrap ? wrap.querySelector('input[type="hidden"][data-filter-group]') : null;
            if (hidden) {
                if (hidden.value !== '') {
                    var visible = wrap.querySelector('[data-filter-search]');
                    if (visible && visible.value !== '') {
                        active.push(visible.value);
                    }
                }
                return;
            }

            var select = wrap ? wrap.querySelector('select[data-filter-group]') : null;
            if (!select) {
                return;
            }

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

        var appendixVariant = root.getAttribute('data-dashboardkey') !== 'employee';
        container.classList.toggle('da-kpi-strip-appendix', appendixVariant);

        container.innerHTML = cards.map(function(card) {
            var trendClass = card.trendstyle === 'plain' ? ' da-kpi-trend-plain' : '';
            var cardClass = 'da-kpi da-kpi-' + escapeHtml(card.status);
            if (appendixVariant) {
                cardClass += ' da-kpi-appendix da-kpi-appendix-' + escapeHtml(card.key);
            }
            var railPercent = Math.max(0, Math.min(100, Number(card.railpercent) || 0));
            return '<button type="button" class="' + cardClass + '"'
                + ' style="--da-kpi-rail-width:' + railPercent + '%"'
                + ' data-drilldown="' + escapeHtml(card.drilldownkey)
                + (card.filterstatus ? '" data-filter-status="' + escapeHtml(card.filterstatus) : '')
                + '" title="' + escapeHtml(card.help) + '">'
                + '<span class="da-kpi-label">' + escapeHtml(card.label) + '</span>'
                + (card.note ? '<span class="da-kpi-note">' + escapeHtml(card.note) + '</span>' : '')
                + '<span class="da-kpi-figure">'
                + '<span class="da-kpi-value">' + escapeHtml(card.value)
                + (card.unit ? ' <small>' + escapeHtml(card.unit) + '</small>' : '')
                + '</span>'
                + ((card.trend || card.note) ? '<span class="da-kpi-meta">'
                    + (card.trend ? '<span class="da-kpi-trend' + trendClass + '">' + escapeHtml(card.trend) + '</span>' : '')
                    + '</span>' : '')
                + '</span>'
                + (card.help && !appendixVariant ? '<span class="da-kpi-hint">' + escapeHtml(card.help) + '</span>' : '')
                + (appendixVariant ? '<span class="da-kpi-rail"><i></i></span>' : '')
                + '</button>';
        }).join('');
    };

    var drilldownExportUrlFor = function(root, state, scope, drilldownkey, overrides, page, perpage) {
        var exportable = [
            'company_compliance',
            'company_expiring_documents',
            'company_expired_documents',
            'company_forecast_documents',
            'company_course_noncompliance',
            'client_compliance',
            'client_expiring_documents',
            'client_expired_documents',
            'client_forecast_documents',
            'employee_documents'
        ];

        if (!drilldownkey || exportable.indexOf(drilldownkey) === -1) {
            return '';
        }

        var params = new URLSearchParams();
        params.set('contextid', String(state.contextid));
        params.set('dashboardkey', String(state.dashboardkey || ''));
        params.set('drilldownkey', String(drilldownkey || ''));
        params.set('filters', JSON.stringify(readFilters(root, state, overrides || undefined)));
        params.set('scope', scope === 'all' ? 'all' : 'visible');
        params.set('page', String(Math.max(0, Number(page) || 0)));
        params.set('perpage', String(Math.max(10, Number(perpage) || 20)));
        params.set('sesskey', M.cfg.sesskey);
        return M.cfg.wwwroot + '/blocks/dashboardanalytics/export.php?' + params.toString();
    };

    var drilldownExportUrl = function(root, state, scope) {
        return drilldownExportUrlFor(
            root,
            state,
            scope,
            state.currentDrilldown,
            state.currentDrilldownOverrides || undefined,
            state.currentDrilldownPage,
            state.currentDrilldownPerPage
        );
    };

    var buildDrilldownTableMarkup = function(root, data, state, options) {
        var columns = data.columns || [];
        var currentPage = Math.max(0, Number(options.page) || 0);
        var perpage = Math.max(10, Number(options.perpage) || 20);
        var totalcount = Math.max(0, Number(data.totalcount) || 0);
        var totalpages = Math.max(1, Math.ceil(totalcount / perpage));
        var drilldownkey = options.drilldownkey || state.currentDrilldown || '';
        var overrides = options.overrides || undefined;
        var actionPrefix = options.actionPrefix || 'drilldown';
        var exporturl = data.exporturl || drilldownExportUrlFor(root, state, 'visible', drilldownkey, overrides, currentPage, perpage);
        var exportallurl = drilldownExportUrlFor(root, state, 'all', drilldownkey, overrides, currentPage, perpage);
        var head = columns.map(function(column) {
            return '<th scope="col">' + escapeHtml(column.label) + '</th>';
        }).join('');

        var body = data.rows.map(function(row) {
            var cellsByKey = {};
            var cellsMetaByKey = {};
            (row.cells || []).forEach(function(cell) {
                cellsByKey[cell.key] = cell.value;
                cellsMetaByKey[cell.key] = cell;
            });

            var rowtype = row.rowtype || '';
            var groupid = row.groupid || '';
            var classes = ['da-table-row'];
            if (rowtype === 'summary') {
                classes.push('da-matrix-summary-row');
            } else if (rowtype === 'course') {
                classes.push('da-matrix-course-row', 'is-collapsed');
            }

            return '<tr class="' + classes.join(' ') + '"'
                + (groupid ? ' data-group-id="' + escapeHtml(groupid) + '"' : '')
                + (rowtype ? ' data-rowtype="' + escapeHtml(rowtype) + '"' : '')
                + '>' + columns.map(function(column) {
                var value = escapeHtml(cellsByKey[column.key] || '');
                var key = column.key;
                if (key === 'status' || key === 'statusbadge') {
                    var statusclass = value.toLowerCase().replace(/[^a-z0-9]+/g, '-');
                    if (cellsMetaByKey[key] && cellsMetaByKey[key].statuskey) {
                        statusclass = cellsMetaByKey[key].statuskey;
                    }
                    return '<td><span class="da-badge da-badge-' + statusclass + '">' + value + '</span></td>';
                }
                if (key === 'action') {
                    return '<td><button type="button" class="da-row-action" data-action="company-report"'
                        + ' data-company="' + escapeHtml(cellsByKey.company || '') + '"'
                        + ' data-companyid="' + escapeHtml(cellsByKey.companyid || '') + '">'
                        + value + '</button></td>';
                }
                if (key === 'course' && rowtype === 'summary') {
                    var coursecount = cellsMetaByKey[key] && cellsMetaByKey[key].coursecount
                        ? Number(cellsMetaByKey[key].coursecount)
                        : 0;
                    if (coursecount <= 1) {
                        if (cellsMetaByKey[key] && cellsMetaByKey[key].courseurl) {
                            return '<td class="da-table-course-cell"><a class="da-table-link da-table-course-link" href="'
                                + escapeHtml(cellsMetaByKey[key].courseurl) + '" title="' + value + '">' + value + '</a></td>';
                        }
                        return '<td class="da-table-course-cell"><span class="da-table-course-text" title="' + value + '">' + value + '</span></td>';
                    }
                    var togglelabel = cellsMetaByKey[key] && cellsMetaByKey[key].togglelabel
                        ? cellsMetaByKey[key].togglelabel
                        : text('details', 'Details');
                    return '<td><button type="button" class="da-matrix-toggle" data-action="matrix-toggle"'
                        + (groupid ? ' data-group-id="' + escapeHtml(groupid) + '"' : '')
                        + ' aria-expanded="false" title="' + escapeHtml(togglelabel) + '">'
                        + '<span class="da-matrix-toggle-icon">▾</span>'
                        + '<span>' + value + '</span></button></td>';
                }
                if (key === 'employee' && cellsMetaByKey[key] && cellsMetaByKey[key].profileurl) {
                    return '<td><a class="da-table-link" href="' + escapeHtml(cellsMetaByKey[key].profileurl) + '">' + value + '</a></td>';
                }
                if (key === 'course') {
                    if (cellsMetaByKey[key] && cellsMetaByKey[key].courseurl) {
                        return '<td class="da-table-course-cell"><a class="da-table-link da-table-course-link" href="'
                            + escapeHtml(cellsMetaByKey[key].courseurl) + '" title="' + value + '">' + value + '</a></td>';
                    }
                    return '<td class="da-table-course-cell"><span class="da-table-course-text" title="' + value + '">' + value + '</span></td>';
                }
                return '<td>' + value + '</td>';
            }).join('') + '</tr>';
        }).join('');

        var description = data.description
            ? '<div class="da-description">' + escapeHtml(data.description) + '</div>'
            : '';
        var actions = exporturl
            ? '<div class="da-table-actions">'
                + '<a class="da-row-action" href="' + escapeHtml(exporturl) + '">'
                + escapeHtml(text('exportLabel', 'Export')) + '</a>'
                + '<a class="da-row-action" href="' + escapeHtml(exportallurl) + '">'
                + escapeHtml(text('exportAllLabel', 'Export all')) + '</a>'
                + '</div>'
            : '';

        var pagination = totalcount ? '<div class="da-table-pagination">'
            + '<div class="da-table-pagination-status">' + escapeHtml(formatString(text('page', 'Page {$a}'), String((currentPage + 1) + ' / ' + totalpages))) + '</div>'
            + '<div class="da-table-pagination-controls">'
            + '<label class="da-table-perpage-label"><span>' + escapeHtml(text('perPage', 'Rows per page')) + '</span>'
            + '<select class="da-table-perpage" data-action="' + escapeHtml(actionPrefix) + '-perpage">'
            + [20, 50, 100].map(function(size) {
                return '<option value="' + size + '"' + (size === perpage ? ' selected' : '') + '>' + size + '</option>';
            }).join('')
            + '</select></label>'
            + '<button type="button" class="da-pagination-button" data-action="' + escapeHtml(actionPrefix) + '-page" data-page="' + Math.max(0, currentPage - 1) + '"'
            + (currentPage <= 0 ? ' disabled' : '') + '>' + escapeHtml(text('previous', 'Previous')) + '</button>'
            + '<button type="button" class="da-pagination-button" data-action="' + escapeHtml(actionPrefix) + '-page" data-page="' + Math.min(totalpages - 1, currentPage + 1) + '"'
            + (currentPage >= totalpages - 1 ? ' disabled' : '') + '>' + escapeHtml(text('next', 'Next')) + '</button>'
            + '</div></div>' : '';

        return description + actions + '<div class="da-table-wrap"><table class="da-table da-learning-matrix">'
            + '<thead><tr>' + head + '</tr></thead>'
            + '<tbody>' + body + '</tbody>'
            + '</table></div>' + pagination;
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

        container.innerHTML = buildDrilldownTableMarkup(root, data, state, {
            page: currentPage,
            perpage: perpage,
            drilldownkey: state.currentDrilldown || '',
            overrides: state.currentDrilldownOverrides || undefined,
            actionPrefix: 'drilldown'
        });
    };

    var renderReportsActPanel = function() {
    return '<div class="da-reports-act" data-region="reports-act">'
        + '<div class="da-reports-act-layout">'
        + '<section class="da-reports-act-card">'
        + '<div class="da-reports-act-card-head">'
        + '<div>'
        + '<h4>Act of Completed Works — Report Configuration</h4>'
        + '<p>Select company & period → load from LMS → review & download Excel</p>'
        + '</div>'
        + '<div class="da-reports-act-head-actions">'
        + '<span class="da-reports-chip">АВР / Excel</span>'
        + '<span class="da-reports-chip">auto-populate</span>'
        + '</div>'
        + '</div>'

        + '<div class="da-reports-act-status" data-region="reports-act-status" hidden></div>'

        + '<div class="da-reports-act-form">'
        + '<label><span>Client Company</span><select data-act-field="companyid"></select></label>'
        + '<label><span>Month</span><select data-act-field="month"></select></label>'
        + '<label><span>Year</span><select data-act-field="year"></select></label>'
        + '<label><span>Act Number</span><input type="text" data-act-field="actnumber" value="1"></label>'
        + '<label><span>Contract Number</span><input type="text" data-act-field="contractnumber" value=""></label>'
        + '<label><span>Service Provider</span><input type="text" data-act-field="provider"></label>'
        + '</div>'

        + '<div class="da-reports-act-table-head">'
        + '<h5>Volume of Services Rendered</h5>'
        + '<button type="button" class="da-row-action" data-action="reports-act-load">Load from LMS</button>'
        + '</div>'

        + '<div class="da-table-wrap da-reports-act-table-wrap">'
        + '<table class="da-table da-reports-act-table">'
        + '<thead><tr>'
        + '<th>№</th>'
        + '<th>Service / Course Name</th>'
        + '<th>Unit</th>'
        + '<th>LMS Count</th>'
        + '<th>Act Qty</th>'
        + '</tr></thead>'
        + '<tbody data-region="reports-act-rows">'
        + '<tr><td colspan="5" class="da-muted-cell">Select company and period, then click Load from LMS.</td></tr>'
        + '</tbody>'
        + '<tfoot>'
        + '<tr>'
        + '<th colspan="3">TOTAL:</th>'
        + '<th data-region="reports-act-lms-total">0</th>'
        + '<th data-region="reports-act-act-total">0</th>'
        + '</tr>'
        + '</tfoot>'
        + '</table>'
        + '</div>'

        + '<div class="da-reports-act-footer">'
        + '<div>'
        + '<button type="button" class="da-row-action" data-action="reports-act-reset">Reset to LMS</button> '
        + '<button type="button" class="da-row-action" data-action="reports-act-clear">Clear all</button>'
        + '</div>'
        + '<div class="da-reports-act-diff da-reports-act-diff-ok" data-region="reports-act-difference">Difference: 0</div>'
        + '<button type="button" class="da-row-action da-reports-act-download" data-action="reports-act-download">Download Excel (Act)</button>'
        + '</div>'

        + '</section>'

        + '<section class="da-reports-act-card da-reports-act-preview">'
        + '<div class="da-reports-act-card-head">'
        + '<div>'
        + '<h4>Act Preview</h4>'
        + '<p>Live preview of the Excel document contents</p>'
        + '</div>'
        + '<span class="da-reports-chip">live preview</span>'
        + '</div>'

        + '<div class="da-reports-act-summary">'
        + '<div><strong data-region="reports-act-preview-total">0</strong><span>Total services</span></div>'
        + '<div><strong data-region="reports-act-preview-types">0</strong><span>Course types</span></div>'
        + '<div><strong data-region="reports-act-preview-diff">+0</strong><span>Vs LMS count</span></div>'
        + '</div>'

        + '<div class="da-reports-act-document">'
        + '<h3>АКТ ВЫПОЛНЕННЫХ РАБОТ<br><span>(ОКАЗАННЫХ УСЛУГ) № <span data-region="reports-act-preview-number">1</span></span></h3>'
        + '<div class="da-reports-act-doc-line"></div>'
        + '<p><strong>Period:</strong> <span data-region="reports-act-preview-period">—</span></p>'
        + '<p><strong>Client:</strong> <span data-region="reports-act-preview-client">—</span></p>'
        + '<p><strong>Provider:</strong> <span data-region="reports-act-preview-provider">—</span></p>'
        + '<p><strong>Contract:</strong> <span data-region="reports-act-preview-contract">—</span></p>'
        + '<h5>Services Rendered</h5>'
        + '<ol data-region="reports-act-preview-rows"></ol>'
        + '<div class="da-reports-act-doc-line"></div>'
        + '<p class="da-reports-act-doc-total"><strong>TOTAL</strong> <strong data-region="reports-act-preview-total-bottom">0</strong></p>'
        + '</div>'
        + '</section>'
        + '</div>'
        + '</div>';
    };

    var renderCourseAnalyticsPanel = function() {
        return '<div class="da-course-analytics-panel" data-region="course-analytics-panel">'
            + '<div class="da-course-analytics-toolbar">'
            + '<div class="da-course-analytics-toolbar-copy">' + escapeHtml(text('courseAnalyticsHelp',
                'Hidden courses are excluded automatically. Turning analytics off here also excludes the course from dashboard calculations.')) + '</div>'
            + '<input type="search" class="da-course-analytics-search" data-action="course-analytics-search" placeholder="'
            + escapeHtml(text('courseAnalyticsSearch', 'Search courses')) + '">'
            + '</div>'
            + '<div class="da-course-analytics-results" data-region="course-analytics-results"></div>'
            + '</div>';
    };

    var renderCourseAnalyticsResults = function(root, data, state) {
        var panel = root.querySelector('[data-region="course-analytics-panel"]');
        var results = panel ? panel.querySelector('[data-region="course-analytics-results"]') : null;
        if (!panel || !results) {
            return;
        }

        var searchInput = panel.querySelector('[data-action="course-analytics-search"]');
        if (searchInput) {
            searchInput.value = (((state || {}).currentVisualOverrides) || {}).courseanalytics_search || '';
        }

        var totalcount = Math.max(0, Number(data.totalcount) || 0);
        var currentPage = Math.max(0, Number(data.page) || 0);
        var perpage = Math.max(10, Number(data.perpage) || 20);
        var totalpages = Math.max(1, Math.ceil(totalcount / perpage));
        var rows = data.rows || [];

        if (!rows.length) {
            results.innerHTML = '<div class="da-empty">' + escapeHtml(text('courseAnalyticsNoResults', 'No matching courses found.')) + '</div>';
            return;
        }

        var body = rows.map(function(row) {
            var visibilityLabel = row.visible ? text('courseAnalyticsVisible', 'Visible') : text('courseAnalyticsHidden', 'Hidden');
            var analyticsLabel = row.analyticsenabled ? text('courseAnalyticsIncluded', 'Included') : text('courseAnalyticsExcluded', 'Excluded');
            var toggleLabel = row.analyticsenabled ? text('courseAnalyticsToggleOn', 'On') : text('courseAnalyticsToggleOff', 'Off');

            return '<tr>'
                + '<td class="da-table-course-cell"><a class="da-table-link da-table-course-link" href="/course/view.php?id=' + escapeHtml(String(row.courseid)) + '" title="' + escapeHtml(row.fullname) + '">'
                    + escapeHtml(row.fullname) + '</a><div class="da-course-analytics-shortname">' + escapeHtml(row.shortname || '') + '</div></td>'
                + '<td><span class="da-badge da-badge-' + (row.visible ? 'ok' : 'muted') + '">' + escapeHtml(visibilityLabel) + '</span></td>'
                + '<td><span class="da-badge da-badge-' + (row.analyticsenabled ? 'ok' : 'muted') + '">' + escapeHtml(analyticsLabel) + '</span></td>'
                + '<td><button type="button" class="da-toggle' + (row.analyticsenabled ? ' is-on' : '')
                    + '" data-action="course-analytics-toggle" data-courseid="' + escapeHtml(String(row.courseid))
                    + '" data-enabled="' + (row.analyticsenabled ? '1' : '0') + '"><span class="da-toggle-track"></span><span class="da-toggle-label">'
                    + escapeHtml(toggleLabel) + '</span></button></td>'
                + '</tr>';
        }).join('');

        var pagination = '<div class="da-table-pagination">'
            + '<div class="da-table-pagination-status">' + escapeHtml(formatString(text('page', 'Page {$a}'), String((currentPage + 1) + ' / ' + totalpages))) + '</div>'
            + '<div class="da-table-pagination-controls">'
            + '<label class="da-table-perpage-label"><span>' + escapeHtml(text('perPage', 'Rows per page')) + '</span>'
            + '<select class="da-table-perpage" data-action="course-analytics-perpage">'
            + [20, 50, 100].map(function(size) {
                return '<option value="' + size + '"' + (size === perpage ? ' selected' : '') + '>' + size + '</option>';
            }).join('')
            + '</select></label>'
            + '<button type="button" class="da-pagination-button" data-action="course-analytics-page" data-page="' + Math.max(0, currentPage - 1) + '"'
            + (currentPage <= 0 ? ' disabled' : '') + '>' + escapeHtml(text('previous', 'Previous')) + '</button>'
            + '<button type="button" class="da-pagination-button" data-action="course-analytics-page" data-page="' + Math.min(totalpages - 1, currentPage + 1) + '"'
            + (currentPage >= totalpages - 1 ? ' disabled' : '') + '>' + escapeHtml(text('next', 'Next')) + '</button>'
            + '</div></div>';

        results.innerHTML = '<div class="da-table-wrap"><table class="da-table da-course-analytics-table">'
            + '<thead><tr><th scope="col">' + escapeHtml(text('courseAnalyticsHeaderCourse', 'Course')) + '</th>'
            + '<th scope="col">' + escapeHtml(text('courseAnalyticsHeaderVisibility', 'Visibility')) + '</th>'
            + '<th scope="col">' + escapeHtml(text('courseAnalyticsHeaderAnalytics', 'Analytics')) + '</th>'
            + '<th scope="col">' + escapeHtml(text('courseAnalyticsHeaderToggle', 'Toggle')) + '</th></tr></thead>'
            + '<tbody>' + body + '</tbody></table></div>' + pagination;
    };

    var updateCourseAnalyticsToggleUi = function(toggle, enabled) {
        var row = toggle ? toggle.closest('tr') : null;
        if (!toggle || !row) {
            return;
        }

        var visibilityCell = row.children[1];
        var analyticsCell = row.children[2];
        var analyticsBadge = analyticsCell ? analyticsCell.querySelector('.da-badge') : null;

        toggle.setAttribute('data-enabled', enabled ? '1' : '0');
        toggle.classList.toggle('is-on', !!enabled);

        var toggleLabel = toggle.querySelector('.da-toggle-label');
        if (toggleLabel) {
            toggleLabel.textContent = enabled
                ? text('courseAnalyticsToggleOn', 'On')
                : text('courseAnalyticsToggleOff', 'Off');
        }

        if (analyticsBadge) {
            analyticsBadge.textContent = enabled
                ? text('courseAnalyticsIncluded', 'Included')
                : text('courseAnalyticsExcluded', 'Excluded');
            analyticsBadge.className = 'da-badge da-badge-' + (enabled ? 'ok' : 'muted');
        }

        if (visibilityCell) {
            var visibilityBadge = visibilityCell.querySelector('.da-badge');
            if (visibilityBadge && visibilityBadge.textContent === text('courseAnalyticsHidden', 'Hidden')) {
                analyticsBadge.className = 'da-badge da-badge-muted';
            }
        }
    };

    var loadCourseAnalyticsControl = function(root, state, overrides) {
        var panel = root.querySelector('[data-region="course-analytics-panel"]');
        var results = panel ? panel.querySelector('[data-region="course-analytics-results"]') : null;
        if (!panel || !results) {
            return Promise.resolve();
        }

        setLoading(results);
        state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, overrides || {});

        return call('block_dashboardanalytics_get_course_analytics_control', {
            contextid: state.contextid,
            search: state.currentVisualOverrides.courseanalytics_search || '',
            page: Math.max(0, Number(state.currentVisualOverrides.courseanalytics_page) || 0),
            perpage: Math.max(10, Number(state.currentVisualOverrides.courseanalytics_perpage) || 20)
        }).then(function(response) {
            renderCourseAnalyticsResults(root, response, state);
            persistState(root, state);
            commitBrowserHistoryState(root, state, 'push');
        }).catch(function(error) {
            Notification.exception(error);
            results.innerHTML = '<div class="da-empty">' + escapeHtml(text('courseAnalyticsLoadError',
                'Unable to load course analytics controls.')) + '</div>';
        });
    };

    var forecastPeriodOrder = ['30days', '60days', '90days', '6months', '12months', '3years'];

    var forecastPeriodOptions = function(items) {
        var map = {};
        (items || []).forEach(function(item) {
            if (item.periodkey && !map[item.periodkey]) {
                map[item.periodkey] = item.rowlabel || item.periodkey;
            }
        });

        return forecastPeriodOrder.filter(function(key) {
            return !!map[key];
        }).map(function(key) {
            return {key: key, label: map[key]};
        });
    };

    var forecastSelectionMatches = function(selection, tabKey, periodKey) {
        return selection && selection.tabkey === tabKey && selection.periodkey === periodKey;
    };

    var getForecastSelection = function(state, panelKey, tabKey, periodKey) {
        var selection = (((state || {}).currentVisualOverrides) || {})['forecastselection_' + panelKey] || null;
        return forecastSelectionMatches(selection, tabKey, periodKey) ? selection : null;
    };

    var renderForecastWorkloadPanel = function(root, panel, state, visibleItems, selectedPanelTab) {
        var overrides = ((state || {}).currentVisualOverrides) || {};
        var periodOverrideKey = 'forecastperiod_' + panel.key;
        var periodOptions = forecastPeriodOptions(panel.items || []);
        var companyTabs = panel.tabs || [];
        var selectedPeriod = overrides[periodOverrideKey] || ((periodOptions[0] || {}).key) || '90days';
        var periodItems = visibleItems.filter(function(item) {
            return (item.periodkey || '') === selectedPeriod;
        });
        if (!periodItems.length && periodOptions.length) {
            selectedPeriod = periodOptions[0].key;
            periodItems = visibleItems.filter(function(item) {
                return (item.periodkey || '') === selectedPeriod;
            });
        }

        var selection = getForecastSelection(state, panel.key, selectedPanelTab || '', selectedPeriod);
        var selectedBar = selection ? periodItems.find(function(item) {
            return Number(item.fromts || 0) === Number(selection.fromts || 0)
                && Number(item.tots || 0) === Number(selection.tots || 0);
        }) : null;
        var selectedCourseId = selection ? Number(selection.courseid || 0) : 0;
        var maxTotal = periodItems.reduce(function(max, item) {
            return Math.max(max, Number(item.value) || 0);
        }, 1);

        var periodButtons = '<div class="da-forecast-segmented da-forecast-periods">' + periodOptions.map(function(option) {
            return '<button type="button" class="da-forecast-period'
                + (selectedPeriod === option.key ? ' is-active' : '')
                + '" data-action="forecast-period" data-panel="' + escapeHtml(panel.key)
                + '" data-period="' + escapeHtml(option.key) + '">'
                + escapeHtml(option.label) + '</button>';
        }).join('') + '</div>';

        var companyButtons = companyTabs.length
            ? '<div class="da-forecast-segmented da-forecast-companies">' + companyTabs.map(function(tab) {
                return '<button type="button" class="da-forecast-company'
                    + (tab.key === selectedPanelTab ? ' is-active' : '')
                    + '" data-action="panel-tab" data-panel="' + escapeHtml(panel.key)
                    + '" data-tabkey="' + escapeHtml(tab.key) + '">'
                    + escapeHtml(tab.label) + '</button>';
            }).join('') + '</div>'
            : '';

        var chartBars = periodItems.map(function(item) {
            var total = Number(item.value) || 0;
            var totalHeight = maxTotal > 0 ? Math.max(total > 0 ? 8 : 0, Math.min(100, (total / maxTotal) * 100)) : 0;
            var isSelected = !!selectedBar
                && Number(selectedBar.fromts || 0) === Number(item.fromts || 0)
                && Number(selectedBar.tots || 0) === Number(item.tots || 0);
            var segments = item.segments || [];

            return '<div class="da-forecast-bar-group' + (isSelected ? ' is-selected' : '') + '">'
                + '<div class="da-forecast-bar-total">' + escapeHtml(item.value || '0') + '</div>'
                + '<div class="da-forecast-bar-shell" style="height:' + totalHeight + '%">'
                + segments.map(function(segment) {
                    var totalSegments = Number(item.value) || 0;
                    var segmentHeight = totalSegments > 0 ? Math.max(0, (Number(segment.value) || 0) / totalSegments * 100) : 0;
                    var tooltipCount = formatString(text('forecastUsersLabel', '{$a} users'), String(segment.value || '0'));
                    return '<button type="button" class="da-forecast-segment'
                        + (selectedCourseId && Number(segment.courseid || 0) === selectedCourseId && isSelected ? ' is-selected' : '')
                        + '" data-action="forecast-segment"'
                        + ' data-panel="' + escapeHtml(panel.key) + '"'
                        + ' data-tabkey="' + escapeHtml(selectedPanelTab || '') + '"'
                        + ' data-period="' + escapeHtml(selectedPeriod) + '"'
                        + ' data-courseid="' + escapeHtml(String(segment.courseid || 0)) + '"'
                        + ' data-fromts="' + escapeHtml(String(segment.fromts || item.fromts || 0)) + '"'
                        + ' data-tots="' + escapeHtml(String(segment.tots || item.tots || 0)) + '"'
                        + ' data-label="' + escapeHtml(segment.label || '') + '"'
                        + ' data-value="' + escapeHtml(String(segment.value || '0')) + '"'
                        + ' data-colour="' + escapeHtml(segment.colour || '#3b82f6') + '"'
                        + ' data-tooltip-course="' + escapeHtml(segment.label || '') + '"'
                        + ' data-tooltip-count="' + escapeHtml(tooltipCount) + '"'
                        + ' data-tooltip-window="' + escapeHtml(item.label || '') + '"'
                        + ' style="height:' + segmentHeight + '%; background:' + escapeHtml(segment.colour || '#3b82f6') + '"></button>';
                }).join('')
                + '</div>'
                + '<button type="button" class="da-forecast-bar-label" data-action="forecast-bar"'
                + ' data-panel="' + escapeHtml(panel.key) + '"'
                + ' data-tabkey="' + escapeHtml(selectedPanelTab || '') + '"'
                + ' data-period="' + escapeHtml(selectedPeriod) + '"'
                + ' data-fromts="' + escapeHtml(String(item.fromts || 0)) + '"'
                + ' data-tots="' + escapeHtml(String(item.tots || 0)) + '">'
                + escapeHtml(item.label || '') + '</button>'
                + '</div>';
        }).join('');

        var yAxisSteps = [100, 75, 50, 25, 0].map(function(step) {
            return '<div class="da-forecast-y-step" style="bottom:' + step + '%"><span>'
                + escapeHtml(String(Math.round((maxTotal * step) / 100))) + '</span></div>';
        }).join('');

        var summarySegments = selectedBar ? (selectedBar.segments || []).slice().sort(function(a, b) {
            return (Number(b.value) || 0) - (Number(a.value) || 0);
        }) : [];
        var summaryBody = summarySegments.length
            ? summarySegments.map(function(segment) {
                var total = Number(selectedBar.value) || 0;
                var width = total > 0 ? Math.max(2, Math.min(100, ((Number(segment.value) || 0) / total) * 100)) : 0;
                return '<button type="button" class="da-forecast-summary-row'
                    + (selectedCourseId && Number(segment.courseid || 0) === selectedCourseId ? ' is-selected' : '')
                    + '" data-action="forecast-summary-course"'
                    + ' data-panel="' + escapeHtml(panel.key) + '"'
                    + ' data-tabkey="' + escapeHtml(selectedPanelTab || '') + '"'
                    + ' data-period="' + escapeHtml(selectedPeriod) + '"'
                    + ' data-fromts="' + escapeHtml(String(selectedBar.fromts || 0)) + '"'
                    + ' data-tots="' + escapeHtml(String(selectedBar.tots || 0)) + '"'
                    + ' data-courseid="' + escapeHtml(String(segment.courseid || 0)) + '"'
                    + ' data-label="' + escapeHtml(segment.label || '') + '"'
                    + ' data-barlabel="' + escapeHtml(selectedBar.label || '') + '">'
                    + '<span class="da-forecast-summary-head"><span class="da-forecast-summary-swatch" style="background:'
                    + escapeHtml(segment.colour || '#3b82f6') + '"></span><span class="da-forecast-summary-name">'
                    + escapeHtml(segment.label || '') + '</span><strong>' + escapeHtml(String(segment.value || '0')) + '</strong></span>'
                    + '<span class="da-forecast-summary-track"><span style="width:' + width + '%; background:'
                    + escapeHtml(segment.colour || '#3b82f6') + '"></span></span></button>';
            }).join('')
            : '<div class="da-empty">' + escapeHtml(text('forecastSummaryEmpty',
                'Click a bar label to see the course breakdown.')) + '</div>';

        var selectedMeta = selectedBar ? selectedBar.meta : '';
        var tableHeadline = selectedBar ? escapeHtml(selectedBar.label || '') : '';
        var tableCourse = selection && selection.course ? escapeHtml(selection.course) : '';
        var totalInWindow = periodItems.reduce(function(sum, item) {
            return sum + (Number(item.value) || 0);
        }, 0);

        return '<div class="da-forecast-workload" data-region="forecast-workload" data-panel-key="' + escapeHtml(panel.key) + '">'
            + '<div class="da-forecast-toolbar">'
            + '<div class="da-forecast-toolbar-group"><span class="da-forecast-toolbar-label">'
            + escapeHtml(text('filter:daterange', 'Period')) + '</span>' + periodButtons + '</div>'
            + (companyButtons ? '<div class="da-forecast-toolbar-group"><span class="da-forecast-toolbar-label">'
                + escapeHtml(text('filter:companies', 'Company')) + '</span>' + companyButtons + '</div>' : '')
            + '<div class="da-forecast-toolbar-spacer"></div>'
            + '<div class="da-forecast-toolbar-stat"><span>' + escapeHtml(text('forecastInWindowLabel', 'In this window')) + '</span><strong>'
            + escapeHtml(formatString(text('forecastRenewalsLabel', '{$a} renewals'), String(totalInWindow || 0))) + '</strong></div>'
            + '</div>'
            + '<div class="da-forecast-layout">'
            + '<section class="da-forecast-chart-card">'
            + '<div class="da-forecast-chart-wrap">'
            + '<div class="da-forecast-y-axis">' + yAxisSteps + '</div>'
            + '<div class="da-forecast-chart-area">'
            + '<div class="da-forecast-bars">' + chartBars + '</div>'
            + '<div class="da-forecast-tooltip" data-region="forecast-tooltip" hidden>'
            + '<div class="da-forecast-tooltip-course"><i></i><span></span></div>'
            + '<div class="da-forecast-tooltip-meta"></div>'
            + '</div>'
            + '</div></div>'
            + '</section>'
            + '<aside class="da-forecast-summary-card">'
            + '<h6>' + escapeHtml(selectedBar ? (selectedBar.label || text('forecastSummaryTitle', 'Course summary')) : text('forecastSummaryTitle', 'Course summary')) + '</h6>'
            + '<p>' + escapeHtml(selectedMeta || '') + '</p>'
            + '<div class="da-forecast-summary-list">' + summaryBody + '</div>'
            + '<div class="da-forecast-summary-total"><span>' + escapeHtml(text('forecastSummaryTotal', 'Total')) + '</span><strong>'
            + escapeHtml(selectedBar ? String(selectedBar.value || '0') : '0') + '</strong></div>'
            + '</aside>'
            + '</div>'
            + '<section class="da-forecast-table-card">'
            + '<div class="da-forecast-table-head"><h6>' + escapeHtml(text('learningMatrixTitle', 'The Learning Matrix')) + '</h6>'
            + '<span class="da-forecast-table-count" data-region="forecast-row-count"></span></div>'
            + '<div class="da-forecast-table-filters">'
            + '<span class="da-forecast-filterchip" data-region="forecast-selection">' + tableHeadline + '</span>'
            + '<span class="da-forecast-filterchip da-forecast-filterchip-course" data-region="forecast-course-chip"'
            + (tableCourse ? '' : ' hidden') + '>' + tableCourse + '</span>'
            + '<button type="button" class="da-forecast-clear" data-action="forecast-clear-course" data-panel="'
            + escapeHtml(panel.key) + '"' + (tableCourse ? '' : ' hidden') + '>'
            + escapeHtml(text('forecastClearCourseLabel', 'Clear course filter')) + '</button></div>'
            + '<div class="da-forecast-table-body" data-region="forecast-table-body">'
            + '<div class="da-empty">' + escapeHtml(text('forecastTableEmpty',
                'Click a bar or a course segment to open The Learning Matrix.')) + '</div>'
            + '</div></section></div>';
    };

    var loadForecastInlineTable = function(root, state, panelKey) {
        var overrides = (((state || {}).currentVisualOverrides) || {}) || {};
        var selection = overrides['forecastselection_' + panelKey] || null;
        var panel = root.querySelector('.da-visual-panel[data-panel-key="' + panelKey + '"]');
        var tableBody = panel ? panel.querySelector('[data-region="forecast-table-body"]') : null;
        var selectionNode = panel ? panel.querySelector('[data-region="forecast-selection"]') : null;
        var courseChipNode = panel ? panel.querySelector('[data-region="forecast-course-chip"]') : null;
        var clearCourseButton = panel ? panel.querySelector('[data-action="forecast-clear-course"]') : null;
        var countNode = panel ? panel.querySelector('[data-region="forecast-row-count"]') : null;
        var activePanelTab = panel ? panel.querySelector('.da-panel-tab.is-active') : null;
        if (!activePanelTab) {
            activePanelTab = panel ? panel.querySelector('.da-forecast-company.is-active') : null;
        }
        var currentPanelTab = activePanelTab ? (activePanelTab.getAttribute('data-tabkey') || '') : '';
        var activePeriod = panel ? panel.querySelector('.da-forecast-period.is-active') : null;
        var currentPeriod = activePeriod ? (activePeriod.getAttribute('data-period') || '') : (overrides['forecastperiod_' + panelKey] || '');
        if (!panel || !tableBody) {
            return Promise.resolve();
        }

        if (!selection || !selection.fromts || !selection.tots
                || (selection.tabkey || '') !== currentPanelTab
                || (selection.periodkey || '') !== currentPeriod) {
            if (selectionNode) {
                selectionNode.textContent = '';
            }
            if (courseChipNode) {
                courseChipNode.textContent = '';
                courseChipNode.hidden = true;
            }
            if (clearCourseButton) {
                clearCourseButton.hidden = true;
            }
            if (countNode) {
                countNode.textContent = '';
            }
            tableBody.innerHTML = '<div class="da-empty">' + escapeHtml(text('forecastTableEmpty',
                'Click a bar or a course segment to open The Learning Matrix.')) + '</div>';
            return Promise.resolve();
        }

        var drilldownkey = state.dashboardkey === 'client' ? 'client_forecast_documents' : 'company_forecast_documents';
        var filterOverrides = {
            expirystartts: Number(selection.fromts) || 0,
            expiryendts: Number(selection.tots) || 0
        };
        if (selection.courseid) {
            filterOverrides.courseids = [Number(selection.courseid)];
        }

        var currentPage = Math.max(0, Number(overrides['forecastpage_' + panelKey]) || 0);
        var perpage = Math.max(10, Number(overrides['forecastperpage_' + panelKey]) || 20);

        tableBody.innerHTML = '<div class="da-loading">' + escapeHtml(text('forecastTableLoading', 'Loading Learning Matrix...')) + '</div>';

        if (selectionNode) {
            selectionNode.textContent = selection.label || '';
        }
        if (courseChipNode) {
            courseChipNode.textContent = selection.course || '';
            courseChipNode.hidden = !selection.course;
        }
        if (clearCourseButton) {
            clearCourseButton.hidden = !selection.course;
        }

        return call('block_dashboardanalytics_get_drilldown', {
            contextid: state.contextid,
            dashboardkey: state.dashboardkey,
            drilldownkey: drilldownkey,
            filters: JSON.stringify(readFilters(root, state, filterOverrides)),
            page: currentPage,
            perpage: perpage
        }).then(function(response) {
            var expiryBoundaries = (function() {
                var expiries = (response.rows || []).map(function(row) {
                    var expiryCell = (row.cells || []).find(function(cell) {
                        return cell.key === 'expiry';
                    });
                    return expiryCell ? String(expiryCell.value || '').trim() : '';
                }).filter(function(value) {
                    return value !== '';
                });

                if (!expiries.length) {
                    return '';
                }

                if (expiries.length === 1 || expiries[0] === expiries[expiries.length - 1]) {
                    return expiries[0];
                }

                return expiries[0] + ' - ' + expiries[expiries.length - 1];
            })();

            if (selectionNode) {
                selectionNode.textContent = expiryBoundaries || selection.label || '';
            }
            if (countNode) {
                countNode.textContent = formatString(text('rows', '{$a} rows'), String(response.totalcount || 0));
            }
            tableBody.innerHTML = buildDrilldownTableMarkup(root, response, state, {
                page: currentPage,
                perpage: perpage,
                drilldownkey: drilldownkey,
                overrides: filterOverrides,
                actionPrefix: 'forecast-table'
            });
        }).catch(function(error) {
            Notification.exception(error);
            tableBody.innerHTML = '<div class="da-empty">' + escapeHtml(text('noMatchingRows', 'No matching rows.')) + '</div>';
        });
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
            return ['table', 'servererrors', 'serversettings', 'overviewsummary', 'companyhealth', 'alerts', 'qualityratingtable', 'heatmap', 'reportsact', 'compliancetrendline', 'forecastworkload'].indexOf(panel.type) !== -1
                || ['coursecompliance', 'newhirerisk'].indexOf(panel.key) !== -1
                || panel.type === 'analyticscourses';
        };
        if (!panels.length) {
            container.innerHTML = '<div class="da-empty">' + escapeHtml(text('noVisualData', 'No visual data available.')) + '</div>';
            return;
        }

        container.innerHTML = '<div class="da-visual-grid' + (hasServerPanels ? ' da-visual-grid-server' : '') + '">' + panels.map(function(panel) {
            var items = panel.items || [];
            var body = '';
            var panelTabOverrideKey = 'paneltab_' + panel.key;
            var panelTabs = panel.tabs || [];
            var selectedPanelTab = (((state || {}).currentVisualOverrides) || {})[panelTabOverrideKey]
                || ((panelTabs.filter(function(tab) { return !!tab.active; })[0] || {}).key)
                || ((panelTabs[0] || {}).key)
                || '';
            var visibleItems = selectedPanelTab ? items.filter(function(item) {
                return (item.groupkey || '') === selectedPanelTab;
            }) : items;

            if (selectedPanelTab && !visibleItems.length && panelTabs.length) {
                selectedPanelTab = (panelTabs[0] || {}).key || '';
                visibleItems = selectedPanelTab ? items.filter(function(item) {
                    return (item.groupkey || '') === selectedPanelTab;
                }) : items;
            }

            var panelTabMarkup = '';
            if (panelTabs.length && panel.type !== 'heatmap') {
                panelTabMarkup = '<div class="da-panel-tabs">' + panelTabs.map(function(tab) {
                    return '<button type="button" class="da-panel-tab'
                        + (tab.key === selectedPanelTab ? ' is-active' : '')
                        + '" data-action="panel-tab" data-panel="' + escapeHtml(panel.key)
                        + '" data-tabkey="' + escapeHtml(tab.key) + '">'
                        + escapeHtml(tab.label) + '</button>';
                }).join('') + '</div>';
            }
            if (panel.type === 'reportsact') {
                body = renderReportsActPanel();
            } else if (panel.type === 'analyticscourses') {
                body = renderCourseAnalyticsPanel();
            } else if (panel.type === 'forecastworkload') {
                body = panelTabMarkup + renderForecastWorkloadPanel(root, panel, state, visibleItems, selectedPanelTab);
            } else if (!(panel.type === 'heatmap' ? items : visibleItems).length) {
                body = '<div class="da-empty">' + escapeHtml(panel.emptymessage || text('noMatchingRows', 'No matching rows.')) + '</div>';
            } else if (panel.type === 'overviewsummary') {
                body = '<div class="da-overview-summary-grid">' + visibleItems.map(function(item) {
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
                var legend = (((visibleItems[0] || {}).segments) || []).map(function(segment) {
                    return '<span class="da-multibars-legend-item"><span class="da-dot da-dot-' + escapeHtml(segment.status) + '"></span>' + escapeHtml(segment.label) + '</span>';
                }).join('');
                body = (isPlatformGrowth ? '<div class="da-platform-growth-head">' + periodButtons + '</div>' : '')
                    + (legend ? '<div class="da-multibars-legend">' + legend + '</div>' : '')
                    + '<div class="da-multibars-chart' + (isPlatformGrowth ? ' da-multibars-chart-growth' : '') + '">' + visibleItems.map(function(item) {
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
                var turnoverLegend = (((visibleItems[0] || {}).segments) || []).slice(0, 3).map(function(segment) {
                    return '<span class="da-turnover-legend-item"><span class="da-dot da-dot-' + escapeHtml(segment.status) + '"></span>'
                        + escapeHtml(segment.label) + '</span>';
                }).join('');
                var comboMax = 1;
                visibleItems.forEach(function(item) {
                    (item.segments || []).forEach(function(segment) {
                        comboMax = Math.max(comboMax, Math.abs(Number(segment.value) || 0));
                    });
                });
                var topY = 14;
                var zeroY = 54;
                var bottomY = 94;
                var halfHeight = zeroY - topY;
                var barWidth = 4;
                var step = visibleItems.length > 0 ? (100 / visibleItems.length) : 100;
                var blueBars = [];
                var redBars = [];
                var netPoints = [];
                var axisLabels = [];

                visibleItems.forEach(function(item, index) {
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
                    + '<div class="da-turnover-rate-list">' + visibleItems.map(function(item) {
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
                    + '</div>';
            } else if (panel.key === 'newhirerisk') {
                body = '<div class="da-newhire-risk-wrap">'
                    + '<div class="da-newhire-risk-list">' + visibleItems.map(function(item) {
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
                    + '</div>';
            } else if (panel.type === 'activitysnapshot') {
                var metrics = visibleItems.slice(0, 4);
                var courses = visibleItems.slice(4);
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
                    + visibleItems.map(function(item) {
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
                body = '<div class="da-alerts-grid">' + visibleItems.map(function(item) {
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
                    + '<div class="da-server-capacity-grid">' + visibleItems.map(function(item) {
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
                var forecast = visibleItems[0] || {segments: [], value: '', status: 'muted', meta: ''};
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
            } else if (panel.type === 'compliancetrendline') {
                var trendOverrides = (((state || {}).currentVisualOverrides) || {});
                var selectedRange = Math.max(3, Math.min(12, Number(trendOverrides.compliancetrendperiod) || 12));
                var compliantThreshold = Number(trendOverrides.compliancenorm);
                if (isNaN(compliantThreshold)) {
                    compliantThreshold = Number(panel.threshold) || 80;
                }
                var criticalThreshold = Number(trendOverrides.compliancecritical);
                if (isNaN(criticalThreshold)) {
                    criticalThreshold = Number(panel.secondarythreshold) || 70;
                }

                compliantThreshold = Math.max(1, Math.min(100, compliantThreshold));
                criticalThreshold = Math.max(0, Math.min(99, criticalThreshold));
                if (criticalThreshold >= compliantThreshold) {
                    criticalThreshold = Math.max(0, compliantThreshold - 1);
                }

                var aggregateSegments = [];
                var sourceSeries = visibleItems.filter(function(item) {
                    return Array.isArray(item.segments) && item.segments.length;
                });

                if (sourceSeries.length) {
                    var segmentCount = sourceSeries.reduce(function(max, item) {
                        return Math.max(max, (item.segments || []).length);
                    }, 0);

                    for (var segmentIndex = 0; segmentIndex < segmentCount; segmentIndex++) {
                        var monthPoints = sourceSeries.map(function(item) {
                            return (item.segments || [])[segmentIndex] || null;
                        }).filter(function(segment) {
                            return !!segment;
                        });

                        if (!monthPoints.length) {
                            continue;
                        }

                        var averagePercent = monthPoints.reduce(function(sum, segment) {
                            return sum + (Number(segment.percent) || 0);
                        }, 0) / monthPoints.length;

                        aggregateSegments.push({
                            label: monthPoints[0].label || '',
                            percent: averagePercent
                        });
                    }
                }

                if (!aggregateSegments.length) {
                    body = '<div class="da-empty">' + escapeHtml(panel.emptymessage || text('noMatchingRows', 'No matching rows.')) + '</div>';
                } else {
                    var displayedSegments = aggregateSegments.slice(Math.max(0, aggregateSegments.length - Math.min(selectedRange, aggregateSegments.length)));
                    var lastSegment = displayedSegments[displayedSegments.length - 1] || {percent: 0, label: ''};
                    var previousSegment = displayedSegments.length > 1 ? displayedSegments[displayedSegments.length - 2] : null;
                    var currentPercent = Number(lastSegment.percent) || 0;
                    var delta = previousSegment ? currentPercent - (Number(previousSegment.percent) || 0) : 0;
                    var currentStatus = currentPercent >= compliantThreshold ? 'ok' : (currentPercent >= criticalThreshold ? 'warning' : 'danger');
                    var yTicksTrend = [0, 20, 40, 60, 80, 100];
                    var chartLeftTrend = 3.2;
                    var chartRightTrend = 3.5;
                    var chartTopTrend = 10;
                    var chartBottomTrend = 11;
                    var chartWidthTrend = 100 - chartLeftTrend - chartRightTrend;
                    var chartHeightTrend = 100 - chartTopTrend - chartBottomTrend;
                    var xForTrend = function(index, total) {
                        if (total <= 1) {
                            return chartLeftTrend;
                        }
                        return chartLeftTrend + ((index / (total - 1)) * chartWidthTrend);
                    };
                    var yForTrend = function(percent) {
                        var safePercent = Math.max(0, Math.min(100, Number(percent) || 0));
                        return chartTopTrend + ((100 - safePercent) / 100) * chartHeightTrend;
                    };
                    var zoneColorForValue = function(value) {
                        if (value >= compliantThreshold) {
                            return '#107c10';
                        }
                        if (value >= criticalThreshold) {
                            return '#d9822b';
                        }
                        return '#d13438';
                    };
                    var lineSegments = [];
                    for (var trendIndex = 1; trendIndex < displayedSegments.length; trendIndex++) {
                        var previousPoint = displayedSegments[trendIndex - 1];
                        var currentPoint = displayedSegments[trendIndex];
                        lineSegments.push('<line x1="' + xForTrend(trendIndex - 1, displayedSegments.length).toFixed(2)
                            + '" y1="' + yForTrend(previousPoint.percent).toFixed(2)
                            + '" x2="' + xForTrend(trendIndex, displayedSegments.length).toFixed(2)
                            + '" y2="' + yForTrend(currentPoint.percent).toFixed(2)
                            + '" class="da-compliance-trendline-path" style="stroke:' + zoneColorForValue(currentPoint.percent) + '"></line>');
                    }
                    var xLabelsTrend = displayedSegments.map(function(segment, index) {
                        return '<span class="da-compliance-trendline-x-label" style="left:' + xForTrend(index, displayedSegments.length).toFixed(2) + '%">'
                            + escapeHtml(segment.label || '') + '</span>';
                    }).join('');
                    var hoverTargetsTrend = displayedSegments.map(function(segment, index) {
                        return '<button type="button" class="da-compliance-trendline-hover-target"'
                            + ' data-action="compliance-hover"'
                            + ' data-index="' + index + '"'
                            + ' data-label="' + escapeHtml(segment.label || '') + '"'
                            + ' data-value="' + escapeHtml(formatPercent(segment.percent) + '%') + '"'
                            + ' style="left:' + xForTrend(index, displayedSegments.length).toFixed(2) + '%"'
                            + ' aria-label="' + escapeHtml((segment.label || '') + ' ' + formatPercent(segment.percent) + '%') + '"></button>';
                    }).join('');
                    var yGridTrend = yTicksTrend.map(function(tick) {
                        var y = yForTrend(tick).toFixed(2);
                        return '<line x1="' + chartLeftTrend + '" y1="' + y + '" x2="' + (100 - chartRightTrend) + '" y2="' + y + '" class="da-compliance-trendline-grid"></line>';
                    }).join('');
                    var yLabelsTrend = yTicksTrend.map(function(tick) {
                        return '<span class="da-compliance-trendline-y-label" style="top:' + yForTrend(tick).toFixed(2) + '%">' + escapeHtml(tick + '%') + '</span>';
                    }).join('');
                    var thresholdNormY = yForTrend(compliantThreshold).toFixed(2);
                    var thresholdCriticalY = yForTrend(criticalThreshold).toFixed(2);
                    var compliantZoneHeight = (yForTrend(compliantThreshold) - chartTopTrend).toFixed(2);
                    var warningZoneHeight = (yForTrend(criticalThreshold) - yForTrend(compliantThreshold)).toFixed(2);
                    var dangerZoneHeight = ((chartTopTrend + chartHeightTrend) - yForTrend(criticalThreshold)).toFixed(2);
                    var currentX = xForTrend(displayedSegments.length - 1, displayedSegments.length).toFixed(2);
                    var currentY = yForTrend(currentPercent).toFixed(2);
                    var currentValueLabelY = Math.max(chartTopTrend + 2, Number(currentY) - 4).toFixed(2);
                    var deltaText = '';
                    var deltaClass = 'muted';
                    if (previousSegment) {
                        if (delta > 0) {
                            deltaClass = 'ok';
                            deltaText = '\u25B2 +' + formatPercent(delta) + ' ' + formatString(text('pointsVsLastMonth', '{$a} pts vs last month'), '').trim();
                        } else if (delta < 0) {
                            deltaClass = 'danger';
                            deltaText = '\u25BC -' + formatPercent(Math.abs(delta)) + ' ' + formatString(text('pointsVsLastMonth', '{$a} pts vs last month'), '').trim();
                        } else {
                            deltaClass = 'muted';
                            deltaText = text('noChangeVsLastMonth', 'No change vs last month');
                        }
                    }

                    body = '<div class="da-compliance-trendline">'
                        + '<div class="da-compliance-trendline-head">'
                        + '<div class="da-compliance-trendline-periods">'
                        + '<button type="button" class="da-compliance-trendline-period' + (selectedRange === 3 ? ' is-active' : '') + '" data-action="compliance-period" data-period="3">' + escapeHtml(text('months3Short', '3M')) + '</button>'
                        + '<button type="button" class="da-compliance-trendline-period' + (selectedRange === 6 ? ' is-active' : '') + '" data-action="compliance-period" data-period="6">' + escapeHtml(text('months6Short', '6M')) + '</button>'
                        + '<button type="button" class="da-compliance-trendline-period' + (selectedRange === 12 ? ' is-active' : '') + '" data-action="compliance-period" data-period="12">' + escapeHtml(text('months12Short', '12M')) + '</button>'
                        + '</div>'
                        + '<div class="da-compliance-trendline-kpi da-compliance-trendline-kpi-' + escapeHtml(currentStatus) + '">'
                        + '<span class="da-compliance-trendline-kpi-label">' + escapeHtml(text('currentCompliance', 'Current compliance')) + '</span>'
                        + '<strong class="da-compliance-trendline-kpi-value">' + escapeHtml(formatPercent(currentPercent) + '%') + '</strong>'
                        + '<span class="da-compliance-trendline-kpi-delta da-text-' + escapeHtml(deltaClass) + '">' + escapeHtml(deltaText) + '</span>'
                        + '</div>'
                        + '</div>'
                        + '<div class="da-compliance-trendline-chart">'
                        + '<svg class="da-compliance-trendline-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">'
                        + '<rect x="' + chartLeftTrend + '" y="' + chartTopTrend + '" width="' + chartWidthTrend + '" height="' + compliantZoneHeight + '" class="da-compliance-trendline-zone-ok"></rect>'
                        + '<rect x="' + chartLeftTrend + '" y="' + thresholdNormY + '" width="' + chartWidthTrend + '" height="' + warningZoneHeight + '" class="da-compliance-trendline-zone-warning"></rect>'
                        + '<rect x="' + chartLeftTrend + '" y="' + thresholdCriticalY + '" width="' + chartWidthTrend + '" height="' + dangerZoneHeight + '" class="da-compliance-trendline-zone-danger"></rect>'
                        + yGridTrend
                        + '<line x1="' + chartLeftTrend + '" y1="' + thresholdNormY + '" x2="' + (100 - chartRightTrend) + '" y2="' + thresholdNormY + '" class="da-compliance-trendline-threshold da-compliance-trendline-threshold-ok"></line>'
                        + '<line x1="' + chartLeftTrend + '" y1="' + thresholdCriticalY + '" x2="' + (100 - chartRightTrend) + '" y2="' + thresholdCriticalY + '" class="da-compliance-trendline-threshold da-compliance-trendline-threshold-danger"></line>'
                        + lineSegments.join('')
                        + '<line x1="' + chartLeftTrend + '" y1="' + (chartTopTrend + chartHeightTrend) + '" x2="' + (100 - chartRightTrend) + '" y2="' + (chartTopTrend + chartHeightTrend) + '" class="da-compliance-trendline-axis"></line>'
                        + '<line x1="' + chartLeftTrend + '" y1="' + chartTopTrend + '" x2="' + chartLeftTrend + '" y2="' + (chartTopTrend + chartHeightTrend) + '" class="da-compliance-trendline-axis"></line>'
                        + '</svg>'
                        + '<div class="da-compliance-trendline-overlay">'
                        + yLabelsTrend
                        + xLabelsTrend
                        + '<span class="da-compliance-trendline-crosshair" data-region="compliance-crosshair" hidden></span>'
                        + '<span class="da-compliance-trendline-tooltip" data-region="compliance-tooltip" hidden></span>'
                        + hoverTargetsTrend
                        + '<span class="da-compliance-trendline-threshold-label da-text-ok" style="top:' + thresholdNormY + '%">' + escapeHtml(formatPercent(compliantThreshold) + '%') + '</span>'
                        + '<span class="da-compliance-trendline-threshold-label da-text-danger" style="top:' + thresholdCriticalY + '%">' + escapeHtml(formatPercent(criticalThreshold) + '%') + '</span>'
                        + '<span class="da-compliance-trendline-current-dot" style="left:' + currentX + '%; top:' + currentY + '%; background:' + zoneColorForValue(currentPercent) + '"></span>'
                        + '<span class="da-compliance-trendline-current-label" style="left:' + currentX + '%; top:' + currentValueLabelY + '%">' + escapeHtml(formatPercent(currentPercent) + '%') + '</span>'
                        + '</div>'
                        + '</div>'
                        + '<div class="da-compliance-trendline-legend">'
                        + '<span class="da-turnover-legend-item"><span class="da-dot da-dot-ok"></span>' + escapeHtml(text('heatmapCompliantLegend', '>=80% Compliant')) + '</span>'
                        + '<span class="da-turnover-legend-item"><span class="da-dot da-dot-warning"></span>' + escapeHtml(text('heatmapRiskLegend', '70–79% At risk')) + '</span>'
                        + '<span class="da-turnover-legend-item"><span class="da-dot da-dot-danger"></span>' + escapeHtml(text('heatmapCriticalLegend', '<70% Critical')) + '</span>'
                        + '</div>'
                        + '<div class="da-compliance-trendline-footer">'
                        + '<span class="da-compliance-trendline-footer-chip"><span class="da-compliance-trendline-footer-line"></span>Compliance line</span>'
                        + '<label class="da-compliance-trendline-threshold-control"><span class="da-dot da-dot-ok"></span>' + escapeHtml(text('compliantThresholdTitle', 'Compliant threshold'))
                        + '<input type="number" min="0" max="100" step="1" value="' + escapeHtml(formatPercent(compliantThreshold)) + '" data-action="compliance-threshold" data-threshold-key="compliancenorm">%</label>'
                        + '<label class="da-compliance-trendline-threshold-control"><span class="da-dot da-dot-danger"></span>' + escapeHtml(text('criticalThresholdTitle', 'Critical threshold'))
                        + '<input type="number" min="0" max="100" step="1" value="' + escapeHtml(formatPercent(criticalThreshold)) + '" data-action="compliance-threshold" data-threshold-key="compliancecritical">%</label>'
                        + '</div>'
                        + '</div>';
                }
            } else if (panel.type === 'compliancetrendchart') {
                var monthLabels = ((visibleItems[0] || {}).segments || []).map(function(segment) {
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
                var trendSeries = visibleItems.map(function(item) {
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
                    + '<div class="da-compliance-snapshot-list">' + visibleItems.map(function(item) {
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
            } else if (panel.type === 'heatmap') {
                var heatmapTabs = panel.tabs || [];
                var selectedHeatmapTab = (((state || {}).currentVisualOverrides) || {}).heatmapcompany
                    || ((heatmapTabs.filter(function(tab) { return !!tab.active; })[0] || {}).key)
                    || ((heatmapTabs[0] || {}).key)
                    || 'all';
                var visibleHeatmapItems = items.filter(function(item) {
                    return (item.groupkey || 'all') === selectedHeatmapTab;
                });
                if (!visibleHeatmapItems.length && heatmapTabs.length) {
                    selectedHeatmapTab = (heatmapTabs[0] || {}).key || 'all';
                    visibleHeatmapItems = items.filter(function(item) {
                        return (item.groupkey || 'all') === selectedHeatmapTab;
                    });
                }
                var selectedHeatmapMeta = heatmapTabs.filter(function(tab) {
                    return tab.key === selectedHeatmapTab;
                })[0] || {};
                var heatmapTone = function(percent, hasValue) {
                    if (!hasValue) {
                        return {
                            background: '#f8fafc',
                            color: '#8b97a8',
                            ring: '#d9e2ee'
                        };
                    }

                    var safe = Math.max(0, Math.min(100, Number(percent) || 0));
                    if (safe >= 90) {
                        return {background: '#0f8a55', color: '#ffffff', ring: '#0a6b41'};
                    }
                    if (safe >= 80) {
                        return {background: '#4cc38a', color: '#06371f', ring: '#2fa76f'};
                    }
                    if (safe >= 70) {
                        return {background: '#f5a623', color: '#4a2c00', ring: '#cc8408'};
                    }
                    if (safe >= 60) {
                        return {background: '#ff8566', color: '#5c1a0a', ring: '#e46343'};
                    }
                    if (safe >= 50) {
                        return {background: '#fa5a3d', color: '#5c1005', ring: '#d63c21'};
                    }
                    if (safe >= 40) {
                        return {background: '#e13a1e', color: '#ffffff', ring: '#b32a13'};
                    }
                    if (safe >= 30) {
                        return {background: '#c22412', color: '#ffffff', ring: '#96190b'};
                    }
                    if (safe >= 20) {
                        return {background: '#a11a0c', color: '#ffffff', ring: '#7a1107'};
                    }
                    if (safe >= 10) {
                        return {background: '#7f1207', color: '#ffffff', ring: '#5c0b04'};
                    }
                    return {background: '#5e0c04', color: '#ffffff', ring: '#3f0702'};
                };
                var heatmapLegendRamp = function(colors) {
                    return '<span class="da-heatmap-ramp">' + colors.map(function(color) {
                        return '<i style="background:' + escapeHtml(color) + '"></i>';
                    }).join('') + '</span>';
                };
                var rowLabels = [];
                var columnLabels = [];
                var matrix = {};

                visibleHeatmapItems.forEach(function(item) {
                    var rowLabel = String(item.rowlabel || '').trim();
                    var columnLabel = String(item.columnlabel || '').trim();
                    if (!rowLabel || !columnLabel) {
                        return;
                    }
                    if (rowLabels.indexOf(rowLabel) === -1) {
                        rowLabels.push(rowLabel);
                    }
                    if (columnLabels.indexOf(columnLabel) === -1) {
                        columnLabels.push(columnLabel);
                    }
                    if (!matrix[rowLabel]) {
                        matrix[rowLabel] = {};
                    }
                    matrix[rowLabel][columnLabel] = item;
                });

                body = '<div class="da-heatmap-wrap">'
                    + (heatmapTabs.length ? '<div class="da-heatmap-tabs">' + heatmapTabs.map(function(tab) {
                        return '<button type="button" class="da-heatmap-tab'
                            + (tab.key === selectedHeatmapTab ? ' is-active' : '')
                            + '" data-action="heatmap-tab" data-tabkey="' + escapeHtml(tab.key) + '">'
                            + escapeHtml(tab.label) + '</button>';
                    }).join('') + '</div>' : '')
                    + '<div class="da-heatmap-subtitle">'
                    + escapeHtml(panel.description || text('heatmapAllCombined', 'Click a cell for the employee list.'))
                    + '</div>'
                    + '<div class="da-heatmap-table-wrap"><table class="da-heatmap-table">'
                    + '<thead><tr><th>' + escapeHtml(text('heatmapCorner', 'Personnel category')) + '</th>' + columnLabels.map(function(label) {
                        return '<th scope="col">' + escapeHtml(label) + '</th>';
                    }).join('') + '</tr></thead><tbody>'
                    + rowLabels.map(function(rowLabel) {
                        return '<tr><th scope="row">' + escapeHtml(rowLabel) + '</th>'
                            + columnLabels.map(function(columnLabel) {
                                var cell = ((matrix[rowLabel] || {})[columnLabel]) || null;
                                if (!cell) {
                                    return '<td class="da-heatmap-cell-slot"><span class="da-heatmap-cell da-heatmap-cell-muted">—</span></td>';
                                }
                                var tone = heatmapTone(Number(cell.percent) || 0, (cell.value || '—') !== '—');
                                return '<td class="da-heatmap-cell-slot"><button type="button" class="da-heatmap-cell da-heatmap-cell-'
                                    + escapeHtml(cell.status || 'muted') + '" data-action="heatmap-cell"'
                                    + ' data-drilldown="' + escapeHtml(cell.drilldownkey || 'company_compliance') + '"'
                                    + ' data-personnelcategory="' + escapeHtml(cell.rowlabel || '') + '"'
                                    + ' data-site="' + escapeHtml(cell.columnlabel || '') + '"'
                                    + ' data-companyid="' + escapeHtml(String(cell.companyid || 0)) + '"'
                                    + ' data-companyname="' + escapeHtml(cell.companyname || '') + '"'
                                    + ' style="--da-heatmap-bg:' + escapeHtml(tone.background) + ';--da-heatmap-fg:' + escapeHtml(tone.color) + ';--da-heatmap-ring:' + escapeHtml(tone.ring) + ';"'
                                    + ' title="' + escapeHtml(cell.meta || '') + '">'
                                    + '<span class="da-heatmap-cell-value">' + escapeHtml(cell.value || '—') + '</span>'
                                    + '</button></td>';
                            }).join('') + '</tr>';
                    }).join('')
                    + '</tbody></table></div>'
                    + '<div class="da-heatmap-legend">'
                    + '<span class="da-turnover-legend-item">' + heatmapLegendRamp(['#4cc38a', '#0f8a55']) + escapeHtml(text('heatmapCompliantLegend', '>=80% Compliant')) + '</span>'
                    + '<span class="da-turnover-legend-item">' + heatmapLegendRamp(['#f5a623']) + escapeHtml(text('heatmapRiskLegend', '70–79% At risk')) + '</span>'
                    + '<span class="da-turnover-legend-item">' + heatmapLegendRamp(['#ff8566', '#fa5a3d', '#e13a1e', '#c22412', '#a11a0c', '#7f1207', '#5e0c04']) + escapeHtml(text('heatmapCriticalLegend', '<70% Critical')) + '</span>'
                    + '</div>'
                    + '</div>';
            } else if (panel.type === 'servererrors') {
                body = '<div class="da-server-error-list">' + visibleItems.map(function(item) {
                    return '<div class="da-server-error-row da-server-error-row-' + escapeHtml(item.status) + '">'
                        + '<div class="da-server-error-label">' + escapeHtml(item.label) + '</div>'
                        + '<div class="da-server-error-count da-text-' + escapeHtml(item.status) + '">' + escapeHtml(item.value) + '</div>'
                        + '<div class="da-server-error-meta">' + escapeHtml(item.meta) + '</div>'
                        + '<div class="da-server-error-state da-text-' + escapeHtml(item.status) + '">' + escapeHtml(item.status === 'ok' ? text('clearState', 'Clear') : (item.status === 'danger' ? text('criticalState', 'Critical') : text('monitorState', 'Monitor'))) + '</div>'
                        + '</div>';
                }).join('') + '</div>';
            } else if (panel.type === 'serversettings') {
                var summary = visibleItems.reduce(function(result, item) {
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
                    + '<div class="da-server-settings-table">' + visibleItems.map(function(item) {
                        return '<div class="da-server-settings-row">'
                            + '<div class="da-server-settings-label">' + escapeHtml(item.label) + '</div>'
                            + '<div class="da-server-settings-value">' + escapeHtml(item.value) + '</div>'
                            + '<div class="da-server-settings-status"><span class="da-badge da-badge-' + escapeHtml(item.status) + '">' + escapeHtml(item.meta) + '</span></div>'
                            + '</div>';
                    }).join('') + '</div>'
                    + '</div>';
            } else if (panel.type === 'qualitypassrate') {
                var passMarker = '';
                var passThreshold = Number(panel.threshold);
                if (!isNaN(passThreshold) && passThreshold >= 0 && passThreshold <= 100) {
                    passMarker = '<span class="da-quality-pass-reference" style="left:'
                        + Math.max(0, Math.min(100, passThreshold)).toFixed(1) + '%" title="'
                        + escapeHtml(panel.thresholdlabel || '') + '"></span>';
                }
                if (!items.length) {
                    body = '<div class="da-quality-empty">' + escapeHtml(panel.emptymessage || text('noData', 'No data')) + '</div>'
                        + (panel.footer ? '<div class="da-quality-note"><span class="da-quality-note-line"></span>'
                            + escapeHtml(panel.footer) + '</div>' : '');
                } else {
                    body = '<div class="da-quality-pass-chart">' + visibleItems.map(function(item) {
                    var width = Math.max(0, Math.min(100, Number(item.percent) || 0));
                    var label = item.url
                        ? '<a class="da-quality-course-link" href="' + escapeHtml(item.url) + '">' + escapeHtml(item.label) + '</a>'
                        : '<span>' + escapeHtml(item.label) + '</span>';
                    var tag = item.url ? 'a' : 'span';
                    var href = item.url ? ' href="' + escapeHtml(item.url) + '"' : '';
                    var titleText = String(item.label || '') + ': ' + String(item.value || '');
                    return '<div class="da-quality-pass-row da-quality-pass-row-' + escapeHtml(item.status) + '">'
                        + '<div class="da-quality-pass-label">' + label + '</div>'
                        + '<div class="da-quality-pass-track">' + passMarker
                        + '<' + tag + ' class="da-quality-pass-fill da-bar-fill-' + escapeHtml(item.status) + '"'
                        + href + ' style="width:' + width.toFixed(1) + '%" title="' + escapeHtml(titleText) + '">'
                        + '<span>' + escapeHtml(item.value || '') + '</span></' + tag + '>'
                        + '</div>'
                        + '</div>';
                    }).join('') + '</div>'
                        + (panel.footer ? '<div class="da-quality-note"><span class="da-quality-note-line"></span>'
                            + escapeHtml(panel.footer) + '</div>' : '');
                }
            } else if (panel.type === 'qualityengagementtime') {
                if (!visibleItems.length) {
                    body = '<div class="da-quality-empty">' + escapeHtml(panel.emptymessage || text('noData', 'No data')) + '</div>'
                        + (panel.footer ? '<div class="da-quality-note"><span class="da-quality-note-line"></span>'
                            + escapeHtml(panel.footer) + '</div>' : '');
                } else {
                    body = '<div class="da-quality-engagement-chart">' + visibleItems.map(function(item) {
                    var segments = item.segments || [];
                    var activeSegment = segments[0] || {};
                    var sessionSegment = segments[1] || {};
                    var activeWidth = Number(item.activepercent);
                    if (isNaN(activeWidth)) {
                        activeWidth = Number(activeSegment.percent);
                    }
                    if (isNaN(activeWidth)) {
                        activeWidth = Number(item.percent) || 0;
                    }
                    var sessionWidth = Number(item.sessionpercent);
                    if (isNaN(sessionWidth)) {
                        sessionWidth = Number(sessionSegment.percent);
                    }
                    if (isNaN(sessionWidth)) {
                        sessionWidth = 100;
                    }
                    activeWidth = Math.max(0, Math.min(100, activeWidth));
                    sessionWidth = Math.max(0, Math.min(100, sessionWidth));
                    var activeValue = item.activevalue || activeSegment.value || '';
                    var sessionValue = item.sessionvalue || sessionSegment.value || '';
                    var ratio = Number(item.percent) || 0;
                    var status = item.status || (ratio < 30 ? 'danger' : (ratio <= 60 ? 'warning' : 'ok'));
                    var label = item.url
                        ? '<a class="da-quality-course-link" href="' + escapeHtml(item.url) + '">' + escapeHtml(item.label) + '</a>'
                        : '<span>' + escapeHtml(item.label) + '</span>';
                    return '<div class="da-quality-engagement-row da-quality-engagement-row-' + escapeHtml(status) + '">'
                        + '<div class="da-quality-engagement-label">' + label + '</div>'
                        + '<div class="da-quality-engagement-bars">'
                        + '<div class="da-quality-engagement-track da-quality-engagement-track-active">'
                        + '<span class="da-quality-engagement-fill da-quality-engagement-fill-active" style="width:' + activeWidth.toFixed(1) + '%">'
                        + '<span>' + escapeHtml(activeValue) + '</span></span></div>'
                        + '<div class="da-quality-engagement-track da-quality-engagement-track-session">'
                        + '<span class="da-quality-engagement-fill da-quality-engagement-fill-session" style="width:' + sessionWidth.toFixed(1) + '%">'
                        + '<span>' + escapeHtml(sessionValue) + '</span></span></div>'
                        + '</div>'
                        + '<div class="da-quality-engagement-ratio da-text-' + escapeHtml(status) + '">' + escapeHtml(item.value || '') + '</div>'
                        + '</div>';
                    }).join('') + '</div>'
                        + '<div class="da-quality-engagement-legend">'
                        + '<span><i class="da-quality-legend-active"></i>Active time</span>'
                        + '<span><i class="da-quality-legend-session"></i>Session time</span>'
                        + '<span><strong>%</strong> = engagement ratio</span>'
                        + '</div>'
                        + (panel.footer ? '<div class="da-quality-note"><span class="da-quality-note-line"></span>'
                            + escapeHtml(panel.footer) + '</div>' : '');
                }
            } else if (panel.type === 'qualityratingtable') {
                var info = '<span class="da-quality-info" aria-hidden="true">i</span>';
                var npsStatus = function(value) {
                    var numeric = Number(value);
                    if (isNaN(numeric)) {
                        return 'muted';
                    }
                    if (numeric < 0) {
                        return 'danger';
                    }
                    if (numeric < 30) {
                        return 'warning';
                    }
                    return 'ok';
                };
                var relevanceStatus = function(item) {
                    if (item.relevancestatus) {
                        return item.relevancestatus;
                    }
                    var numeric = Number(item.relevance);
                    if (isNaN(numeric)) {
                        return 'muted';
                    }
                    if (numeric < 60) {
                        return 'danger';
                    }
                    if (numeric < 80) {
                        return 'warning';
                    }
                    return 'ok';
                };
                if (!visibleItems.length) {
                    body = '<div class="da-quality-empty">' + escapeHtml(panel.emptymessage || text('noData', 'No data')) + '</div>';
                } else {
                    body = '<div class="da-quality-rating-wrap"><table class="da-quality-rating-table">'
                    + '<thead><tr>'
                    + '<th>' + escapeHtml(text('qualityCourseHeader', 'Course')) + ' ' + info + '</th>'
                    + '<th>' + escapeHtml(text('qualityRatingHeader', 'Rating')) + ' ' + info + '</th>'
                    + '<th>' + escapeHtml(text('qualityReviewsHeader', 'Reviews')) + ' ' + info + '</th>'
                    + '<th>' + escapeHtml(text('qualityNpsHeader', 'NPS')) + ' ' + info + '</th>'
                    + '<th>' + escapeHtml(text('qualityFeedbackHeader', 'Latest feedback')) + '</th>'
                    + '<th>' + escapeHtml(text('qualityRelevanceHeader', 'Relevance')) + ' ' + info + '</th>'
                    + '</tr></thead><tbody>'
                    + visibleItems.map(function(item) {
                        var ratingStatus = item.status || 'muted';
                        var npsClass = npsStatus(item.nps);
                        var relevanceClass = relevanceStatus(item);
                        var feedback = item.latestfeedback || text('qualityNoFeedback', 'No review text available');
                        var feedbackText = item.latestfeedback ? '"' + feedback + '"' : feedback;
                        var course = item.url
                            ? '<a class="da-quality-course-link" href="' + escapeHtml(item.url) + '">' + escapeHtml(item.label) + '</a>'
                            : '<span>' + escapeHtml(item.label) + '</span>';
                        return '<tr class="da-quality-rating-row da-quality-rating-row-' + escapeHtml(ratingStatus) + '">'
                            + '<td class="da-quality-rating-course">' + course + '</td>'
                            + '<td class="da-quality-rating-score da-text-' + escapeHtml(ratingStatus) + '"><span class="da-quality-star">&#9733;</span> '
                            + '<strong>' + escapeHtml(item.ratinglabel || item.value || '') + '</strong></td>'
                            + '<td class="da-quality-rating-reviews">' + escapeHtml(String(item.reviews || 0)) + '</td>'
                            + '<td><span class="da-quality-nps da-quality-nps-' + escapeHtml(npsClass) + '">'
                            + escapeHtml(item.npslabel || '') + '</span></td>'
                            + '<td class="da-quality-rating-feedback">' + escapeHtml(feedbackText) + '</td>'
                            + '<td class="da-quality-rating-relevance da-text-' + escapeHtml(relevanceClass) + '">'
                            + escapeHtml(item.relevancelabel || '') + '</td>'
                            + '</tr>';
                    }).join('')
                    + '</tbody></table></div>'
                    + (panel.alertmessage ? '<div class="da-quality-alert da-quality-alert-' + escapeHtml(panel.alertstatus || 'warning') + '">'
                        + '<span class="da-quality-alert-icon">&#9888;</span> '
                        + escapeHtml(panel.alertmessage) + '</div>' : '');
                }
            } else if (panel.type === 'qualitybar') {
                var reference = function(value, label, secondary) {
                    var numeric = Number(value);
                    if (isNaN(numeric) || numeric < 0 || numeric > 100) {
                        return '';
                    }
                    var className = secondary ? ' da-quality-reference-secondary' : '';
                    return '<span class="da-quality-reference' + className + '" style="left:'
                        + Math.max(0, Math.min(100, numeric)).toFixed(1) + '%" title="'
                        + escapeHtml(label || '') + '"></span>';
                };
                var markers = reference(panel.threshold, panel.thresholdlabel, false)
                    + reference(panel.secondarythreshold, panel.secondarythresholdlabel, true);
                var legendItems = [];
                if (panel.thresholdlabel) {
                    legendItems.push('<span><i class="da-quality-legend-line"></i>' + escapeHtml(panel.thresholdlabel) + '</span>');
                }
                if (panel.secondarythresholdlabel) {
                    legendItems.push('<span><i class="da-quality-legend-line da-quality-legend-line-secondary"></i>'
                        + escapeHtml(panel.secondarythresholdlabel) + '</span>');
                }
                body = (legendItems.length ? '<div class="da-quality-legend">' + legendItems.join('') + '</div>' : '')
                    + '<div class="da-quality-bars">' + visibleItems.map(function(item) {
                    var width = Math.max(0, Math.min(100, Number(item.percent) || 0));
                    return '<div class="da-quality-row da-quality-row-' + escapeHtml(item.status) + '">'
                        + '<div class="da-quality-label"><span>' + escapeHtml(item.label) + '</span><strong>'
                        + escapeHtml(item.value) + '</strong></div>'
                        + '<div class="da-quality-track"><span class="da-quality-fill da-bar-fill-'
                        + escapeHtml(item.status) + '" style="width:' + width + '%"></span>' + markers + '</div>'
                        + '<div class="da-quality-meta">' + escapeHtml(item.meta || '') + '</div>'
                        + '</div>';
                }).join('') + '</div>';
            } else if (panel.type === 'line') {
                body = '<div class="da-line-chart">' + visibleItems.map(function(item) {
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
                body = '<div class="da-mini-cards">' + visibleItems.map(function(item) {
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
                if (panel.key === 'documentstatus') {
                    var donutTotal = visibleItems.reduce(function(sum, item) {
                        return sum + Math.max(0, Number(item.value) || 0);
                    }, 0);
                    body = '<div class="da-donut-card">'
                        + '<div class="da-donut-card-chart">'
                        + '<canvas class="da-donut-canvas da-donut-canvas-lg" width="250" height="250" data-donut="' + escapeHtml(panel.key) + '"></canvas>'
                        + '<div class="da-donut-center">'
                        + '<strong class="da-donut-center-value" data-donut-total="' + escapeHtml(panel.key) + '">' + escapeHtml(String(Math.round(donutTotal))) + '</strong>'
                        + '<span class="da-donut-center-label">' + escapeHtml((text('total', 'total') + ' checks').toUpperCase()) + '</span>'
                        + '</div>'
                        + '</div>'
                        + '<div class="da-donut-card-list">' + visibleItems.map(function(item) {
                            return '<div class="da-donut-card-row" data-donut-row="' + escapeHtml(panel.key + ':' + item.status) + '" style="--da-donut-color:' + escapeHtml(colorForStatus(item.status)) + '">'
                                + '<div class="da-donut-card-row-head">'
                                + '<span class="da-donut-card-swatch"></span>'
                                + '<span class="da-donut-card-name">' + escapeHtml(item.label) + '</span>'
                                + '<strong class="da-donut-card-count">' + escapeHtml(item.value) + '</strong>'
                                + '<span class="da-donut-card-badge">' + escapeHtml((Number(item.percent) || 0).toFixed(1) + '%') + '</span>'
                                + '</div>'
                                + '<span class="da-donut-card-bar"><i data-width="' + escapeHtml(String(Math.max(0, Number(item.percent) || 0))) + '"></i></span>'
                                + '</div>';
                        }).join('') + '</div>'
                        + '</div>';
                } else {
                    body = '<div class="da-donut-wrap">'
                        + '<canvas class="da-donut-canvas" width="180" height="180" data-donut="' + escapeHtml(panel.key) + '"></canvas>'
                        + '<div class="da-donut-list">' + visibleItems.map(function(item) {
                            return '<div class="da-donut-row">'
                                + '<span class="da-dot da-dot-' + escapeHtml(item.status) + '"></span>'
                                + '<span>' + escapeHtml(item.label) + '</span>'
                                + '<strong>' + escapeHtml(item.value) + '</strong>'
                                + '<em>' + escapeHtml(item.meta) + '</em>'
                                + '</div>';
                        }).join('') + '</div>'
                        + '</div>';
                }
            } else if (panel.type === 'histogram') {
                body = '<div class="da-histogram">' + visibleItems.map(function(item) {
                    var height = Math.max(5, Math.min(100, Number(item.percent) || 0));
                    return '<div class="da-histogram-bar">'
                        + '<strong>' + escapeHtml(item.value) + '</strong>'
                        + '<span class="da-histogram-fill da-bar-fill-' + escapeHtml(item.status) + '" style="height:' + height + '%"></span>'
                        + '<em>' + escapeHtml(item.label) + '</em>'
                        + '</div>';
                }).join('') + '</div>';
            } else if (panel.type === 'grouped') {
                body = panelTabMarkup + '<div class="da-grouped-bars">' + visibleItems.map(function(item) {
                    var segments = item.segments || [];
                    return '<div class="da-grouped-row">'
                        + '<div class="da-grouped-label">' + escapeHtml(item.label) + '</div>'
                        + '<div class="da-grouped-series">' + segments.map(function(segment) {
                            var width = Math.max(0, Math.min(100, Number(segment.percent) || 0));
                            var isGroupedClickable = !!segment.drilldownkey;
                            var groupedTag = isGroupedClickable ? 'button' : 'div';
                            var buttonAttrs = isGroupedClickable ? ' data-action="grouped-drilldown"'
                                + ' data-drilldown="' + escapeHtml(segment.drilldownkey) + '"'
                                + ' data-companyid="' + escapeHtml(String(segment.companyid || 0)) + '"'
                                + ' data-companyname="' + escapeHtml(segment.companyname || '') + '"' : '';
                            var segmentLabel = String(segment.label || '').toLowerCase();
                            var valueLabel = String(segment.value || '0') + (segmentLabel ? ' ' + segmentLabel : '');
                            return '<div class="da-grouped-segment">'
                                + '<' + groupedTag + (isGroupedClickable ? ' type="button"' : '') + ' class="da-grouped-track"' + buttonAttrs + '>'
                                + '<span class="da-grouped-track-visual"><span class="da-grouped-fill da-bar-fill-' + escapeHtml(segment.status) + '" style="width:' + width + '%"></span></span>'
                                + '<span class="da-grouped-track-value da-text-' + escapeHtml(segment.status) + '">' + escapeHtml(valueLabel) + '</span>'
                                + '</' + groupedTag + '>'
                                + '</div>';
                        }).join('') + '</div>'
                        + '</div>';
                }).join('') + '</div>';
            } else if (panel.type === 'stacked') {
                body = '<div class="da-stacked-bars">' + visibleItems.map(function(item) {
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
                var renderedBarItems = visibleItems.slice();
                var barSortMarkup = '';
                if (panel.key === 'riskcourse') {
                    var courseSort = ((((state || {}).currentVisualOverrides) || {}).riskcoursesort || 'asc').toLowerCase();
                    renderedBarItems.sort(function(a, b) {
                        var delta = (Number(a.percent) || 0) - (Number(b.percent) || 0);
                        return courseSort === 'desc' ? (-1 * delta) : delta;
                    });
                    barSortMarkup = '<div class="da-bar-sort-controls">'
                        + '<button type="button" class="da-bar-sort-button' + (courseSort === 'asc' ? ' is-active' : '')
                        + '" data-action="course-compliance-sort" data-sort="asc">' + escapeHtml(text('sortWorstBest', 'Worst to best')) + '</button>'
                        + '<button type="button" class="da-bar-sort-button' + (courseSort === 'desc' ? ' is-active' : '')
                        + '" data-action="course-compliance-sort" data-sort="desc">' + escapeHtml(text('sortBestWorst', 'Best to worst')) + '</button>'
                        + '</div>';
                }
                body = panelTabMarkup + barSortMarkup + '<div class="da-bars">' + renderedBarItems.map(function(item) {
                    var width = Math.max(0, Math.min(100, Number(item.percent) || 0));
                    var isBarClickable = !!item.drilldownkey;
                    var barTag = isBarClickable ? 'button' : 'div';
                    var barAttrs = isBarClickable ? ' data-action="bar-drilldown"'
                        + ' data-drilldown="' + escapeHtml(item.drilldownkey) + '"'
                        + ' data-companyid="' + escapeHtml(String(item.companyid || 0)) + '"'
                        + ' data-companyname="' + escapeHtml(item.companyname || '') + '"'
                        + ' data-courseid="' + escapeHtml(String(item.courseid || 0)) + '"' : '';
                    return '<div class="da-bar-row">'
                        + '<div class="da-bar-label"><span>' + escapeHtml(item.label) + '</span></div>'
                        + '<' + barTag + (isBarClickable ? ' type="button"' : '') + ' class="da-bar-track"' + barAttrs + '><div class="da-bar-fill da-bar-fill-' + escapeHtml(item.status) + '" style="width:' + width + '%"><span class="da-bar-fill-value">' + escapeHtml(item.value) + '</span></div></' + barTag + '>'
                        + '<div class="da-bar-meta">' + escapeHtml(item.meta) + '</div>'
                        + '</div>';
                }).join('') + '</div>';
            }

            var isQualityPrototypePanel = ['qualitypassrate', 'qualityengagementtime', 'qualityratingtable'].indexOf(panel.type) !== -1;
            var qualityPanelActions = '';
            if (isQualityPrototypePanel && (panel.chartlabel || panel.interactivelabel)) {
                qualityPanelActions = '<div class="da-quality-panel-actions">'
                    + (panel.chartlabel ? '<span class="da-quality-chip da-quality-chip-blue">' + escapeHtml(panel.chartlabel) + '</span>' : '')
                    + (panel.interactivelabel ? '<span class="da-quality-chip">' + escapeHtml(panel.interactivelabel) + '</span>' : '')
                    + '<span class="da-quality-caret" aria-hidden="true">&#9662;</span>'
                    + '</div>';
            }

            var panelHelp = panel.formula
                ? '<span class="da-panel-help" title="' + escapeHtml(text('formulaTooltip', 'Formula') + ': ' + panel.formula) + '" aria-label="'
                    + escapeHtml(text('formulaTooltip', 'Formula') + ': ' + panel.formula) + '">i</span>'
                : '';

            return '<article class="da-visual-panel' + (isFullRowVisualPanel(panel) ? ' da-visual-panel-fullrow' : '')
                + (isQualityPrototypePanel ? ' da-quality-prototype-panel' : '') + '" data-panel-key="' + escapeHtml(panel.key) + '">'
                + '<div class="da-visual-panel-headline"><h5>' + escapeHtml(panel.title) + '</h5>' + panelHelp + '</div>'
                + (panel.description ? '<p>' + escapeHtml(panel.description) + '</p>' : '')
                + qualityPanelActions
                + body
                + '</article>';
        }).join('') + '</div>';

        drawDoughnuts(root, panels);
        if (panels.some(function(panel) { return panel.type === 'reportsact'; })) {
            loadReportsActConfig(root, state);
        }
        if (panels.some(function(panel) { return panel.type === 'analyticscourses'; })) {
            loadCourseAnalyticsControl(root, state);
        }
        if (panels.some(function(panel) { return panel.type === 'forecastworkload'; })) {
            loadForecastInlineTable(root, state, 'forecastworkload');
        }
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
            var totalNode = root.querySelector('[data-donut-total="' + panel.key + '"]');
            var chartNode = canvas.closest ? canvas.closest('.da-donut-card-chart') : null;
            var centerNode = chartNode ? chartNode.querySelector('.da-donut-center') : null;
            var centerLabelNode = centerNode ? centerNode.querySelector('.da-donut-center-label') : null;
            var animateBars = function() {
                Array.prototype.slice.call(root.querySelectorAll('[data-donut-row^="' + panel.key + ':"] .da-donut-card-bar i')).forEach(function(bar) {
                    var width = Math.max(0.8, Math.min(100, Number(bar.getAttribute('data-width')) || 0));
                    window.requestAnimationFrame(function() {
                        bar.style.width = width.toFixed(1) + '%';
                    });
                });
            };

            var lineWidth = panel.key === 'documentstatus' ? 34 : 26;
            var radius = panel.key === 'documentstatus' ? 88 : 58;

            if (panel.key === 'documentstatus' && chartNode && centerNode && totalNode && centerLabelNode) {
                var totalRect = totalNode.getBoundingClientRect();
                var labelRect = centerLabelNode.getBoundingClientRect();
                var contentWidth = Math.max(totalRect.width || 0, labelRect.width || 0);
                var contentHeight = (totalRect.height || 0) + (labelRect.height || 0) + 8;
                var innerDiameter = Math.ceil(Math.max(contentWidth + 34, contentHeight + 28, 126));
                lineWidth = Math.max(30, Math.min(38, Math.round(innerDiameter * 0.24)));
                radius = Math.ceil((innerDiameter / 2) + (lineWidth / 2) + 12);

                var canvasSize = Math.ceil((radius * 2) + lineWidth + 18);
                canvas.width = canvasSize;
                canvas.height = canvasSize;
                canvas.style.width = canvasSize + 'px';
                canvas.style.height = canvasSize + 'px';
                chartNode.style.width = canvasSize + 'px';
                chartNode.style.height = canvasSize + 'px';
                chartNode.style.flexBasis = canvasSize + 'px';
            }

            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.lineWidth = lineWidth;
            ctx.lineCap = 'butt';

            var cx = canvas.width / 2;
            var cy = canvas.height / 2;

            if (!total) {
                ctx.beginPath();
                ctx.strokeStyle = '#edf1f6';
                ctx.arc(cx, cy, radius, 0, Math.PI * 2);
                ctx.stroke();
                if (totalNode) {
                    totalNode.textContent = '0';
                }
                return;
            }
            var renderProgress = function(progress) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.beginPath();
                ctx.strokeStyle = '#edf1f6';
                ctx.arc(cx, cy, radius, 0, Math.PI * 2);
                ctx.stroke();

                var remaining = (Math.PI * 2) * progress;
                var start = -Math.PI / 2;
                items.forEach(function(item) {
                    var value = Math.max(0, Number(item.value) || Number(item.percent) || 0);
                    var fullSpan = (value / total) * Math.PI * 2;
                    var drawSpan = Math.max(0, Math.min(fullSpan, remaining));
                    if (drawSpan > 0) {
                        ctx.beginPath();
                        ctx.strokeStyle = colorForStatus(item.status);
                        ctx.arc(cx, cy, radius, start, start + drawSpan);
                        ctx.stroke();
                    }
                    start += fullSpan;
                    remaining -= drawSpan;
                });

                if (totalNode) {
                    totalNode.textContent = String(Math.round(total * progress));
                }
            };

            if (panel.key === 'documentstatus') {
                renderProgress(0);
                animateBars();
                var duration = 900;
                var startTime = null;
                var step = function(timestamp) {
                    if (startTime === null) {
                        startTime = timestamp;
                    }
                    var progress = Math.min(1, (timestamp - startTime) / duration);
                    var eased = 1 - Math.pow(1 - progress, 3);
                    renderProgress(eased);
                    if (progress < 1) {
                        window.requestAnimationFrame(step);
                    }
                };
                window.requestAnimationFrame(step);
                return;
            }

            renderProgress(1);
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

    var edgeAutoScrollTimers = new WeakMap();

    var clearEdgeScrollClasses = function(container) {
        if (!container || !container.classList) {
            return;
        }
        container.classList.remove('da-edge-scroll-left', 'da-edge-scroll-right');
    };

    var stopEdgeAutoScroll = function(container) {
        var timer = edgeAutoScrollTimers.get(container);
        if (timer) {
            window.cancelAnimationFrame(timer);
            edgeAutoScrollTimers.delete(container);
        }
        clearEdgeScrollClasses(container);
    };

    var startEdgeAutoScroll = function(container, velocity) {
        if (!container || !velocity) {
            return;
        }

        stopEdgeAutoScroll(container);

        var step = function() {
            if (!container || !container.isConnected) {
                stopEdgeAutoScroll(container);
                return;
            }

            if (container.scrollWidth <= container.clientWidth + 2) {
                stopEdgeAutoScroll(container);
                return;
            }

            var maxScrollLeft = Math.max(0, container.scrollWidth - container.clientWidth);
            var nextScrollLeft = Math.max(0, Math.min(maxScrollLeft, container.scrollLeft + velocity));

            if (nextScrollLeft === container.scrollLeft) {
                stopEdgeAutoScroll(container);
                return;
            }

            container.scrollLeft = nextScrollLeft;
            edgeAutoScrollTimers.set(container, window.requestAnimationFrame(step));
        };

        edgeAutoScrollTimers.set(container, window.requestAnimationFrame(step));
    };

    var edgeScrollContainerFor = function(target, root) {
        var selectors = ['.da-table-wrap', '.da-forecast-chart-wrap', '.block-dashboardanalytics'];
        for (var i = 0; i < selectors.length; i++) {
            var candidate = target.closest(selectors[i]);
            if (candidate && root.contains(candidate) && candidate.scrollWidth > candidate.clientWidth + 2) {
                return candidate;
            }
        }
        return null;
    };

    var updateEdgeAutoScroll = function(container, clientX) {
        if (!container || container.scrollWidth <= container.clientWidth + 2) {
            stopEdgeAutoScroll(container);
            return;
        }

        var bounds = container.getBoundingClientRect();
        var threshold = Math.max(28, Math.min(64, bounds.width * 0.08));
        var leftDistance = clientX - bounds.left;
        var rightDistance = bounds.right - clientX;
        var velocity = 0;

        clearEdgeScrollClasses(container);

        if (leftDistance >= 0 && leftDistance < threshold && container.scrollLeft > 0) {
            velocity = -1 * Math.max(6, ((threshold - leftDistance) / threshold) * 18);
            container.classList.add('da-edge-scroll-left');
        } else if (rightDistance >= 0 && rightDistance < threshold
                && container.scrollLeft < (container.scrollWidth - container.clientWidth)) {
            velocity = Math.max(6, ((threshold - rightDistance) / threshold) * 18);
            container.classList.add('da-edge-scroll-right');
        }

        if (!velocity) {
            stopEdgeAutoScroll(container);
            return;
        }

        startEdgeAutoScroll(container, velocity);
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

    var fillSelect = function(select, options, selectedValue) {
        if (!select) {
            return;
        }

        select.innerHTML = (options || []).map(function(option) {
            var selected = String(option.value) === String(selectedValue) ? ' selected' : '';
            return '<option value="' + escapeHtml(option.value) + '"' + selected + '>'
                + escapeHtml(option.label) + '</option>';
        }).join('');
    };

    var reportsActRoot = function(root) {
        return root.querySelector('[data-region="reports-act"]');
    };

    var reportsActField = function(root, name) {
        var panel = reportsActRoot(root);
        return panel ? panel.querySelector('[data-act-field="' + name + '"]') : null;
    };

    var readReportsActForm = function(root) {
        var valueOf = function(name) {
            var field = reportsActField(root, name);
            return field ? field.value : '';
        };

        return {
            companyid: Number(valueOf('companyid')) || 0,
            month: Number(valueOf('month')) || 0,
            year: Number(valueOf('year')) || 0,
            actnumber: valueOf('actnumber'),
            contractnumber: valueOf('contractnumber'),
            provider: valueOf('provider')
        };
    };

    var updateReportsActPreview = function(root) {
        var panel = reportsActRoot(root);
        if (!panel) {
            return;
        }

        var form = readReportsActForm(root);
        var companySelect = reportsActField(root, 'companyid');
        var monthSelect = reportsActField(root, 'month');
        var companyLabel = companySelect && companySelect.selectedOptions.length ? companySelect.selectedOptions[0].textContent : '—';
        var monthLabel = monthSelect && monthSelect.selectedOptions.length ? monthSelect.selectedOptions[0].textContent : '—';

        var lmsTotal = 0;
        var actTotal = 0;
        var rows = Array.prototype.slice.call(panel.querySelectorAll('[data-act-row]'));

        rows.forEach(function(row) {
            lmsTotal += Number(row.getAttribute('data-lms-count')) || 0;
            var qty = row.querySelector('[data-act-qty]');
            actTotal += Number(qty ? qty.value : 0) || 0;
        });

        var diff = actTotal - lmsTotal;
        var diffNode = panel.querySelector('[data-region="reports-act-difference"]');
        var lmsTotalNode = panel.querySelector('[data-region="reports-act-lms-total"]');
        var actTotalNode = panel.querySelector('[data-region="reports-act-act-total"]');

        if (lmsTotalNode) {
            lmsTotalNode.textContent = String(lmsTotal);
        }
        if (actTotalNode) {
            actTotalNode.textContent = String(actTotal);
        }
        if (diffNode) {
            diffNode.textContent = 'Difference: ' + (diff > 0 ? '+' : '') + diff;
            diffNode.classList.toggle('da-reports-act-diff-ok', diff === 0);
            diffNode.classList.toggle('da-reports-act-diff-warning', diff !== 0);
        }

        var previewRows = panel.querySelector('[data-region="reports-act-preview-rows"]');
        if (previewRows) {
            previewRows.innerHTML = rows.map(function(row) {
                var name = row.getAttribute('data-course-name') || '';
                var unit = row.querySelector('[data-act-unit]');
                var qty = row.querySelector('[data-act-qty]');
                return '<li><span>' + escapeHtml(name) + '</span><em>'
                    + escapeHtml(unit ? unit.value : '') + '</em><strong>'
                    + escapeHtml(qty ? qty.value : '0') + '</strong></li>';
            }).join('');
        }

        var setText = function(region, value) {
            var node = panel.querySelector('[data-region="' + region + '"]');
            if (node) {
                node.textContent = value;
            }
        };

        setText('reports-act-preview-total', String(actTotal));
        setText('reports-act-preview-types', String(rows.length));
        setText('reports-act-preview-diff', (diff >= 0 ? '+' : '') + diff);
        setText('reports-act-preview-number', form.actnumber || '—');
        setText('reports-act-preview-period', monthLabel + ' ' + (form.year || '—'));
        setText('reports-act-preview-client', companyLabel || '—');
        setText('reports-act-preview-provider', form.provider || '—');
        setText('reports-act-preview-contract', form.contractnumber || '—');
        setText('reports-act-preview-total-bottom', String(actTotal));
    };

    var renderReportsActRows = function(root, rows) {
        var panel = reportsActRoot(root);
        if (!panel) {
            return;
        }

        var tbody = panel.querySelector('[data-region="reports-act-rows"]');
        if (!tbody) {
            return;
        }

        if (!rows || !rows.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="da-muted-cell">No visible enrolled courses found for this company and period.</td></tr>';
            updateReportsActPreview(root);
            return;
        }

        tbody.innerHTML = rows.map(function(row) {
            return '<tr data-act-row data-courseid="' + escapeHtml(row.courseid) + '" data-lms-count="'
                + escapeHtml(row.lmscount) + '" data-course-name="' + escapeHtml(row.coursename) + '">'
                + '<td>' + escapeHtml(row.number) + '</td>'
                + '<td>' + escapeHtml(row.coursename) + '</td>'
                + '<td><input type="text" class="da-reports-act-unit" data-act-unit value="' + escapeHtml(row.unit) + '"></td>'
                + '<td><span class="da-reports-lms-count">' + escapeHtml(row.lmscount) + '</span></td>'
                + '<td><input type="number" min="0" step="1" class="da-reports-act-qty" data-act-qty value="' + escapeHtml(row.actqty) + '"></td>'
                + '</tr>';
        }).join('');

        updateReportsActPreview(root);
    };

    var loadReportsActConfig = function(root, state) {
        var panel = reportsActRoot(root);
        if (!panel || panel.getAttribute('data-config-loaded') === '1') {
            return;
        }

        panel.setAttribute('data-config-loaded', '1');

        call('block_dashboardanalytics_get_act_config', {
            contextid: state.contextid
        }).then(function(response) {
            fillSelect(reportsActField(root, 'companyid'), response.companies || [], '');
            fillSelect(reportsActField(root, 'month'), response.months || [], response.defaultmonth);
            fillSelect(reportsActField(root, 'year'), response.years || [], response.defaultyear);

            var provider = reportsActField(root, 'provider');
            if (provider) {
                provider.value = response.defaultprovider || 'TOO "SENTAL"';
            }

            updateReportsActPreview(root);
        }).catch(Notification.exception);
    };

    var loadReportsActServices = function(root, state) {
        var panel = reportsActRoot(root);
        if (!panel) {
            return;
        }

        var form = readReportsActForm(root);
        var status = panel.querySelector('[data-region="reports-act-status"]');

        if (!form.companyid || !form.month || !form.year) {
            if (status) {
                status.hidden = false;
                status.textContent = 'Select client company, month and year first.';
                status.className = 'da-reports-act-status da-reports-act-status-warning';
            }
            return;
        }

        if (status) {
            status.hidden = false;
            status.textContent = 'Loading LMS data...';
            status.className = 'da-reports-act-status';
        }

        call('block_dashboardanalytics_load_act_services', {
            contextid: state.contextid,
            companyid: form.companyid,
            month: form.month,
            year: form.year
        }).then(function(response) {
            if (status) {
                status.hidden = false;
                status.textContent = 'LMS data loaded · ' + response.companyname;
                status.className = 'da-reports-act-status da-reports-act-status-ok';
            }

            /*
            * Idempotent behaviour:
            * This replaces the tbody every time. It does not append.
            */
            renderReportsActRows(root, response.rows || []);
        }).catch(Notification.exception);
    };

    var resetReportsActToLms = function(root) {
        var panel = reportsActRoot(root);
        if (!panel) {
            return;
        }

        Array.prototype.slice.call(panel.querySelectorAll('[data-act-row]')).forEach(function(row) {
            var qty = row.querySelector('[data-act-qty]');
            if (qty) {
                qty.value = row.getAttribute('data-lms-count') || '0';
            }
        });

        updateReportsActPreview(root);
    };

    var clearReportsAct = function(root) {
        var panel = reportsActRoot(root);
        if (!panel) {
            return;
        }

        ['actnumber', 'contractnumber'].forEach(function(name) {
            var field = reportsActField(root, name);
            if (field) {
                field.value = name === 'actnumber' ? '1' : '';
            }
        });

        var tbody = panel.querySelector('[data-region="reports-act-rows"]');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="5" class="da-muted-cell">Select company and period, then click Load from LMS.</td></tr>';
        }

        updateReportsActPreview(root);
    };    

    var loadFilters = function(root, state, requestFilters) {
        var container = root.querySelector('[data-region="filter-bar"]');
        setLoading(container);
        var payload = requestFilters;
        if (!payload) {
            payload = Object.keys(state.filterGroups || {}).length
                ? readFilters(root, state)
                : Object.assign({}, state.persistedFilters || {});
        }

        state.persistedFilters = Object.assign({}, payload);

        return call('block_dashboardanalytics_get_filter_options', {
            contextid: state.contextid,
            filters: JSON.stringify(payload)
        }).then(function(response) {
            renderFilters(root, state, response.groups || []);
            persistState(root, state);
        }).catch(Notification.exception);
    };

    var syncSearchableFilter = function(input) {
        if (!input) {
            return false;
        }

        var wrap = input.closest('[data-filter-wrap]');
        if (!wrap) {
            return false;
        }

        var hidden = wrap.querySelector('input[type="hidden"][data-filter-group]');
        var listId = input.getAttribute('list');
        var list = listId ? document.getElementById(listId) : null;
        if (!hidden || !list) {
            return false;
        }

        var previous = hidden.value || '';
        var entered = (input.value || '').trim().toLowerCase();
        var matched = '';

        if (entered !== '') {
            Array.prototype.slice.call(list.querySelectorAll('option')).some(function(option) {
                if ((option.value || '').trim().toLowerCase() === entered) {
                    matched = option.getAttribute('data-value') || '';
                    return true;
                }
                return false;
            });
        }

        hidden.value = matched;
        if (matched === '') {
            input.value = entered === '' ? '' : input.value;
        }

        return previous !== hidden.value;
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

    var loadDrilldown = function(root, state, drilldownkey, filterOverrides, page, perpage, historyMode) {
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
            commitBrowserHistoryState(root, state, historyMode || 'push');
        }).catch(Notification.exception);
    };

    var loadVisuals = function(root, state, tabkey, overrides, historyMode) {
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
            state.currentVisualResponse = response;
            setActiveTab(root, tabkey);
            renderVisuals(root, response, state);
            persistState(root, state);
            commitBrowserHistoryState(root, state, historyMode || 'push');
        }).catch(Notification.exception);
    };

    var refresh = function(root, state, historyMode) {
        var payload = Object.keys(state.filterGroups || {}).length
            ? readFilters(root, state)
            : Object.assign({}, state.persistedFilters || {});

        return loadFilters(root, state, payload).then(function() {
            persistState(root, state);
            loadKpis(root, state);
            if (state.currentTab === 'kpis') {
                loadDrilldown(root, state, state.currentDrilldown || defaultDrilldownKey(state), undefined, undefined, undefined, historyMode || 'push');
                return;
            }
            if (state.currentDrilldown) {
                loadDrilldown(root, state, state.currentDrilldown, undefined, undefined, undefined, historyMode || 'push');
                return;
            }
            loadVisuals(root, state, state.currentTab || 'overview', undefined, historyMode || 'push');
        });
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
        var hideComplianceHover = function(overlay) {
            if (!overlay) {
                return;
            }

            var crosshair = overlay.querySelector('[data-region="compliance-crosshair"]');
            var tooltip = overlay.querySelector('[data-region="compliance-tooltip"]');

            if (crosshair) {
                crosshair.classList.remove('is-visible');
                window.setTimeout(function() {
                    crosshair.hidden = true;
                }, 140);
            }

            if (tooltip) {
                tooltip.classList.remove('is-visible');
                window.setTimeout(function() {
                    tooltip.hidden = true;
                }, 140);
            }
        };
        var hideForecastTooltip = function(rootNode) {
            var tooltip = rootNode ? rootNode.querySelector('[data-region="forecast-tooltip"]') : null;
            if (!tooltip) {
                return;
            }
            tooltip.hidden = true;
            tooltip.classList.remove('is-visible');
            var courseNode = tooltip.querySelector('.da-forecast-tooltip-course span');
            var metaNode = tooltip.querySelector('.da-forecast-tooltip-meta');
            if (courseNode) {
                courseNode.textContent = '';
            }
            if (metaNode) {
                metaNode.textContent = '';
            }
        };
        var showComplianceHover = function(target) {
            if (!target) {
                return;
            }

            var overlay = target.closest('.da-compliance-trendline-overlay');
            if (!overlay) {
                return;
            }

            var crosshair = overlay.querySelector('[data-region="compliance-crosshair"]');
            var tooltip = overlay.querySelector('[data-region="compliance-tooltip"]');
            if (!crosshair || !tooltip) {
                return;
            }

            var left = target.style.left || '0%';
            var monthLabel = target.getAttribute('data-label') || '';
            var complianceValue = target.getAttribute('data-value') || '';

            crosshair.style.left = left;
            crosshair.hidden = false;
            crosshair.classList.add('is-visible');

            tooltip.innerHTML = '<strong>' + escapeHtml('Month ' + monthLabel) + '</strong>'
                + '<span>' + escapeHtml('Compliance: ' + complianceValue) + '</span>';
            tooltip.hidden = false;
            tooltip.style.left = left;
            window.requestAnimationFrame(function() {
                tooltip.classList.add('is-visible');
            });

            var buttonWidth = target.offsetWidth || 32;
            var overlayWidth = overlay.offsetWidth || 0;
            var hoverTargetLeft = target.offsetLeft + (buttonWidth / 2);
            var tooltipWidth = tooltip.offsetWidth || 0;
            var minLeft = tooltipWidth / 2;
            var maxLeft = Math.max(minLeft, overlayWidth - (tooltipWidth / 2));
            var clampedLeft = Math.max(minLeft, Math.min(maxLeft, hoverTargetLeft));

            tooltip.style.left = clampedLeft + 'px';
        };
        var showForecastTooltip = function(target, event) {
            if (!target) {
                return;
            }

            var rootNode = target.closest('[data-region="forecast-workload"]');
            var tooltip = rootNode ? rootNode.querySelector('[data-region="forecast-tooltip"]') : null;
            var chartArea = rootNode ? rootNode.querySelector('.da-forecast-chart-area') : null;
            if (!tooltip || !chartArea) {
                return;
            }

            var courseNode = tooltip.querySelector('.da-forecast-tooltip-course span');
            var swatchNode = tooltip.querySelector('.da-forecast-tooltip-course i');
            var metaNode = tooltip.querySelector('.da-forecast-tooltip-meta');
            var courseLabel = target.getAttribute('data-tooltip-course')
                || target.getAttribute('data-label')
                || '';
            var countLabel = target.getAttribute('data-tooltip-count')
                || formatString(text('forecastUsersLabel', '{$a} users'), String(target.getAttribute('data-value') || '0'));
            var windowLabel = target.getAttribute('data-tooltip-window')
                || ((target.closest('.da-forecast-bar-group') || {}).querySelector
                    ? (((target.closest('.da-forecast-bar-group') || {}).querySelector('.da-forecast-bar-label') || {}).textContent || '')
                    : '');
            if (courseNode) {
                courseNode.textContent = courseLabel;
            }
            if (swatchNode) {
                swatchNode.style.background = target.getAttribute('data-colour') || '#3b82f6';
            }
            if (metaNode) {
                metaNode.textContent = countLabel + (windowLabel ? ' · ' + windowLabel : '');
            }
            tooltip.hidden = false;
            tooltip.classList.add('is-visible');

            var bounds = chartArea.getBoundingClientRect();
            var left = (event.clientX || bounds.left) - bounds.left;
            var top = (event.clientY || bounds.top) - bounds.top;

            tooltip.style.left = Math.max(12, Math.min(bounds.width - 12, left)) + 'px';
            tooltip.style.top = Math.max(12, Math.min(bounds.height - 12, top - 16)) + 'px';
        };

        root.addEventListener('change', function(event) {
            if (event.target.matches('select[data-filter-group]')) {
                rememberCurrentState(root, state);
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

            if (event.target.matches('[data-filter-search]')) {
                rememberCurrentState(root, state);
                state.currentDrilldownPage = 0;
                syncSearchableFilter(event.target);
                updateFilterCounts(root, state);
                refresh(root, state);
                return;
            }

            if (event.target.matches('[data-action="compliance-threshold"]')) {
                rememberCurrentState(root, state);
                state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {});
                state.currentVisualOverrides[event.target.getAttribute('data-threshold-key') || ''] = Number(event.target.value) || 0;
                if (state.currentVisualResponse) {
                    renderVisuals(root, state.currentVisualResponse, state);
                    persistState(root, state);
                    commitBrowserHistoryState(root, state, 'push');
                }
                return;
            }

            if (event.target.matches('[data-filter-custom]')) {
                rememberCurrentState(root, state);
                state.currentDrilldownPage = 0;
                refresh(root, state);
                return;
            }

            if (event.target.matches('[data-action="drilldown-perpage"]')) {
                rememberCurrentState(root, state);
                state.currentDrilldownPage = 0;
                state.currentDrilldownPerPage = Number(event.target.value) || 20;
                if (state.currentDrilldown) {
                    loadDrilldown(root, state, state.currentDrilldown, undefined, 0, state.currentDrilldownPerPage);
                }
                return;
            }

            if (event.target.matches('[data-action="course-analytics-perpage"]')) {
                rememberCurrentState(root, state);
                state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, {
                    courseanalytics_perpage: Number(event.target.value) || 20,
                    courseanalytics_page: 0
                });
                loadCourseAnalyticsControl(root, state);
                return;
            }

            if (event.target.matches('[data-action="forecast-table-perpage"]')) {
                rememberCurrentState(root, state);
                state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, {
                    forecastperpage_forecastworkload: Number(event.target.value) || 20,
                    forecastpage_forecastworkload: 0
                });
                loadForecastInlineTable(root, state, 'forecastworkload');
                persistState(root, state);
                commitBrowserHistoryState(root, state, 'push');
                return;
            }

            if (event.target.matches('[data-act-field]')) {
                updateReportsActPreview(root);
                return;
            }
        });

        root.addEventListener('input', function(event) {
            if (event.target.matches('[data-filter-search]')) {
                var changed = syncSearchableFilter(event.target);
                updateFilterCounts(root, state);
                if (event.target.value === '' || changed) {
                    window.clearTimeout(timer);
                    timer = window.setTimeout(function() {
                        rememberCurrentState(root, state);
                        state.currentDrilldownPage = 0;
                        refresh(root, state);
                    }, 250);
                }
                return;
            }
            if (event.target.matches('[data-act-field], [data-act-unit], [data-act-qty]')) {
                updateReportsActPreview(root);
                return;
            }
            if (event.target.matches('[data-action="course-analytics-search"]')) {
                window.clearTimeout(timer);
                timer = window.setTimeout(function() {
                    state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, {
                        courseanalytics_search: event.target.value || '',
                        courseanalytics_page: 0
                    });
                    loadCourseAnalyticsControl(root, state);
                }, 250);
                return;
            }
            if (!event.target.matches('[data-filter="search"]')) {
                return;
            }
            window.clearTimeout(timer);
            timer = window.setTimeout(function() {
                rememberCurrentState(root, state);
                refresh(root, state);
            }, 350);
        });

        root.addEventListener('mouseover', function(event) {
            var complianceHover = event.target.closest('[data-action="compliance-hover"]');
            if (complianceHover && root.contains(complianceHover)) {
                showComplianceHover(complianceHover);
                return;
            }

            var forecastSegmentHover = event.target.closest('[data-action="forecast-segment"]');
            if (forecastSegmentHover && root.contains(forecastSegmentHover)) {
                showForecastTooltip(forecastSegmentHover, event);
            }
        });

        root.addEventListener('focusin', function(event) {
            var complianceHover = event.target.closest('[data-action="compliance-hover"]');
            if (complianceHover && root.contains(complianceHover)) {
                showComplianceHover(complianceHover);
                return;
            }

            var forecastSegmentHover = event.target.closest('[data-action="forecast-segment"]');
            if (forecastSegmentHover && root.contains(forecastSegmentHover)) {
                showForecastTooltip(forecastSegmentHover, {
                    clientX: forecastSegmentHover.getBoundingClientRect().left,
                    clientY: forecastSegmentHover.getBoundingClientRect().top
                });
            }
        });

        root.addEventListener('mouseout', function(event) {
            var complianceHover = event.target.closest('[data-action="compliance-hover"]');
            if (complianceHover && root.contains(complianceHover)) {
                var related = event.relatedTarget;
                if (related && complianceHover.contains(related)) {
                    return;
                }

                hideComplianceHover(complianceHover.closest('.da-compliance-trendline-overlay'));
                return;
            }

            var forecastSegmentHover = event.target.closest('[data-action="forecast-segment"]');
            if (forecastSegmentHover && root.contains(forecastSegmentHover)) {
                hideForecastTooltip(forecastSegmentHover.closest('[data-region="forecast-workload"]'));
            }
        });

        root.addEventListener('focusout', function(event) {
            var complianceHover = event.target.closest('[data-action="compliance-hover"]');
            if (complianceHover && root.contains(complianceHover)) {
                hideComplianceHover(complianceHover.closest('.da-compliance-trendline-overlay'));
                return;
            }

            var forecastSegmentHover = event.target.closest('[data-action="forecast-segment"]');
            if (forecastSegmentHover && root.contains(forecastSegmentHover)) {
                hideForecastTooltip(forecastSegmentHover.closest('[data-region="forecast-workload"]'));
            }
        });

        root.addEventListener('mousemove', function(event) {
            var edgeScrollTarget = edgeScrollContainerFor(event.target, root);
            if (edgeScrollTarget) {
                updateEdgeAutoScroll(edgeScrollTarget, event.clientX || 0);
            } else {
                Array.prototype.slice.call(root.querySelectorAll('.da-table-wrap, .da-forecast-chart-wrap, .block-dashboardanalytics')).forEach(function(container) {
                    stopEdgeAutoScroll(container);
                });
            }

            var forecastSegmentHover = event.target.closest('[data-action="forecast-segment"]');
            if (forecastSegmentHover && root.contains(forecastSegmentHover)) {
                showForecastTooltip(forecastSegmentHover, event);
            }
        });

        root.addEventListener('mouseleave', function(event) {
            var edgeScrollTarget = edgeScrollContainerFor(event.target, root);
            if (edgeScrollTarget) {
                stopEdgeAutoScroll(edgeScrollTarget);
            }
        }, true);

        root.addEventListener('click', function(event) {
            var reportsActLoad = event.target.closest('[data-action="reports-act-load"]');
            if (reportsActLoad && root.contains(reportsActLoad)) {
                rememberCurrentState(root, state);
                loadReportsActServices(root, state);
                return;
            }

            var reportsActReset = event.target.closest('[data-action="reports-act-reset"]');
            if (reportsActReset && root.contains(reportsActReset)) {
                rememberCurrentState(root, state);
                resetReportsActToLms(root);
                return;
            }

            var reportsActClear = event.target.closest('[data-action="reports-act-clear"]');
            if (reportsActClear && root.contains(reportsActClear)) {
                rememberCurrentState(root, state);
                clearReportsAct(root);
                return;
            }

            var reportsActDownload = event.target.closest('[data-action="reports-act-download"]');
            if (reportsActDownload && root.contains(reportsActDownload)) {
                var panel = reportsActRoot(root);
                var diffNode = panel ? panel.querySelector('[data-region="reports-act-difference"]') : null;
                var hasWarning = diffNode && diffNode.classList.contains('da-reports-act-diff-warning');

                if (hasWarning && !window.confirm('Act Qty differs from LMS Count. Continue download?')) {
                    return;
                }

                Notification.addNotification({
                    message: 'Excel download endpoint is the next backend step.',
                    type: 'info'
                });

                return;
            }
            var addFilterToggle = event.target.closest('[data-action="toggle-add-filter"]');
            if (addFilterToggle && root.contains(addFilterToggle)) {
                toggleAddFilterMenu(root);
                return;
            }

            var compliancePeriod = event.target.closest('[data-action="compliance-period"]');
            if (compliancePeriod && root.contains(compliancePeriod)) {
                rememberCurrentState(root, state);
                state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, {
                    compliancetrendperiod: Number(compliancePeriod.getAttribute('data-period')) || 12
                });
                if (state.currentVisualResponse) {
                    renderVisuals(root, state.currentVisualResponse, state);
                    persistState(root, state);
                }
                return;
            }

            var courseComplianceSort = event.target.closest('[data-action="course-compliance-sort"]');
            if (courseComplianceSort && root.contains(courseComplianceSort)) {
                rememberCurrentState(root, state);
                state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, {
                    riskcoursesort: (courseComplianceSort.getAttribute('data-sort') || 'asc').toLowerCase()
                });
                if (state.currentVisualResponse) {
                    renderVisuals(root, state.currentVisualResponse, state);
                    persistState(root, state);
                    commitBrowserHistoryState(root, state, 'push');
                }
                return;
            }

            var courseAnalyticsToggle = event.target.closest('[data-action="course-analytics-toggle"]');
            if (courseAnalyticsToggle && root.contains(courseAnalyticsToggle)) {
                if (courseAnalyticsToggle.disabled) {
                    return;
                }

                var nextEnabled = courseAnalyticsToggle.getAttribute('data-enabled') !== '1';
                courseAnalyticsToggle.disabled = true;
                call('block_dashboardanalytics_set_course_analytics_control', {
                    contextid: state.contextid,
                    courseid: Number(courseAnalyticsToggle.getAttribute('data-courseid')) || 0,
                    enabled: nextEnabled
                }).then(function() {
                    updateCourseAnalyticsToggleUi(courseAnalyticsToggle, nextEnabled);
                    Notification.addNotification({
                        message: text('courseAnalyticsSaved', 'Course analytics setting updated.'),
                        type: 'success'
                    });
                }).catch(function(error) {
                    Notification.exception(error);
                }).finally(function() {
                    courseAnalyticsToggle.disabled = false;
                });
                return;
            }

            var courseAnalyticsPage = event.target.closest('[data-action="course-analytics-page"]');
            if (courseAnalyticsPage && root.contains(courseAnalyticsPage) && !courseAnalyticsPage.disabled) {
                rememberCurrentState(root, state);
                state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, {
                    courseanalytics_page: Number(courseAnalyticsPage.getAttribute('data-page')) || 0
                });
                loadCourseAnalyticsControl(root, state);
                return;
            }

            var addFilter = event.target.closest('[data-action="add-filter"]');
            if (addFilter && root.contains(addFilter)) {
                rememberCurrentState(root, state);
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
                rememberCurrentState(root, state);
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
                rememberCurrentState(root, state);
                Array.prototype.slice.call(root.querySelectorAll('[data-filter-group]')).forEach(function(field) {
                    if (field.tagName === 'SELECT') {
                        field.value = field.getAttribute('data-filter-group') === 'daterange' ? defaultDateRange(state) : '';
                        return;
                    }

                    if (field.type === 'hidden') {
                        field.value = '';
                        var wrap = field.closest('[data-filter-wrap]');
                        var visible = wrap ? wrap.querySelector('[data-filter-search]') : null;
                        if (visible) {
                            visible.value = '';
                        }
                    }
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
                rememberCurrentState(root, state);
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

            var matrixToggle = event.target.closest('[data-action="matrix-toggle"]');
            if (matrixToggle && root.contains(matrixToggle)) {
                var matrixGroupId = matrixToggle.getAttribute('data-group-id') || '';
                var expanded = matrixToggle.getAttribute('aria-expanded') === 'true';
                matrixToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                matrixToggle.classList.toggle('is-expanded', !expanded);
                var icon = matrixToggle.querySelector('.da-matrix-toggle-icon');
                if (icon) {
                    icon.textContent = expanded ? '▾' : '▴';
                }

                Array.prototype.slice.call(root.querySelectorAll('.da-matrix-course-row[data-group-id="' + matrixGroupId + '"]')).forEach(function(row) {
                    row.classList.toggle('is-collapsed', expanded);
                });
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
                rememberCurrentState(root, state);
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
                rememberCurrentState(root, state);
                state.currentDrilldown = '';
                state.currentDrilldownOverrides = null;
                state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, {
                    platformgrowthperiod: platformGrowthPeriod.getAttribute('data-period') || '1year'
                });
                loadVisuals(root, state, state.currentTab || 'overview', state.currentVisualOverrides);
                return;
            }

            var forecastPeriod = event.target.closest('[data-action="forecast-period"]');
            if (forecastPeriod && root.contains(forecastPeriod)) {
                rememberCurrentState(root, state);
                var forecastPanelKey = forecastPeriod.getAttribute('data-panel') || 'forecastworkload';
                state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, {
                    ['forecastperiod_' + forecastPanelKey]: forecastPeriod.getAttribute('data-period') || '30days',
                    ['forecastselection_' + forecastPanelKey]: null,
                    ['forecastpage_' + forecastPanelKey]: 0
                });
                loadVisuals(root, state, state.currentTab || 'forecast', state.currentVisualOverrides);
                return;
            }

            var forecastSegment = event.target.closest('[data-action="forecast-segment"]');
            if (forecastSegment && root.contains(forecastSegment)) {
                rememberCurrentState(root, state);
                var forecastSegmentPanelKey = forecastSegment.getAttribute('data-panel') || 'forecastworkload';
                state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, {
                    ['forecastselection_' + forecastSegmentPanelKey]: {
                        tabkey: forecastSegment.getAttribute('data-tabkey') || '',
                        periodkey: forecastSegment.getAttribute('data-period') || '',
                        fromts: Number(forecastSegment.getAttribute('data-fromts')) || 0,
                        tots: Number(forecastSegment.getAttribute('data-tots')) || 0,
                        courseid: Number(forecastSegment.getAttribute('data-courseid')) || 0,
                        course: forecastSegment.getAttribute('data-label') || '',
                        label: forecastSegment.closest('.da-forecast-bar-group')
                            ? (forecastSegment.closest('.da-forecast-bar-group').querySelector('.da-forecast-bar-label') || {}).textContent || ''
                            : ''
                    },
                    ['forecastpage_' + forecastSegmentPanelKey]: 0
                });
                loadVisuals(root, state, state.currentTab || 'forecast', state.currentVisualOverrides);
                return;
            }

            var forecastSummaryCourse = event.target.closest('[data-action="forecast-summary-course"]');
            if (forecastSummaryCourse && root.contains(forecastSummaryCourse)) {
                rememberCurrentState(root, state);
                var forecastSummaryPanelKey = forecastSummaryCourse.getAttribute('data-panel') || 'forecastworkload';
                var currentSelection = (((state.currentVisualOverrides || {})['forecastselection_' + forecastSummaryPanelKey]) || {});
                var nextCourseId = Number(forecastSummaryCourse.getAttribute('data-courseid')) || 0;
                if (Number(currentSelection.courseid || 0) === nextCourseId) {
                    nextCourseId = 0;
                }
                state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, {
                    ['forecastselection_' + forecastSummaryPanelKey]: {
                        tabkey: forecastSummaryCourse.getAttribute('data-tabkey') || '',
                        periodkey: forecastSummaryCourse.getAttribute('data-period') || '',
                        fromts: Number(forecastSummaryCourse.getAttribute('data-fromts')) || 0,
                        tots: Number(forecastSummaryCourse.getAttribute('data-tots')) || 0,
                        courseid: nextCourseId,
                        course: nextCourseId ? (forecastSummaryCourse.getAttribute('data-label') || '') : '',
                        label: forecastSummaryCourse.getAttribute('data-barlabel') || ''
                    },
                    ['forecastpage_' + forecastSummaryPanelKey]: 0
                });
                loadVisuals(root, state, state.currentTab || 'forecast', state.currentVisualOverrides);
                return;
            }

            var forecastBar = event.target.closest('[data-action="forecast-bar"]');
            if (forecastBar && root.contains(forecastBar)) {
                rememberCurrentState(root, state);
                var forecastBarPanelKey = forecastBar.getAttribute('data-panel') || 'forecastworkload';
                state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, {
                    ['forecastselection_' + forecastBarPanelKey]: {
                        tabkey: forecastBar.getAttribute('data-tabkey') || '',
                        periodkey: forecastBar.getAttribute('data-period') || '',
                        fromts: Number(forecastBar.getAttribute('data-fromts')) || 0,
                        tots: Number(forecastBar.getAttribute('data-tots')) || 0,
                        courseid: 0,
                        course: '',
                        label: forecastBar.textContent || ''
                    },
                    ['forecastpage_' + forecastBarPanelKey]: 0
                });
                loadVisuals(root, state, state.currentTab || 'forecast', state.currentVisualOverrides);
                return;
            }

            var forecastClearCourse = event.target.closest('[data-action="forecast-clear-course"]');
            if (forecastClearCourse && root.contains(forecastClearCourse)) {
                rememberCurrentState(root, state);
                var forecastClearPanelKey = forecastClearCourse.getAttribute('data-panel') || 'forecastworkload';
                var clearSelection = (((state.currentVisualOverrides || {})['forecastselection_' + forecastClearPanelKey]) || {});
                state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, {
                    ['forecastselection_' + forecastClearPanelKey]: Object.assign({}, clearSelection, {
                        courseid: 0,
                        course: ''
                    }),
                    ['forecastpage_' + forecastClearPanelKey]: 0
                });
                loadVisuals(root, state, state.currentTab || 'forecast', state.currentVisualOverrides);
                return;
            }

            var forecastTablePage = event.target.closest('[data-action="forecast-table-page"]');
            if (forecastTablePage && root.contains(forecastTablePage) && !forecastTablePage.disabled) {
                rememberCurrentState(root, state);
                state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, {
                    forecastpage_forecastworkload: Number(forecastTablePage.getAttribute('data-page')) || 0
                });
                loadForecastInlineTable(root, state, 'forecastworkload');
                persistState(root, state);
                commitBrowserHistoryState(root, state, 'push');
                return;
            }

            var panelTab = event.target.closest('[data-action="panel-tab"]');
            if (panelTab && root.contains(panelTab)) {
                rememberCurrentState(root, state);
                var panelKey = panelTab.getAttribute('data-panel') || '';
                var tabKey = panelTab.getAttribute('data-tabkey') || '';
                if (panelKey && tabKey) {
                    var overrideKey = 'paneltab_' + panelKey;
                    state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {});
                    state.currentVisualOverrides[overrideKey] = tabKey;
                    loadVisuals(root, state, state.currentTab || 'compliance', state.currentVisualOverrides);
                }
                return;
            }

            var heatmapTab = event.target.closest('[data-action="heatmap-tab"]');
            if (heatmapTab && root.contains(heatmapTab)) {
                rememberCurrentState(root, state);
                state.currentVisualOverrides = Object.assign({}, state.currentVisualOverrides || {}, {
                    heatmapcompany: heatmapTab.getAttribute('data-tabkey') || 'all'
                });
                loadVisuals(root, state, state.currentTab || 'compliance', state.currentVisualOverrides);
                return;
            }

            var heatmapCell = event.target.closest('[data-action="heatmap-cell"]');
            if (heatmapCell && root.contains(heatmapCell)) {
                rememberCurrentState(root, state);
                var heatmapOverrides = {
                    personnelcategories: [heatmapCell.getAttribute('data-personnelcategory') || ''],
                    sites: [heatmapCell.getAttribute('data-site') || '']
                };
                var heatmapCompanyId = heatmapCell.getAttribute('data-companyid') || '0';
                var heatmapCompanyName = heatmapCell.getAttribute('data-companyname') || '';
                if (heatmapCompanyId !== '0') {
                    heatmapOverrides.companyids = [heatmapCompanyId];
                } else if (heatmapCompanyName !== '') {
                    heatmapOverrides.companies = [heatmapCompanyName];
                }
                state.currentDrilldown = heatmapCell.getAttribute('data-drilldown') || 'company_compliance';
                state.currentDrilldownPage = 0;
                loadDrilldown(
                    root,
                    state,
                    state.currentDrilldown,
                    heatmapOverrides,
                    0,
                    state.currentDrilldownPerPage || 20
                );
                return;
            }

            var groupedDrilldown = event.target.closest('[data-action="grouped-drilldown"]');
            if (groupedDrilldown && root.contains(groupedDrilldown)) {
                rememberCurrentState(root, state);
                var groupedOverrides = {};
                var groupedCompanyId = groupedDrilldown.getAttribute('data-companyid') || '0';
                var groupedCompanyName = groupedDrilldown.getAttribute('data-companyname') || '';
                if (groupedCompanyId !== '0') {
                    groupedOverrides.companyids = [groupedCompanyId];
                } else if (groupedCompanyName !== '') {
                    groupedOverrides.companies = [groupedCompanyName];
                }
                state.currentDrilldown = groupedDrilldown.getAttribute('data-drilldown') || 'company_compliance';
                state.currentDrilldownPage = 0;
                loadDrilldown(root, state, state.currentDrilldown, groupedOverrides, 0, state.currentDrilldownPerPage || 20);
                return;
            }

            var barDrilldown = event.target.closest('[data-action="bar-drilldown"]');
            if (barDrilldown && root.contains(barDrilldown)) {
                rememberCurrentState(root, state);
                var barOverrides = {};
                var barCompanyId = barDrilldown.getAttribute('data-companyid') || '0';
                var barCompanyName = barDrilldown.getAttribute('data-companyname') || '';
                var barCourseId = barDrilldown.getAttribute('data-courseid') || '0';
                if (barCompanyId !== '0') {
                    barOverrides.companyids = [barCompanyId];
                } else if (barCompanyName !== '') {
                    barOverrides.companies = [barCompanyName];
                }
                if (barCourseId !== '0') {
                    barOverrides.courseids = [barCourseId];
                }
                state.currentDrilldown = barDrilldown.getAttribute('data-drilldown') || 'company_compliance';
                state.currentDrilldownPage = 0;
                loadDrilldown(root, state, state.currentDrilldown, barOverrides, 0, state.currentDrilldownPerPage || 20);
                return;
            }

            var pager = event.target.closest('[data-action="drilldown-page"]');
            if (pager && root.contains(pager) && !pager.disabled && state.currentDrilldown) {
                rememberCurrentState(root, state);
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
                rememberCurrentState(root, state);
                state.currentTab = 'kpis';
                setActiveTab(root, 'kpis');
                state.currentDrilldownPage = 0;
                var kpiOverrides = undefined;
                if (kpi.getAttribute('data-filter-status')) {
                    kpiOverrides = {status: kpi.getAttribute('data-filter-status')};
                }
                loadDrilldown(root, state, kpi.getAttribute('data-drilldown'), kpiOverrides, 0, state.currentDrilldownPerPage || 20);
                return;
            }

            var tab = event.target.closest('[data-tab]');
            if (tab && root.contains(tab)) {
                rememberCurrentState(root, state);
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
        initViewStretchToggle(root, state);
        initStatusModeToggle(root, state);
        updateBackButtonState(state);
        if (root.getAttribute('data-history-bound') !== '1') {
            root.setAttribute('data-history-bound', '1');
            window.addEventListener('popstate', function(event) {
                restoreBrowserHistoryState(root, state, event.state);
            });
        }

        Str.get_strings(stringList).then(function(values) {
            stringTargets.forEach(function(target, index) {
                strings[target] = values[index];
            });
            strings.allLabels = {
                companies: strings.allcompanieslabel,
                courses: strings.allcourseslabel,
                daterange: strings.allperiodslabel,
                departments: strings.alldepartmentslabel,
                locations: strings.alllocationslabel,
                positions: strings.allpositionslabel,
                personnelcategories: strings.allpersonnelcategorieslabel,
                sites: strings.allsiteslabel,
                educations: strings.alleducationslabel
            };

            bindEvents(root, state);
            refresh(root, state, 'replace').then(function() {
                updateFilterCounts(root, state);
                if (matchesBrowserHistoryState(window.history.state, state)) {
                    restoreBrowserHistoryState(root, state, window.history.state);
                }
            });
        }).catch(Notification.exception);
    };

    return {
        init: init
    };
});
