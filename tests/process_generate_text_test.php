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

use core_ai\aiactions\generate_text;
use core_ai\aiactions\summarise_text;

/**
 * Tests for the admin-configured system instruction reaching the outgoing payload.
 *
 * get_system_instruction() used to read $this->action->get_configuration('systeminstruction'),
 * which is always null: the provider-instance form (action_generate_text_form) stores the value
 * under $this->provider->actionconfig[$actionclass]['settings']['systeminstruction'], the same
 * pattern used by aiprovider_openai and aiprovider_azureai. This left the admin-configured
 * instruction dead: every request silently fell back to the action's default instruction.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\process_generate_text::class)]
final class process_generate_text_test extends \advanced_testcase {
    /**
     * Seed a provider instance whose generate_text actionconfig carries an admin-configured
     * system instruction, mirroring what the instance form actually stores.
     *
     * @param string $instruction
     * @param string $actionclass Action class the instruction is stored under.
     * @return provider
     */
    private function make_provider_with_instruction(
        string $instruction,
        string $actionclass = generate_text::class
    ): provider {
        global $DB;
        $manager = new \core_ai\manager($DB);

        return $manager->create_provider_instance(
            classname: provider::class,
            name: 'test',
            enabled: true,
            config: ['licensekey' => 'test-key'],
            actionconfig: [
                $actionclass => [
                    'enabled' => true,
                    'settings' => ['systeminstruction' => $instruction],
                ],
            ],
        );
    }

    /**
     * The admin-configured instruction, stored on the provider's actionconfig, must reach the
     * outgoing request body as the "system" message.
     */
    public function test_generate_text_sends_the_admin_configured_instruction(): void {
        $this->resetAfterTest();

        $provider = $this->make_provider_with_instruction('ADMIN_RULE');
        $action = new generate_text(contextid: 1, userid: 1, prompttext: 'Hello');
        $processor = new process_generate_text($provider, $action);

        $method = new \ReflectionMethod($processor, 'build_request_body');
        $body = (object) $method->invoke($processor, '1');

        $this->assertSame('system', $body->messages[0]['role']);
        $this->assertSame('ADMIN_RULE', $body->messages[0]['content']);
    }

    /**
     * summarise_text inherits get_system_instruction() from process_generate_text and must
     * respect the same admin-configured instruction.
     */
    public function test_summarise_text_sends_the_admin_configured_instruction(): void {
        $this->resetAfterTest();

        $provider = $this->make_provider_with_instruction('SUMMARISE_RULE', summarise_text::class);
        $action = new summarise_text(contextid: 1, userid: 1, prompttext: 'Some content');
        $processor = new process_summarise_text($provider, $action);

        $method = new \ReflectionMethod($processor, 'build_request_body');
        $body = (object) $method->invoke($processor, '1');

        $this->assertSame('system', $body->messages[0]['role']);
        $this->assertSame('SUMMARISE_RULE', $body->messages[0]['content']);
    }

    /**
     * With no admin-configured instruction, the processor falls back to the action's own
     * default instruction -- this behaviour must be preserved, not just the new fix.
     */
    public function test_falls_back_to_the_action_default_instruction_when_unset(): void {
        $this->resetAfterTest();

        global $DB;
        $manager = new \core_ai\manager($DB);
        $provider = $manager->create_provider_instance(
            classname: provider::class,
            name: 'test',
            enabled: true,
            config: ['licensekey' => 'test-key'],
        );

        $action = new generate_text(contextid: 1, userid: 1, prompttext: 'Hello');
        $processor = new process_generate_text($provider, $action);

        $method = new \ReflectionMethod($processor, 'build_request_body');
        $body = (object) $method->invoke($processor, '1');

        $this->assertSame(generate_text::get_system_instruction(), $body->messages[0]['content']);
    }
}
