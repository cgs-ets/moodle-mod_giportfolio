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
 * Contribution report filter management.
 * Wires the year <select> to the dynamic table so changing the year reloads
 * the table with the chapters for that year as columns.
 *
 * @module     mod_giportfolio/contributions_filter
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import CoreFilter from 'core/datafilter';
import CourseFilter from 'core/datafilter/filtertypes/courseid';
import HiddenFilter from 'mod_giportfolio/hiddenfilter';
import * as DynamicTable from 'core_table/dynamic';
import Selectors from 'core/datafilter/selectors';
import Notification from 'core/notification';
import Pending from 'core/pending';

// Filter names that are managed as hidden (never shown in the datafilter UI).
const HIDDEN_FILTERS = ['courseid', 'cmid', 'giportfolioid', 'year'];

/**
 * Initialise the contributions filter on the element with the given id.
 *
 * @param {String} filterRegionId The id for the filter region element.
 */
export const init = (filterRegionId) => {

    const filterSet = document.getElementById(filterRegionId);
    if (!filterSet) {
        return;
    }

    // Register hidden filters before init() so they are included in every request.
    // year must be registered first so it is present when the first AJAX fires.
    const yearSelect = document.querySelector('[data-region="contributions-year-selector"]');

    // Sync data-table-year from the <select> value before anything fires.
    if (yearSelect) {
        filterSet.dataset.tableYear = yearSelect.value;
    }

    // Create and initialize the core datafilter.
    const coreFilter = new CoreFilter(filterSet, function(filters, pendingPromise) {
        DynamicTable.setFilters(
            DynamicTable.getTableFromId(filterSet.dataset.tableRegion),
            {
                jointype: parseInt(filterSet.querySelector(Selectors.filterset.fields.join).value, 10),
                filters,
            }
        )
            .then(result => {
                pendingPromise.resolve();
                return result;
            })
            .catch(Notification.exception);
    });

    coreFilter.activeFilters.courseid      = new CourseFilter('courseid', filterSet);
    coreFilter.activeFilters.cmid          = new HiddenFilter('cmid', filterSet, 'tableCmid');
    coreFilter.activeFilters.giportfolioid = new HiddenFilter('giportfolioid', filterSet, 'tableGiportfolioid');
    coreFilter.activeFilters.year          = new HiddenFilter('year', filterSet, 'tableYear');

    coreFilter.init();

    // Wire the year <select> — on change update the data attribute and reload.
    if (yearSelect) {
        yearSelect.addEventListener('change', () => {
            filterSet.dataset.tableYear = yearSelect.value;
            coreFilter.updateTableFromFilter();
        });
    }

    // Restore initial filter state if the table already has filters saved.
    const tableRoot = DynamicTable.getTableFromId(filterSet.dataset.tableRegion);
    const initialFilters = DynamicTable.getFilters(tableRoot);
    if (initialFilters) {
        const initialFilterPromise = new Pending('mod_giportfolio/contributions_filter:setFilterFromConfig');
        setFilterFromConfig(coreFilter, filterSet, initialFilters)
            .then(() => initialFilterPromise.resolve())
            .catch();
    } else {
        // No saved filters — trigger a reload so the year filter is sent on first load.
        coreFilter.updateTableFromFilter();
    }
};

/**
 * Restore saved filter state.
 *
 * @param {CoreFilter} coreFilter
 * @param {HTMLElement} filterSet
 * @param {Object} config
 * @returns {Promise}
 */
const setFilterFromConfig = (coreFilter, filterSet, config) => {
    const filterConfig = Object.entries(config.filters);

    if (!filterConfig.length) {
        return Promise.resolve();
    }

    filterSet.querySelector(Selectors.filterset.fields.join).value = config.jointype;

    const filterPromises = filterConfig.map(([filterType, filterData]) => {
        if (HIDDEN_FILTERS.includes(filterType)) {
            return false;
        }

        const filterValues = filterData.values;
        if (!filterValues.length) {
            return false;
        }

        return coreFilter.addFilterRow()
            .then(([filterRow]) => {
                coreFilter.addFilter(filterRow, filterType, filterValues);
                return;
            });
    }).filter(Boolean);

    if (!filterPromises.length) {
        return Promise.resolve();
    }

    return Promise.all(filterPromises)
        .then(() => coreFilter.removeEmptyFilters())
        .then(() => coreFilter.updateFiltersOptions())
        .then(() => coreFilter.updateTableFromFilter());
};
