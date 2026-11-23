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
     * The window value and unit are grouped into a single "Time window" control, mirroring the
     * 4.5 form. The window VALUE defaults to 1 (e.g. "1 hour") -- the catalogue default
     * (get_default_window_limit()) is a credit BUDGET (e.g. 2000 for local_coursegen), not a
     * window length, and must be wired to the "limit" field instead (see
     * test_limit_defaults_from_the_catalogue).
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
        $this->assertEquals(1, $elements["ratelimit_{$sid}_window_value"]->getValue());
    }

    /**
     * The "limit" field -- the actual credit budget enforced per window -- is prefilled from the
     * catalogue default (get_default_window_limit()), mirroring 4.5's config_form::current_data().
     */
    public function test_limit_defaults_from_the_catalogue(): void {
        $mform = $this->dispatch();

        $sid = 'local_coursegen';
        $field = "ratelimit_{$sid}_limit";

        $this->assertTrue($mform->elementExists($field), "Missing limit field {$field}");
        $this->assertEquals(
            provider::get_default_window_limit($sid),
            $mform->getElement($field)->getValue()
        );
    }

    /**
     * The window unit defaults to 'hours', mirroring 4.5's config_form::current_data().
     */
    public function test_window_unit_defaults_to_hours(): void {
        $mform = $this->dispatch();

        $sid = 'local_coursegen';
        $group = "ratelimit_{$sid}_window";

        $elements = [];
        foreach ($mform->getElement($group)->getElements() as $element) {
            $elements[$element->getName()] = $element;
        }

        $this->assertArrayHasKey("ratelimit_{$sid}_window_unit", $elements);
        // HTML_QuickForm_select::getValue() always wraps the selected value(s) in an array,
        // even for a single, non-multiple select.
        $this->assertSame(['hours'], $elements["ratelimit_{$sid}_window_unit"]->getValue());
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

    /**
     * Build the form as ai/configure.php does when it edits the given provider instance.
     *
     * The hook does not receive the instance, so it reads the "id" parameter of the page.
     *
     * @param int $id Id of the edited provider instance.
     * @return \MoodleQuickForm
     */
    private function dispatch_for_instance(int $id): \MoodleQuickForm {
        $_GET['id'] = $id;
        try {
            return $this->dispatch();
        } finally {
            unset($_GET['id']);
        }
    }

    /**
     * Create a provider instance with the given config.
     *
     * @param array $config Instance config.
     * @param string $classname Provider class of the instance.
     * @return \core_ai\provider
     */
    private function create_instance(array $config, string $classname = provider::class): \core_ai\provider {
        global $DB;
        return (new \core_ai\manager($DB))->create_provider_instance(
            classname: $classname,
            name: 'test',
            enabled: true,
            config: $config,
        );
    }

    /**
     * The four Jedomi actions are prefilled with a `ratelimit_local_jedomi_credit_default` saved before the split.
     *
     * Decision 10: saving the form then keeps N on every action instead of the catalog defaults. On Moodle 5.0 the
     * core form saves exactly the submitted fields, so the legacy key, which has no field, is dropped on that save.
     */
    public function test_jedomi_actions_prefill_from_a_saved_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(
            ['chat' => 2, 'speech' => 2, 'transcription' => 2, 'file_read' => 2],
            hook_listener::get_credit_defaults('local_jedomi', [])
        );
        $this->assertSame(
            ['chat' => 7, 'speech' => 7, 'transcription' => 7, 'file_read' => 7],
            hook_listener::get_credit_defaults('local_jedomi', ['ratelimit_local_jedomi_credit_default' => 7])
        );
        $this->assertSame(
            ['chat' => 7, 'speech' => 1, 'transcription' => 7, 'file_read' => 7],
            hook_listener::get_credit_defaults('local_jedomi', [
                'ratelimit_local_jedomi_credit_default' => 7,
                'ratelimit_local_jedomi_credit_speech' => 1,
            ])
        );

        $instance = $this->create_instance(['licensekey' => 'k', 'ratelimit_local_jedomi_credit_default' => 7]);
        $mform = $this->dispatch_for_instance((int) $instance->id);

        foreach (['chat', 'speech', 'transcription', 'file_read'] as $key) {
            $this->assertEquals(7, $mform->getElement("ratelimit_local_jedomi_credit_{$key}")->getValue(), $key);
        }
        $this->assertFalse($mform->elementExists('ratelimit_local_jedomi_credit_default'));
    }

    /**
     * REL-001: a saved 'default' does not prefill the actions of a service other than Jedomi; they keep the
     * catalog values.
     */
    public function test_other_service_actions_do_not_prefill_from_a_saved_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $config = ['licensekey' => 'k', 'ratelimit_local_coursegen_credit_default' => 5];
        $expected = ['course_image' => 2000, 'course_noimage' => 1000, 'activity_image' => 100, 'activity_noimage' => 50];

        $this->assertSame($expected, hook_listener::get_credit_defaults('local_coursegen', $config));

        $mform = $this->dispatch_for_instance((int) $this->create_instance($config)->id);
        foreach ($expected as $key => $credits) {
            $this->assertEquals($credits, $mform->getElement("ratelimit_local_coursegen_credit_{$key}")->getValue(), $key);
        }
    }

    /**
     * Only an existing Datacurso instance is read: a new instance or another provider's instance keeps the catalog.
     */
    public function test_jedomi_prefill_ignores_instances_that_are_not_datacurso(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $other = $this->create_instance(
            ['apikey' => 'k', 'ratelimit_local_jedomi_credit_default' => 7],
            \aiprovider_openai\provider::class
        );

        foreach ([0, (int) $other->id, -1] as $id) {
            $mform = $this->dispatch_for_instance($id);
            $this->assertEquals(2, $mform->getElement('ratelimit_local_jedomi_credit_chat')->getValue(), (string) $id);
        }
    }
}
