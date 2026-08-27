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

use core_ai\hook\after_ai_provider_form_hook;

/**
 * Tests for the provider-instance form extension (hook_listener).
 *
 * The form must expose one credit-per-action field per action of each service
 * (`ratelimit_{sid}_credit_{actionkey}`), flat-keyed per D1, plus the licensekey
 * field, with defaults prefilled from the catalogue.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\hook_listener::class)]
final class hook_listener_test extends \advanced_testcase {
    /**
     * Build a bare MoodleQuickForm and dispatch the hook handler on it directly
     * (no need for the full core_ai\form\ai_provider_form scaffolding).
     *
     * @return \MoodleQuickForm
     */
    private function dispatch(): \MoodleQuickForm {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        $mform = new \MoodleQuickForm('test_form', 'post', '');
        $hook = new after_ai_provider_form_hook(mform: $mform, plugin: 'aiprovider_datacurso');
        hook_listener::set_form_definition_for_aiprovider_datacurso($hook);

        return $mform;
    }

    /**
     * The license key field survives the port.
     */
    public function test_licensekey_field_is_present(): void {
        $mform = $this->dispatch();

        $this->assertTrue($mform->elementExists('licensekey'));
    }

    /**
     * Every action of every service gets its own flat-keyed credit field.
     */
    public function test_each_service_action_exposes_a_credit_field(): void {
        $mform = $this->dispatch();

        $types = new \ReflectionProperty(\MoodleQuickForm::class, '_types');
        $types->setAccessible(true);
        $typemap = $types->getValue($mform);

        foreach (provider::get_services() as $service) {
            $sid = $service['id'];
            foreach (provider::get_actions_for_service($sid) as $action) {
                $field = "ratelimit_{$sid}_credit_{$action['key']}";
                $this->assertTrue($mform->elementExists($field), "Missing credit field {$field}");
                $this->assertSame(PARAM_INT, $typemap[$field] ?? null);
            }
        }
    }

    /**
     * Credit fields are prefilled from the catalogue default for the action.
     */
    public function test_credit_field_defaults_from_the_catalogue(): void {
        $mform = $this->dispatch();

        $sid = 'local_coursegen';
        $action = provider::get_actions_for_service($sid)[0];
        $field = "ratelimit_{$sid}_credit_{$action['key']}";

        $this->assertEquals($action['default'], $mform->getElement($field)->getValue());
    }

    /**
     * The window value and unit are grouped into a single "Time window" control,
     * mirroring the 4.5 form, and the value is prefilled from get_default_window_limit().
     */
    public function test_window_value_defaults_from_the_catalogue(): void {
        $mform = $this->dispatch();

        $sid = 'local_coursegen';
        $group = "ratelimit_{$sid}_window";

        $this->assertTrue($mform->elementExists($group), "Missing time window group {$group}");

        $elements = [];
        foreach ($mform->getElement($group)->getElements() as $element) {
            $elements[$element->getName()] = $element;
        }

        $this->assertArrayHasKey("ratelimit_{$sid}_window_value", $elements);
        $this->assertArrayHasKey("ratelimit_{$sid}_window_unit", $elements);
        $this->assertEquals(
            provider::get_default_window_limit($sid),
            $elements["ratelimit_{$sid}_window_value"]->getValue()
        );
    }

    /**
     * The time window group carries a help button (ratelimit_window), as the 4.5 form did.
     */
    public function test_time_window_group_has_a_help_button(): void {
        $mform = $this->dispatch();

        $sid = 'local_coursegen';
        $element = $mform->getElement("ratelimit_{$sid}_window");
        $vars = get_object_vars($element);

        $this->assertNotEmpty($vars['_helpbutton'] ?? null);
    }

    /**
     * Credit fields carry a help button (ratelimit_creditperaction).
     */
    public function test_credit_field_has_a_help_button(): void {
        $mform = $this->dispatch();

        $sid = 'local_coursegen';
        $action = provider::get_actions_for_service($sid)[0];
        $field = "ratelimit_{$sid}_credit_{$action['key']}";

        $element = $mform->getElement($field);
        $vars = get_object_vars($element);

        $this->assertNotEmpty($vars['_helpbutton'] ?? null);
    }

    /**
     * Credit fields carry a client-side positive-integer validation rule, mirroring the
     * 4.5 config_form's validation() checks -- there is no server-side validation() seam on
     * core_ai\form\ai_provider_form, so this must be a client rule registered via addRule().
     */
    public function test_credit_field_has_a_positive_integer_client_rule(): void {
        $mform = $this->dispatch();

        $sid = 'local_coursegen';
        $action = provider::get_actions_for_service($sid)[0];
        $field = "ratelimit_{$sid}_credit_{$action['key']}";

        $reflection = new \ReflectionProperty(\MoodleQuickForm::class, '_rules');
        $reflection->setAccessible(true);
        $rules = $reflection->getValue($mform);

        $this->assertArrayHasKey($field, $rules, "Missing validation rule(s) for {$field}");

        $matching = array_filter(
            $rules[$field],
            static fn(array $rule): bool => $rule['type'] === 'regex' && $rule['validation'] === 'client'
        );
        $this->assertNotEmpty($matching, "Missing client-side regex rule for {$field}");

        $rule = reset($matching);
        $this->assertSame('/^[1-9]\d*$/', $rule['format']);

        // The regex must accept positive integers and reject zero, negatives and non-numerics.
        $this->assertSame(1, preg_match($rule['format'], '1'));
        $this->assertSame(1, preg_match($rule['format'], '4321'));
        $this->assertSame(0, preg_match($rule['format'], '0'));
        $this->assertSame(0, preg_match($rule['format'], '-1'));
        $this->assertSame(0, preg_match($rule['format'], 'abc'));
    }

    /**
     * Credit fields are hidden when the service's rate limit is disabled, same as
     * the limit/window fields.
     */
    public function test_credit_fields_hide_when_the_service_is_disabled(): void {
        $mform = $this->dispatch();

        $sid = 'local_coursegen';
        $action = provider::get_actions_for_service($sid)[0];
        $field = "ratelimit_{$sid}_credit_{$action['key']}";

        $reflection = new \ReflectionProperty(\MoodleQuickForm::class, '_hideifs');
        $reflection->setAccessible(true);
        $hideifs = $reflection->getValue($mform);

        $this->assertContains($field, $hideifs["ratelimit_{$sid}_enable"]['eq']['0'] ?? []);
    }
}
