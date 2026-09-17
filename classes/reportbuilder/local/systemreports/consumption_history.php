<?php
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

namespace aiprovider_datacurso\reportbuilder\local\systemreports;

use aiprovider_datacurso\reportbuilder\local\entities\consumption;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\system_report;
use lang_string;

/**
 * Datacurso credit consumption history system report.
 *
 * Renders the local mirror table {aiprovider_datacurso_consumption} with the Report Builder
 * framework, providing filters, pagination, sorting and download (CSV/Excel/ODS) for free.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Josue <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class consumption_history extends system_report {
    /**
     * Initialise the report: main table, entities, columns and filters.
     */
    protected function initialise(): void {
        $entitymain = new consumption();
        $mainalias = $entitymain->get_table_alias('aiprovider_datacurso_consumption');

        $this->set_main_table('aiprovider_datacurso_consumption', $mainalias);
        $this->add_entity($entitymain);
        $this->set_downloadable(true, get_string('link_consumptionhistory', 'aiprovider_datacurso'));
        $this->set_default_per_page(10);

        // Join the core user entity to resolve the consumer's full name.
        $entityuser = new user();
        $useralias = $entityuser->get_table_alias('user');
        $this->add_entity($entityuser->add_join(
            "LEFT JOIN {user} {$useralias} ON {$useralias}.id = {$mainalias}.userid"
        ));

        $this->add_columns_from_entities([
            'consumption:externalid',
            'user:fullname',
            'consumption:action',
            'consumption:service',
            'consumption:credits',
            'consumption:balance',
            'consumption:timecreated',
        ]);

        $this->add_filters_from_entities([
            'user:fullname',
            'consumption:service',
            'consumption:action',
            'consumption:timecreated',
        ]);

        $this->set_initial_sort_column('consumption:timecreated', SORT_DESC);
        $this->set_downloadable(true, get_string('link_consumptionhistory', 'aiprovider_datacurso'));
    }

    /**
     * Validate access to view this report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('aiprovider/datacurso:viewreports', $this->get_context());
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('link_consumptionhistory', 'aiprovider_datacurso');
    }
}
