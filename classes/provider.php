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

use core_ai\form\action_settings_form;
use Psr\Http\Message\RequestInterface;

/**
 * Datacurso AI provider for Moodle 5.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends \core_ai\provider {
    /**
     * Returns the list of AI actions supported by this provider.
     *
     * @return array
     */
    public static function get_action_list(): array {
        return [
            \core_ai\aiactions\generate_text::class,
            \core_ai\aiactions\generate_image::class,
            \core_ai\aiactions\summarise_text::class,
            \core_ai\aiactions\explain_text::class,
        ];
    }

    /**
     * Add authentication headers to a request.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @return \Psr\Http\Message\RequestInterface
     */
    public function add_authentication_headers(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\RequestInterface {
        return $request->withAddedHeader('Authorization', "Bearer {$this->config['licensekey']}");
    }

    /**
     * Returns the form used to configure specific action settings.
     *
     * @param string $action
     * @param array $customdata
     * @return action_settings_form|bool
     */
    #[\Override]
    public static function get_action_settings(string $action, array $customdata = []): action_settings_form|bool {
        $actionname = substr($action, (strrpos($action, '\\') + 1));
        $customdata['actionname'] = $actionname;
        $customdata['action'] = $action;

        if (in_array($actionname, ['generate_text', 'summarise_text', 'explain_text'])) {
            return new form\action_generate_text_form(customdata: $customdata);
        } else if ($actionname === 'generate_image') {
            return false;
        }

        return false;
    }


    /**
     * Checks if this provider has the minimal configuration (license key set).
     *
     * @return bool
     */
    public function is_provider_configured(): bool {
        return !empty($this->config['licensekey']);
    }

    /**
     * Return all available AI services for this provider.
     *
     * @return array
     */
    public static function get_services(): array {
        return [
            ['id' => 'local_coursegen', 'name' => get_string('pluginname_coursegen', 'aiprovider_datacurso')],
            ['id' => 'local_datacurso_ratings', 'name' => get_string('pluginname_datacurso_ratings', 'aiprovider_datacurso')],
            ['id' => 'local_forum_ai', 'name' => get_string('pluginname_forum_ai', 'aiprovider_datacurso')],
            ['id' => 'local_assign_ai', 'name' => get_string('pluginname_assign_ai', 'aiprovider_datacurso')],
            ['id' => 'aiprovider_datacurso', 'name' => get_string('pluginname', 'aiprovider_datacurso')],
            ['id' => 'local_dttutor', 'name' => get_string('pluginname_dttutor', 'aiprovider_datacurso')],
            ['id' => 'local_socialcert', 'name' => get_string('pluginname_socialcert', 'aiprovider_datacurso')],
            ['id' => 'report_lifestory', 'name' => get_string('pluginname_lifestory', 'aiprovider_datacurso')],
            ['id' => 'local_coursedynamicrules', 'name' => get_string('pluginname_smartrules', 'aiprovider_datacurso')],
        ];
    }

    /**
     * Per-service "credits per action" catalog.
     *
     * Some services have several sub-actions with their own credit cost (e.g. the course creator).
     * Each entry is {key, name (lang string), default}. Single-action services expose one 'default'.
     * Drives the configuration UI and the flat instance-config keys
     * ratelimit_{service}_credit_{actionkey} (see get_credit_for_action()).
     *
     * @return array<string, array<int, array{key: string, name: \lang_string|string, default: int}>>
     */
    public static function get_service_actions(): array {
        $s = static fn(string $id): \lang_string => new \lang_string($id, 'aiprovider_datacurso');
        return [
            'local_coursegen' => [
                ['key' => 'course_image', 'name' => $s('action_course_image'), 'default' => 2000],
                ['key' => 'course_noimage', 'name' => $s('action_course_noimage'), 'default' => 1000],
                ['key' => 'activity_image', 'name' => $s('action_activity_image'), 'default' => 100],
                ['key' => 'activity_noimage', 'name' => $s('action_activity_noimage'), 'default' => 50],
            ],
            'aiprovider_datacurso' => [
                ['key' => 'text', 'name' => $s('action_text'), 'default' => 1],
                ['key' => 'image', 'name' => $s('action_image'), 'default' => 30],
            ],
            'local_coursedynamicrules' => [
                ['key' => 'activity_image', 'name' => $s('action_activity_image'), 'default' => 100],
                ['key' => 'activity_noimage', 'name' => $s('action_activity_noimage'), 'default' => 50],
            ],
            'local_datacurso_ratings' => [
                ['key' => 'default', 'name' => $s('action_default'), 'default' => 1],
            ],
            'local_socialcert' => [
                ['key' => 'default', 'name' => $s('action_default'), 'default' => 1],
            ],
            'local_forum_ai' => [
                ['key' => 'default', 'name' => $s('action_default'), 'default' => 3],
            ],
            'report_lifestory' => [
                ['key' => 'default', 'name' => $s('action_default'), 'default' => 5],
            ],
            'local_assign_ai' => [
                ['key' => 'default', 'name' => $s('action_default'), 'default' => 3],
            ],
            'local_dttutor' => [
                ['key' => 'default', 'name' => $s('action_default'), 'default' => 2],
            ],
        ];
    }

    /**
     * Return the credits-per-action catalog for a single service (empty if none).
     *
     * @param string $serviceid
     * @return array<int, array{key: string, name: \lang_string|string, default: int}>
     */
    public static function get_actions_for_service(string $serviceid): array {
        return self::get_service_actions()[$serviceid] ?? [
            ['key' => 'default', 'name' => new \lang_string('action_default', 'aiprovider_datacurso'), 'default' => 10],
        ];
    }

    /**
     * Default per-window credit limit prefilled in the configuration form for a service.
     *
     * Set to the most expensive action a normal user can trigger for the service
     * (e.g. an image on the provider, a course with image on the course creator).
     *
     * @param string $serviceid
     * @return int
     */
    public static function get_default_window_limit(string $serviceid): int {
        $defaults = [
            'aiprovider_datacurso' => 30,
            'local_coursegen' => 2000,
        ];
        return $defaults[$serviceid] ?? 10;
    }

    /**
     * Resolve the configured (worst-case) credit for a sub-action of a service.
     *
     * Reads the instance-scoped flat key `ratelimit_{sid}_credit_{actionkey}` from
     * $this->config (never from global plugin config: settings live per provider instance).
     * Falls back to the catalog default for the key, then to the service's 'default'
     * action, then to 1. This is the look-ahead value sent as X-RateLimit-MaxPerAction
     * so the token-manager can block before overshooting the limit.
     *
     * @param string $serviceid Service identifier such as 'local_coursegen'.
     * @param string $actionkey Sub-action key such as 'course_image' (defaults to 'default').
     * @return int Estimated credits for this action (>= 1).
     */
    public function get_credit_for_action(string $serviceid, string $actionkey = 'default'): int {
        $actions = self::get_actions_for_service($serviceid);

        // Catalog defaults keyed by action, for fallback.
        $defaults = [];
        foreach ($actions as $action) {
            $defaults[$action['key']] = (int) $action['default'];
        }

        // Prefer the requested key; fall back to 'default'; then to any first action; then 1.
        foreach ([$actionkey, 'default'] as $key) {
            if (isset($this->config["ratelimit_{$serviceid}_credit_{$key}"])) {
                return max(1, (int) $this->config["ratelimit_{$serviceid}_credit_{$key}"]);
            }
            if (isset($defaults[$key])) {
                return max(1, $defaults[$key]);
            }
        }

        return $defaults ? max(1, (int) reset($defaults)) : 1;
    }

    /**
     * Return all available AI actions for this provider.
     *
     * @return array
     */
    public static function get_actions(): array {
        // Activity-type actions share the same id and language-string key.
        $act = static fn(string $id): array => ['id' => $id, 'name' => get_string($id, 'aiprovider_datacurso')];

        return [
            ['id' => '/provider/chat/completions', 'name' => get_string('generate_text', 'aiprovider_datacurso')],
            ['id' => '/provider/images/generations', 'name' => get_string('generate_image', 'aiprovider_datacurso')],
            ['id' => '/course/execute', 'name' => get_string('generate_creation_course', 'aiprovider_datacurso')],
            ['id' => '/course/execute_image', 'name' => get_string('generate_creation_course_image', 'aiprovider_datacurso')],
            ['id' => '/course/execute_noimage', 'name' => get_string('generate_creation_course_noimage', 'aiprovider_datacurso')],
            ['id' => '/course/start', 'name' => get_string('generate_plan_course', 'aiprovider_datacurso')],
            ['id' => '/resources/create-mod', 'name' => get_string('generate_activitie', 'aiprovider_datacurso')],
            ['id' => '/assign/answer', 'name' => get_string('generate_assign_answer', 'aiprovider_datacurso')],
            ['id' => '/forum/chat', 'name' => get_string('generate_forum_chat', 'aiprovider_datacurso')],
            ['id' => '/forum/grade', 'name' => get_string('generate_forum_grade', 'aiprovider_datacurso')],
            ['id' => '/chat/stream', 'name' => get_string('generate_chat_stream', 'aiprovider_datacurso')],
            ['id' => '/chat/embeddings', 'name' => get_string('generate_chat_embeddings', 'aiprovider_datacurso')],
            ['id' => '/rating/general', 'name' => get_string('generate_analysis_general', 'aiprovider_datacurso')],
            ['id' => '/rating/course', 'name' => get_string('generate_analysis_course', 'aiprovider_datacurso')],
            ['id' => '/rating/query', 'name' => get_string('generate_analysis_comments', 'aiprovider_datacurso')],
            ['id' => '/context/upload', 'name' => get_string('read_context_course', 'aiprovider_datacurso')],
            ['id' => '/context/upload-model-context', 'name' => get_string('read_context_course_model', 'aiprovider_datacurso')],
            ['id' => '/resources/create-mod/stream', 'name' => get_string('generate_activitie', 'aiprovider_datacurso')],
            ['id' => '/certificate/answer', 'name' => get_string('generate_certificate_answer', 'aiprovider_datacurso')],
            ['id' => '/story/analysis', 'name' => get_string('generate_analysis_story_student', 'aiprovider_datacurso')],
            ['id' => '/smartrules/create-mod', 'name' => get_string('generate_ai_reinforcement_activity', 'aiprovider_datacurso')],
            ['id' => '/chat/message', 'name' => get_string('generate_chat_message', 'aiprovider_datacurso')],
            // Moodle activity types.
            $act('create_activity_assign_image'),
            $act('create_activity_assign_noimage'),
            $act('create_activity_quiz_image'),
            $act('create_activity_quiz_noimage'),
            $act('create_activity_lesson_image'),
            $act('create_activity_lesson_noimage'),
            $act('create_activity_workshop_image'),
            $act('create_activity_workshop_noimage'),
            $act('create_activity_h5pactivity_image'),
            $act('create_activity_h5pactivity_noimage'),
            $act('create_activity_scorm_image'),
            $act('create_activity_scorm_noimage'),
            $act('create_activity_feedback_image'),
            $act('create_activity_feedback_noimage'),
            $act('create_activity_choice_image'),
            $act('create_activity_choice_noimage'),
            $act('create_activity_data_image'),
            $act('create_activity_data_noimage'),
            $act('create_activity_book_image'),
            $act('create_activity_book_noimage'),
            $act('create_activity_page_image'),
            $act('create_activity_page_noimage'),
            $act('create_activity_resource_image'),
            $act('create_activity_resource_noimage'),
            $act('create_activity_url_image'),
            $act('create_activity_url_noimage'),
            $act('create_activity_folder_image'),
            $act('create_activity_folder_noimage'),
            $act('create_activity_label_image'),
            $act('create_activity_label_noimage'),
            $act('create_activity_imscp_image'),
            $act('create_activity_imscp_noimage'),
            $act('create_activity_forum_image'),
            $act('create_activity_forum_noimage'),
            $act('create_activity_glossary_image'),
            $act('create_activity_glossary_noimage'),
            $act('create_activity_wiki_image'),
            $act('create_activity_wiki_noimage'),
        ];
    }
}
