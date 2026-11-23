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
 * Provider tests for Datacurso AI provider.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\provider::class)]
final class provider_test extends \advanced_testcase {
    /**
     * Ensure provider no longer exposes legacy user-mapping API.
     */
    public function test_provider_has_no_ratelimit_settings_mapping_method(): void {
        $this->assertFalse(method_exists(provider::class, 'get_ratelimit_settings_class'));
    }

    /**
     * Ensure provider services list still includes known AI consumers.
     */
    public function test_get_services_contains_known_ids(): void {
        $serviceids = array_column(provider::get_services(), 'id');

        $this->assertContains('local_coursegen', $serviceids);
        $this->assertContains('local_assign_ai', $serviceids);
        $this->assertContains('report_lifestory', $serviceids);
    }

    /**
     * Build a provider instance with the given config, no DB writes.
     *
     * @param array $config
     * @return provider
     */
    private function make_instance(array $config): provider {
        return new provider(
            enabled: true,
            name: 'test',
            config: json_encode($config),
            actionconfig: '{}',
        );
    }

    /**
     * Jedomi is registered as a provider service and its name resolves to the product name.
     *
     * J1: Jedomi service registration.
     */
    public function test_get_services_includes_jedomi(): void {
        $servicemap = array_column(provider::get_services(), 'name', 'id');

        $this->assertArrayHasKey('local_jedomi', $servicemap);
        $this->assertSame('Jedomi', $servicemap['local_jedomi']);
    }

    /**
     * Jedomi lists one credits-per-action entry per billed action, labelled like the consumption history.
     *
     * Decision 10: chat, voice of the answer, audio transcription and file or image reading, 2 credits each.
     */
    public function test_jedomi_lists_its_actions_with_labels_and_defaults(): void {
        $actions = provider::get_actions_for_service('local_jedomi');

        $this->assertSame(
            ['chat' => 2, 'speech' => 2, 'transcription' => 2, 'file_read' => 2],
            array_column($actions, 'default', 'key')
        );
        $labels = [];
        foreach ($actions as $action) {
            $labels[$action['key']] = (string) $action['name'];
        }
        $this->assertSame([
            'chat' => get_string('generate_jedomi_chat', 'aiprovider_datacurso'),
            'speech' => get_string('generate_jedomi_speech', 'aiprovider_datacurso'),
            'transcription' => get_string('transcribe_jedomi_audio', 'aiprovider_datacurso'),
            'file_read' => get_string('read_jedomi_file', 'aiprovider_datacurso'),
        ], $labels);
        $this->assertSame(
            array_column($actions, 'default', 'key'),
            array_column(provider::get_service_actions()['local_jedomi'], 'default', 'key')
        );
    }

    /**
     * Each Jedomi action uses its own saved value, and the catalog default for an action not saved.
     */
    public function test_jedomi_action_credits_follow_their_saved_values(): void {
        $empty = $this->make_instance([]);
        foreach (['chat', 'speech', 'transcription', 'file_read'] as $key) {
            $this->assertSame(2, $empty->get_credit_for_action('local_jedomi', $key), $key);
        }

        $instance = $this->make_instance([
            'ratelimit_local_jedomi_credit_chat' => 5,
            'ratelimit_local_jedomi_credit_speech' => 1,
            'ratelimit_local_jedomi_credit_file_read' => 4,
        ]);

        $this->assertSame(5, $instance->get_credit_for_action('local_jedomi', 'chat'));
        $this->assertSame(1, $instance->get_credit_for_action('local_jedomi', 'speech'));
        $this->assertSame(2, $instance->get_credit_for_action('local_jedomi', 'transcription'));
        $this->assertSame(4, $instance->get_credit_for_action('local_jedomi', 'file_read'));
    }

    /**
     * A `ratelimit_local_jedomi_credit_default` saved before the actions were split keeps applying to every
     * Jedomi action.
     */
    public function test_saved_default_keeps_applying_to_every_jedomi_action(): void {
        $legacy = $this->make_instance(['ratelimit_local_jedomi_credit_default' => 7]);

        foreach (['chat', 'speech', 'transcription', 'file_read', 'default', 'unknown'] as $key) {
            $this->assertSame(7, $legacy->get_credit_for_action('local_jedomi', $key), $key);
        }

        $mixed = $this->make_instance([
            'ratelimit_local_jedomi_credit_default' => 7,
            'ratelimit_local_jedomi_credit_speech' => 1,
        ]);

        $this->assertSame(1, $mixed->get_credit_for_action('local_jedomi', 'speech'));
        $this->assertSame(7, $mixed->get_credit_for_action('local_jedomi', 'chat'));
    }

    /**
     * REL-001: a saved 'default' goes before the catalog only for Jedomi; another multi-action service keeps
     * the catalog value of an action that is not saved.
     */
    public function test_saved_default_does_not_override_the_catalog_of_other_services(): void {
        $instance = $this->make_instance([
            'ratelimit_local_coursegen_credit_default' => 5,
            'ratelimit_local_jedomi_credit_default' => 5,
        ]);

        $this->assertSame(2000, $instance->get_credit_for_action('local_coursegen', 'course_image'));
        $this->assertSame(50, $instance->get_credit_for_action('local_coursegen', 'activity_noimage'));
        $this->assertSame(5, $instance->get_credit_for_action('local_coursegen', 'unknown'));
        foreach (['chat', 'speech', 'transcription', 'file_read'] as $key) {
            $this->assertSame(5, $instance->get_credit_for_action('local_jedomi', $key), $key);
        }
    }

    /**
     * An action Jedomi does not list (or 'default') costs the first catalog value when nothing is saved.
     */
    public function test_unknown_jedomi_action_falls_back_to_the_catalog(): void {
        $instance = $this->make_instance([]);

        $this->assertSame(2, $instance->get_credit_for_action('local_jedomi'));
        $this->assertSame(2, $instance->get_credit_for_action('local_jedomi', 'unknown'));
    }

    /**
     * The labels of the Jedomi actions exist in every language of the plugin.
     */
    public function test_jedomi_action_labels_exist_in_every_language(): void {
        global $CFG;

        $ids = ['generate_jedomi_chat', 'generate_jedomi_speech', 'transcribe_jedomi_audio', 'read_jedomi_file'];
        $files = glob($CFG->dirroot . '/ai/provider/datacurso/lang/*/aiprovider_datacurso.php');
        $this->assertCount(7, $files);
        foreach ($files as $file) {
            $string = [];
            include($file);
            foreach ($ids as $id) {
                $this->assertNotEmpty($string[$id] ?? '', basename(dirname($file)) . ': ' . $id);
            }
        }
    }

    /**
     * The Jedomi chat endpoint is a known action with a readable label.
     *
     * J3: Jedomi action label for the consumption history.
     */
    public function test_get_actions_includes_jedomi_chat(): void {
        $actionmap = array_column(provider::get_actions(), 'name', 'id');

        $this->assertArrayHasKey('/jedomi/v1/chat/completions', $actionmap);
        $this->assertSame(
            get_string('generate_jedomi_chat', 'aiprovider_datacurso'),
            $actionmap['/jedomi/v1/chat/completions']
        );
    }

    /**
     * Every billed Jedomi route has its own readable label, so the consumption history never shows a raw path.
     *
     * The text extraction route (/jedomi/v1/files/extract) is not billed, so it never appears in the history.
     */
    public function test_get_actions_labels_every_billed_jedomi_route(): void {
        $actionmap = array_column(provider::get_actions(), 'name', 'id');
        $expected = [
            '/jedomi/v1/chat/completions' => 'generate_jedomi_chat',
            '/jedomi/v1/audio/speech' => 'generate_jedomi_speech',
            '/jedomi/v1/audio/transcriptions' => 'transcribe_jedomi_audio',
            '/jedomi/v1/files/read' => 'read_jedomi_file',
        ];

        foreach ($expected as $path => $stringid) {
            $this->assertArrayHasKey($path, $actionmap);
            $this->assertSame(get_string($stringid, 'aiprovider_datacurso'), $actionmap[$path]);
            $this->assertStringNotContainsString('/jedomi/', $actionmap[$path]);
        }
        $this->assertSame('Generate Jedomi voice answer', $actionmap['/jedomi/v1/audio/speech']);
        $this->assertArrayNotHasKey('/jedomi/v1/files/extract', $actionmap);
    }

    /**
     * The instance-scoped flat key `ratelimit_{sid}_credit_{actionkey}` wins over the
     * catalogue default when present.
     */
    public function test_get_credit_for_action_reads_instance_config(): void {
        $instance = $this->make_instance([
            'ratelimit_local_coursegen_credit_course_image' => 777,
        ]);

        $this->assertSame(777, $instance->get_credit_for_action('local_coursegen', 'course_image'));
    }

    /**
     * With no instance-config key set, the catalogue default for the action wins.
     */
    public function test_get_credit_for_action_falls_back_to_the_catalogue_default(): void {
        $instance = $this->make_instance([]);

        // Catalogue default for local_coursegen/course_image (provider::get_service_actions()).
        $this->assertSame(2000, $instance->get_credit_for_action('local_coursegen', 'course_image'));
    }

    /**
     * A zero or negative configured value never yields fewer than 1 credit.
     */
    public function test_get_credit_for_action_is_never_below_one(): void {
        $zero = $this->make_instance([
            'ratelimit_local_datacurso_ratings_credit_default' => 0,
        ]);
        $this->assertSame(1, $zero->get_credit_for_action('local_datacurso_ratings', 'default'));

        $negative = $this->make_instance([
            'ratelimit_local_datacurso_ratings_credit_default' => -5,
        ]);
        $this->assertSame(1, $negative->get_credit_for_action('local_datacurso_ratings', 'default'));
    }

    /**
     * get_credit_for_action() must be a public, non-static instance method: it reads
     * $this->config, which is only populated on a constructed provider instance.
     */
    public function test_get_credit_for_action_is_an_instance_method(): void {
        $method = new \ReflectionMethod(provider::class, 'get_credit_for_action');

        $this->assertFalse($method->isStatic());
        $this->assertTrue($method->isPublic());
    }

    /**
     * The default window limit is the most expensive action per catalogued service, else 10.
     *
     * Ported from 4.5's tests/provider_catalog_test.php::test_default_window_limit.
     */
    public function test_default_window_limit_catalogue_values(): void {
        $this->assertSame(30, provider::get_default_window_limit('aiprovider_datacurso'));
        $this->assertSame(2000, provider::get_default_window_limit('local_coursegen'));
        $this->assertSame(10, provider::get_default_window_limit('service_without_catalog'));
    }

    /**
     * Registering Jedomi leaves the other services and their action catalogs untouched.
     *
     * J7: no regression on the existing services.
     */
    public function test_existing_services_and_catalogs_are_unchanged(): void {
        $serviceids = array_column(provider::get_services(), 'id');
        $this->assertSame([
            'local_coursegen',
            'local_datacurso_ratings',
            'local_forum_ai',
            'local_assign_ai',
            'aiprovider_datacurso',
            'local_dttutor',
            'local_socialcert',
            'report_lifestory',
            'local_coursedynamicrules',
        ], array_values(array_diff($serviceids, ['local_jedomi'])));

        $catalog = [];
        foreach (provider::get_service_actions() as $serviceid => $actions) {
            if ($serviceid === 'local_jedomi') {
                continue;
            }
            $catalog[$serviceid] = array_column($actions, 'default', 'key');
        }
        $this->assertSame([
            'local_coursegen' => [
                'course_image' => 2000,
                'course_noimage' => 1000,
                'activity_image' => 100,
                'activity_noimage' => 50,
            ],
            'aiprovider_datacurso' => ['text' => 1, 'image' => 30],
            'local_coursedynamicrules' => ['activity_image' => 100, 'activity_noimage' => 50],
            'local_datacurso_ratings' => ['default' => 1],
            'local_socialcert' => ['default' => 1],
            'local_forum_ai' => ['default' => 3],
            'report_lifestory' => ['default' => 5],
            'local_assign_ai' => ['default' => 3],
            'local_dttutor' => ['default' => 2],
        ], $catalog);
    }
}
