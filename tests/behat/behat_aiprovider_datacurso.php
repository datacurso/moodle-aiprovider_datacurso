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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Step definitions specific to the Datacurso AI provider.
 *
 * Only steps that encode plugin-specific meaning live here: navigation, capability checks,
 * "table" and "link" existence, and generic text assertions all use core steps directly in the
 * feature files instead of being wrapped by custom steps here.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_aiprovider_datacurso extends behat_base {
    /**
     * Assert the "Datacurso plugins list" table lists exactly the given number of plugins.
     *
     * @Then /^I should see "(?P<count>\d+)" plugins listed$/
     *
     * @param int $count Expected number of plugin rows.
     */
    public function i_should_see_n_plugins_listed(int $count): void {
        $actual = count($this->get_pluginslist_rows());
        if ($actual !== $count) {
            throw new ExpectationException(
                "Expected {$count} plugins listed, but found {$actual}.",
                $this->getSession()
            );
        }
    }

    /**
     * Assert every row of the "Datacurso plugins list" table shows one of the two given
     * installation status values in its "Installed" column.
     *
     * @Then /^each listed plugin should show an installation status of "(?P<status1>(?:[^"]|\\")*)" or "(?P<status2>(?:[^"]|\\")*)"$/
     *
     * @param string $status1 First accepted status value (e.g. "Yes").
     * @param string $status2 Second accepted status value (e.g. "No").
     */
    public function each_listed_plugin_should_show_an_installation_status_of(string $status1, string $status2): void {
        $allowed = [$status1, $status2];

        foreach ($this->get_pluginslist_rows() as $index => $row) {
            $cells = $row->findAll('css', 'td');
            if (count($cells) < 3) {
                continue;
            }
            $status = trim($cells[2]->getText());
            if (!in_array($status, $allowed, true)) {
                throw new ExpectationException(
                    'Row ' . ($index + 1) . " has installation status \"{$status}\", expected one of: " .
                        implode(', ', $allowed) . '.',
                    $this->getSession()
                );
            }
        }
    }

    /**
     * Assert every row of the "Datacurso plugins list" table exposes a link.
     *
     * @Then each listed plugin should expose a link
     */
    public function each_listed_plugin_should_expose_a_link(): void {
        foreach ($this->get_pluginslist_rows() as $index => $row) {
            if (empty($row->findAll('css', 'a[href]'))) {
                throw new ExpectationException(
                    'Row ' . ($index + 1) . ' does not expose a link.',
                    $this->getSession()
                );
            }
        }
    }

    /**
     * Assert the rows of the given table are sorted by their "Date" column, either ascending
     * or descending (the direction toggled by clicking the column header is not asserted here).
     *
     * @Then /^the rows of the "(?P<table_string>(?:[^"]|\\")*)" table should be sorted by date$/
     *
     * @param string $tablename Locator for the "table" named selector.
     */
    public function the_rows_of_the_table_should_be_sorted_by_date(string $tablename): void {
        $table = $this->find('table', $tablename);

        $dateindex = null;
        foreach ($table->findAll('css', 'thead th') as $index => $header) {
            if (trim($header->getText()) === get_string('date')) {
                $dateindex = $index;
                break;
            }
        }
        if ($dateindex === null) {
            throw new ExpectationException(
                "Could not find a \"Date\" column header in the \"{$tablename}\" table.",
                $this->getSession()
            );
        }

        $timestamps = [];
        foreach ($table->findAll('css', 'tbody tr') as $row) {
            $cells = $row->findAll('css', 'td');
            if (!isset($cells[$dateindex])) {
                continue;
            }
            $timestamp = strtotime(trim($cells[$dateindex]->getText()));
            if ($timestamp === false) {
                throw new ExpectationException(
                    'Could not parse a date value while checking the sort order.',
                    $this->getSession()
                );
            }
            $timestamps[] = $timestamp;
        }

        $ascending = $timestamps;
        sort($ascending);
        $descending = $timestamps;
        rsort($descending);

        if ($timestamps !== $ascending && $timestamps !== $descending) {
            throw new ExpectationException(
                "The rows of the \"{$tablename}\" table are not sorted by date.",
                $this->getSession()
            );
        }
    }

    /**
     * Assert the content of a file downloaded by a non-JavaScript form submission (e.g.
     * pressing a report table's "Download" button) contains the given text.
     *
     * Only reliable for non-@javascript scenarios: with a real browser driver the download
     * happens outside the page, so this reads the current page content directly, which is the
     * raw exported file body after a full-page (non-AJAX) form submission.
     *
     * @Then /^the downloaded file should contain "(?P<text>(?:[^"]|\\")*)"$/
     *
     * @param string $text Text expected to be present in the downloaded content.
     */
    public function the_downloaded_file_should_contain(string $text): void {
        $content = $this->getSession()->getPage()->getContent();
        if (strpos($content, $text) === false) {
            throw new ExpectationException(
                "The string \"{$text}\" was not found in the downloaded file content.",
                $this->getSession()
            );
        }
    }

    /**
     * Get the data rows of the "Datacurso plugins list" table on the current page.
     *
     * @return \Behat\Mink\Element\NodeElement[]
     */
    protected function get_pluginslist_rows(): array {
        $table = $this->find('css', 'table.generaltable');
        return $table->findAll('css', 'tbody tr');
    }
}
