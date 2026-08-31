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

namespace aiprovider_datacurso\reportbuilder\local\entities;

use aiprovider_datacurso\provider;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;

/**
 * Consumption entity for the Datacurso credit consumption report.
 *
 * Backed by the local mirror table {aiprovider_datacurso_consumption}. Raw service and action
 * identifiers are resolved to their display names via callbacks against {@see provider}.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Josue <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class consumption extends base {
    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'aiprovider_datacurso_consumption',
        ];
    }

    /**
     * The default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity_consumption', 'aiprovider_datacurso');
    }

    /**
     * Initialise the entity, adding all columns and filters.
     *
     * @return base
     */
    public function initialise(): base {
        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }

        foreach ($this->get_all_filters() as $filter) {
            $this->add_filter($filter)->add_condition($filter);
        }

        return $this;
    }

    /**
     * Map of service identifier => display name.
     *
     * @return array<string, string>
     */
    private static function service_names(): array {
        $map = [];
        foreach (provider::get_services() as $service) {
            $map[$service['id']] = $service['name'];
        }
        return $map;
    }

    /**
     * Map of action identifier => display name.
     *
     * @return array<string, string>
     */
    private static function action_names(): array {
        $map = [];
        foreach (provider::get_actions() as $action) {
            $map[$action['id']] = $action['name'];
        }
        return $map;
    }

    /**
     * Returns the list of all available columns.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $tablealias = $this->get_table_alias('aiprovider_datacurso_consumption');

        $columns = [];

        // Consumption id column.
        $columns[] = (new column(
            'externalid',
            new lang_string('id', 'aiprovider_datacurso'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$tablealias}.externalid")
            ->set_is_sortable(true);

        // Action column.
        $columns[] = (new column(
            'action',
            new lang_string('action', 'aiprovider_datacurso'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.action")
            ->set_is_sortable(true)
            ->add_callback(static function($value): string {
                static $map = null;
                if ($map === null) {
                    $map = self::action_names();
                }
                return $map[$value] ?? (string) $value;
            });

        // Service column.
        $columns[] = (new column(
            'service',
            new lang_string('service', 'aiprovider_datacurso'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.service")
            ->set_is_sortable(true)
            ->add_callback(static function($value): string {
                static $map = null;
                if ($map === null) {
                    $map = self::service_names();
                }
                return $map[$value] ?? (string) $value;
            });

        // Credits consumed column.
        $columns[] = (new column(
            'credits',
            new lang_string('tokensused', 'aiprovider_datacurso'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$tablealias}.credits")
            ->set_is_sortable(true);

        // Remaining balance column.
        $columns[] = (new column(
            'balance',
            new lang_string('remainingtokens', 'aiprovider_datacurso'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$tablealias}.balance")
            ->set_is_sortable(true);

        // Date column.
        $columns[] = (new column(
            'timecreated',
            new lang_string('date'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.timecreated")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate'], get_string('strftimedatetimeshortaccurate', 'core_langconfig'));

        return $columns;
    }

    /**
     * Returns the list of all available filters.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $tablealias = $this->get_table_alias('aiprovider_datacurso_consumption');

        $filters = [];

        // Service filter.
        $filters[] = (new filter(
            select::class,
            'service',
            new lang_string('service', 'aiprovider_datacurso'),
            $this->get_entity_name(),
            "{$tablealias}.service"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static function(): array {
                return self::service_names();
            });

        // Action filter.
        $filters[] = (new filter(
            select::class,
            'action',
            new lang_string('action', 'aiprovider_datacurso'),
            $this->get_entity_name(),
            "{$tablealias}.action"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static function(): array {
                return self::action_names();
            });

        // Date filter.
        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('date'),
            $this->get_entity_name(),
            "{$tablealias}.timecreated"
        ))
            ->add_joins($this->get_joins())
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_RANGE,
                date::DATE_PREVIOUS,
                date::DATE_CURRENT,
            ]);

        return $filters;
    }
}
