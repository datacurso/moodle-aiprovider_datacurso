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

namespace aiprovider_datacurso;

/**
 * Tests for the hook registration file.
 *
 * The 5.0 line registers form-extension hooks (after_ai_provider_form_hook,
 * after_ai_action_settings_form_hook) plus the primary navigation hook. The 4.5
 * line only had a duplicate navigation callback through hook_callbacks, now
 * superseded by hook\navigation::primary_extend. Exactly the 5.0 set survives.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class hooks_test extends \basic_testcase {
    /**
     * Load db/hooks.php in an isolated scope and return the declared $callbacks array.
     *
     * @return array
     */
    private function load_callbacks(): array {
        $loader = static function (): array {
            require(__DIR__ . '/../db/hooks.php');
            return $callbacks;
        };

        return $loader();
    }

    /**
     * Exactly three callbacks are registered: the two form-extension hooks and
     * the primary navigation hook. hook_callbacks::extend_primary_navigation is gone.
     */
    public function test_hooks_file_registers_exactly_the_three_intended_callbacks(): void {
        $callbacks = $this->load_callbacks();

        $this->assertCount(3, $callbacks);

        $pairs = array_map(
            static fn(array $entry): string => $entry['hook'] . '::' . $entry['callback'],
            $callbacks
        );

        $this->assertContains(
            \core_ai\hook\after_ai_provider_form_hook::class . '::' .
                \aiprovider_datacurso\hook_listener::class . '::set_form_definition_for_aiprovider_datacurso',
            $pairs
        );
        $this->assertContains(
            \core_ai\hook\after_ai_action_settings_form_hook::class . '::' .
                \aiprovider_datacurso\hook_listener::class . '::set_model_form_definition_for_aiprovider_datacurso',
            $pairs
        );
        $this->assertContains(
            \core\hook\navigation\primary_extend::class . '::' .
                \aiprovider_datacurso\hook\navigation::class . '::primary_extend',
            $pairs
        );

        foreach ($callbacks as $entry) {
            $this->assertStringNotContainsString('hook_callbacks', $entry['callback']);
        }
    }
}
