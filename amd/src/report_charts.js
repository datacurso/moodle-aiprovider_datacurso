// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Report charts module.
 *
 * @module     aiprovider_datacurso/report_charts
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Chart from 'core/chartjs';
import { get_string as getString, getStrings } from 'core/str';
import Notification from 'core/notification';
import AutoComplete from 'core/form-autocomplete';

export const init = async () => {

    const [date, creditsConsumedMonth, creditsConsumedDay, creditsConsumed] = await getStrings([
        {key: 'date', component: 'core'},
        {key: 'tokensconsumedmonth', component: 'aiprovider_datacurso'},
        {key: 'tokensconsumedday', component: 'aiprovider_datacurso'},
        {key: 'tokensconsumed', component: 'aiprovider_datacurso'},
    ]);

    const tokensAvailable = document.getElementById('tokens-available');
    const tokensConsumed = document.getElementById('tokens-consumed');
    const userTokensConsumed = document.getElementById('user-tokens-consumed');

    let chartBar, chartPie, chartDay, chartUser;

    const pieColors = [
        '#36A2EB', '#FF6384', '#f1d48bff', '#5ddcdcff', '#049930ff', '#0b6eb0ff',
        '#d10f39ff', '#7c611dff', '#ee9610ff', '#a50562ff', '#022082ff', '#efef21ff',
        '#3f4646ff', '#8f9191ff', '#0c361bff', '#bd836cff'
    ];

    // Year filter state. The report shows one year at a time (default: the current year).
    const START_YEAR = 2024;
    const currentYear = new Date().getFullYear();
    let selectedYear = currentYear;

    const yearRange = (year) => ({
        fromdate: `${year}-01-01`,
        todate: `${year}-12-31`,
    });

    // Fetch server-side aggregated totals for a single dimension (month/day/action/service).
    // Returns { total, summary: [{ label, total }] }; the server does the grouping.
    const fetchSummary = async (groupby, params = {}) => {
        const args = { groupby, service: "", action: "", userid: 0, ...yearRange(selectedYear), ...params };
        try {
            const response = await Ajax.call([{
                methodname: 'aiprovider_datacurso_get_consumption_summary',
                args
            }])[0];

            if (response.status !== 'success') {
                return { total: 0, summary: [] };
            }
            return { total: response.total || 0, summary: response.summary || [] };
        } catch (error) {
            Notification.exception(error);
            return { total: 0, summary: [] };
        }
    };

    // Load the balance card independently.
    Ajax.call([{ methodname: 'aiprovider_datacurso_get_credits_balance', args: {} }])[0]
        .then((balanceResponse) => {
            tokensAvailable.textContent = balanceResponse?.balance || 0;
        })
        .catch((e) => {
            let msg = e.message;
            Notification.addNotification({
                message: msg,
                type: 'error'
            });
        });

    // Load the service list (for the bar/pie filter dropdowns), then build the charts.
    Ajax.call([{ methodname: 'aiprovider_datacurso_get_services', args: {} }])[0]
        .then((servicesResponse) => {
            initCharts(servicesResponse?.services || []);
        })
        .catch(Notification.exception);

    // Init graphs.
    const initCharts = async (services) => {
        const filterYear = document.getElementById('filter-year');
        const filterBar = document.getElementById('filter-service-bar');
        const filterPie = document.getElementById('filter-service-pie');
        const filterUser = document.getElementById('filter-user-charts');
        const filterStart = document.getElementById('filter-start-date');
        const filterEnd = document.getElementById('filter-end-date');

        // Populate the year selector (current year down to START_YEAR), current year selected.
        for (let y = currentYear; y >= START_YEAR; y--) {
            const opt = document.createElement('option');
            opt.value = y;
            opt.textContent = y;
            if (y === selectedYear) {
                opt.selected = true;
            }
            filterYear.appendChild(opt);
        }

        const fillSelect = (select, items) => {
            if (items?.length) {
                items.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.name || item.fullname;
                    select.appendChild(opt);
                });
            }
        };

        fillSelect(filterBar, services);
        fillSelect(filterPie, services);

        // Enhance with Autocomplete for the user chart (AJAX).
        const placeholder = await getString('search', 'core');
        AutoComplete.enhance(
            '#filter-user-charts',
            false,
            'aiprovider_datacurso/repository',
            placeholder,
            false,
            true,
            '',
            false,
            1
        );

        updateBarChart();
        updatePieChart();
        updateDayChart();
        updateUserChart();

        // Listeners filters.
        filterYear.addEventListener('change', () => updateYear());
        filterBar.addEventListener('change', () => updateBarChart());
        filterPie.addEventListener('change', () => updatePieChart());
        filterUser.addEventListener('change', () => updateUserChart());
        filterStart.addEventListener('change', () => updateDayChart());
        filterEnd.addEventListener('change', () => updateDayChart());
    };

    // Re-render every chart for the newly selected year, respecting each chart's active filter.
    const updateYear = () => {
        selectedYear = parseInt(document.getElementById('filter-year').value, 10) || currentYear;
        updateBarChart();
        updatePieChart();
        updateDayChart();
        updateUserChart();
    };

    // Bar chart: credits by month.
    const renderBarChart = (summary) => {
        const ctx = document.getElementById('chart-tokens-by-month');
        if (chartBar) {
            chartBar.destroy();
        }

        chartBar = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: summary.map(s => s.label),
                datasets: [{
                    label: creditsConsumedMonth,
                    data: summary.map(s => s.total),
                    backgroundColor: '#0073e6',
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    };

    const updateBarChart = async () => {
        const service = document.getElementById('filter-service-bar').value;
        const { total, summary } = await fetchSummary('month', service ? { service } : {});
        tokensConsumed.textContent = Number(total).toFixed(2);
        renderBarChart(summary);
    };

    // Pie chart: credits by action.
    const renderPieChart = (summary) => {
        const ctx = document.getElementById('chart-actions');
        if (chartPie) {
            chartPie.destroy();
        }

        chartPie = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: summary.map(s => s.label),
                datasets: [{
                    data: summary.map(s => s.total),
                    backgroundColor: pieColors,
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    };

    const updatePieChart = async () => {
        const service = document.getElementById('filter-service-pie').value;
        const { summary } = await fetchSummary('action', service ? { service } : {});
        renderPieChart(summary);
    };

    // Day chart: credits by day.
    const renderDayChart = (summary) => {
        const ctx = document.getElementById('chart-tokens-by-day');
        if (chartDay) {
            chartDay.destroy();
        }

        chartDay = new Chart(ctx, {
            type: 'line',
            data: {
                labels: summary.map(s => s.label),
                datasets: [{
                    label: creditsConsumedDay,
                    data: summary.map(s => s.total),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40,167,69,0.2)',
                    fill: true,
                    tension: 0.2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { title: { display: true, text: date } },
                    y: { title: { display: true, text: creditsConsumed }, beginAtZero: true }
                }
            }
        });
    };

    const updateDayChart = async () => {
        const fromdate = document.getElementById('filter-start-date').value;
        const todate = document.getElementById('filter-end-date').value;

        const params = {};
        if (fromdate) {
            params.fromdate = fromdate;
        }
        if (todate) {
            params.todate = todate;
        }

        const { summary } = await fetchSummary('day', params);
        renderDayChart(summary);
    };

    // User chart: credits by service.
    const renderUserChart = (summary, total) => {
        if (userTokensConsumed) {
            userTokensConsumed.textContent = total.toFixed(2);
        }

        const ctx = document.getElementById('chart-user-consumption');
        if (!ctx) {
            return;
        }

        if (chartUser) {
            chartUser.destroy();
        }

        chartUser = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: summary.map(s => s.label),
                datasets: [{
                    label: creditsConsumed,
                    data: summary.map(s => s.total),
                    backgroundColor: '#6c757d',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
            }
        });
    };

    const updateUserChart = async () => {
        const userId = document.getElementById('filter-user-charts').value;
        const { total, summary } = await fetchSummary('service', userId ? { userid: userId } : {});
        renderUserChart(summary, total);
    };
};
