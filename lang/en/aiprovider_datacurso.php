<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     aiprovider_datacurso
 * @category    string
 * @copyright   Josue <josue@datacurso.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action'] = 'Action';
$string['action:explain_text:endpoint'] = 'Explain text endpoint';
$string['action:explain_text:model'] = 'Model for explanation';
$string['action:explain_text:model_help'] = 'Select the model that will generate explanations.';
$string['action:explain_text:systeminstruction'] = 'System instruction for explanation';
$string['action:explain_text:systeminstruction_help'] = 'Provide context to guide the explanation process.';
$string['action:generate_image:endpoint'] = 'API endpoint';
$string['action:generate_image:endpoint_desc'] = 'There endpoint the generate image';
$string['action:generate_image:model'] = 'Model for image generation';
$string['action:generate_image:model_help'] = 'Select the AI model to generate images.';
$string['action:generate_image:systeminstruction'] = 'System prompt for image generation';
$string['action:generate_image:systeminstruction_help'] = 'Additional instructions that guide the AI in generating the desired image.';
$string['action:generate_text:endpoint'] = 'API endpoint';
$string['action:generate_text:endpoint_desc'] = 'There endpoint the generate text';
$string['action:generate_text:instruction'] = 'System instruction';
$string['action:generate_text:instruction_desc'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing this instruction is not recommended unless absolutely required.';
$string['action:generate_text:model'] = 'Model for text generation';
$string['action:generate_text:model_help'] = 'Select which AI model will be used to generate the text.';
$string['action:generate_text:systeminstruction'] = 'System instruction';
$string['action:generate_text:systeminstruction_help'] = 'Instruction or context given to the AI before generating the text. Useful for controlling tone, structure, or purpose of the response.';
$string['action:summarise_text:endpoint'] = 'API endpoint';
$string['action:summarise_text:endpoint_desc'] = 'There endpoint the generate text';
$string['action:summarise_text:instruction'] = 'System instruction';
$string['action:summarise_text:instruction_desc'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing this instruction is not recommended unless absolutely required.';
$string['action:summarise_text:model'] = 'Model for summarisation';
$string['action:summarise_text:model_help'] = 'Select which AI model will be used to summarise the text.';
$string['action:summarise_text:systeminstruction'] = 'System instruction for summary';
$string['action:summarise_text:systeminstruction_help'] = 'Optional context to influence how the summary is generated.';
$string['action_activity_image'] = 'Activity with image';
$string['action_activity_noimage'] = 'Activity without image';
$string['action_course_image'] = 'Course with image';
$string['action_course_noimage'] = 'Course without image';
$string['action_default'] = 'Credits per action';
$string['action_image'] = 'Generate image';
$string['action_text'] = 'Generate text / summary';
$string['all'] = 'All';
$string['alt_datacurso_icon'] = 'Datacurso icon';
$string['chart_actions'] = 'Credits distribution by service';
$string['chart_tokens_by_day'] = 'Credits consumption by day';
$string['chart_tokens_by_month'] = 'Number of credits consumed per month';
$string['chart_user_consumption'] = 'Credits consumed by user per service';
$string['connection'] = 'Connection settings';
$string['create_activity_assign_image'] = 'Create assignment with AI (with images)';
$string['create_activity_assign_noimage'] = 'Create assignment with AI (without images)';
$string['create_activity_book_image'] = 'Create book with AI (with images)';
$string['create_activity_book_noimage'] = 'Create book with AI (without images)';
$string['create_activity_choice_image'] = 'Create choice poll with AI (with images)';
$string['create_activity_choice_noimage'] = 'Create choice poll with AI (without images)';
$string['create_activity_data_image'] = 'Create database with AI (with images)';
$string['create_activity_data_noimage'] = 'Create database with AI (without images)';
$string['create_activity_feedback_image'] = 'Create feedback survey with AI (with images)';
$string['create_activity_feedback_noimage'] = 'Create feedback survey with AI (without images)';
$string['create_activity_folder_image'] = 'Create folder with AI (with images)';
$string['create_activity_folder_noimage'] = 'Create folder with AI (without images)';
$string['create_activity_forum_image'] = 'Create forum with AI (with images)';
$string['create_activity_forum_noimage'] = 'Create forum with AI (without images)';
$string['create_activity_glossary_image'] = 'Create glossary with AI (with images)';
$string['create_activity_glossary_noimage'] = 'Create glossary with AI (without images)';
$string['create_activity_h5pactivity_image'] = 'Create H5P activity with AI (with images)';
$string['create_activity_h5pactivity_noimage'] = 'Create H5P activity with AI (without images)';
$string['create_activity_imscp_image'] = 'Create IMS package with AI (with images)';
$string['create_activity_imscp_noimage'] = 'Create IMS package with AI (without images)';
$string['create_activity_label_image'] = 'Create label with AI (with images)';
$string['create_activity_label_noimage'] = 'Create label with AI (without images)';
$string['create_activity_lesson_image'] = 'Create lesson with AI (with images)';
$string['create_activity_lesson_noimage'] = 'Create lesson with AI (without images)';
$string['create_activity_page_image'] = 'Create page with AI (with images)';
$string['create_activity_page_noimage'] = 'Create page with AI (without images)';
$string['create_activity_quiz_image'] = 'Create quiz with AI (with images)';
$string['create_activity_quiz_noimage'] = 'Create quiz with AI (without images)';
$string['create_activity_resource_image'] = 'Create file/resource with AI (with images)';
$string['create_activity_resource_noimage'] = 'Create file/resource with AI (without images)';
$string['create_activity_scorm_image'] = 'Create SCORM package with AI (with images)';
$string['create_activity_scorm_noimage'] = 'Create SCORM package with AI (without images)';
$string['create_activity_url_image'] = 'Create URL with AI (with images)';
$string['create_activity_url_noimage'] = 'Create URL with AI (without images)';
$string['create_activity_wiki_image'] = 'Create wiki with AI (with images)';
$string['create_activity_wiki_noimage'] = 'Create wiki with AI (without images)';
$string['create_activity_workshop_image'] = 'Create workshop with AI (with images)';
$string['create_activity_workshop_noimage'] = 'Create workshop with AI (without images)';
$string['curlerror'] = 'Datacurso API cURL error: {$a}';
$string['custom_model_name'] = 'Custom model name';
$string['custom_model_name_help'] = 'Optional name to identify this specific AI model configuration.';
$string['datacurso:manage'] = 'Manage AI provider settings';
$string['datacurso:use'] = 'Use Datacurso AI services';
$string['datacurso:viewreports'] = 'View AI usage reports';
$string['day'] = 'day';
$string['days'] = 'Days';
$string['description'] = 'Description';
$string['descriptionpagelistplugins'] = 'Here you can find the list of plugins compatible with the Datacurso provider';
$string['emptyprompt'] = 'Empty prompt';
$string['emptyresponse'] = 'No response from Datacurso API.';
$string['endpointurl'] = 'Endpoint URL';
$string['endpointurl_help'] = 'Base API endpoint URL of the Datacurso AI Provider. Usually something like https://api.datacurso.ai/v1/.';
$string['entity_consumption'] = 'Consumption';
$string['error_ratelimit_exceeded'] = 'The allowed consumption limit has been exceeded. Please try again at {$a}.';
$string['errorgetbalancecredits'] = 'Could not retrieve credits balance from external API';
$string['filter_year'] = 'Year';
$string['forbidden'] = 'You are not allowed to perform this action with the current license. Please verify your license and available credits in <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Manage Credits</a> in the Datacurso Shop.';
$string['generate_activitie'] = 'Generate activity or resource with AI';
$string['generate_ai_reinforcement_activity'] = 'Create AI reinforcement activity';
$string['generate_analysis_comments'] = 'Generate rating analysis of an activity/resource with AI';
$string['generate_analysis_course'] = 'Generate course rating analysis with AI';
$string['generate_analysis_general'] = 'Generate general rating analysis with AI';
$string['generate_analysis_story_student'] = 'Generate analysis story student with AI';
$string['generate_assign_answer'] = 'Generate assignment review with AI';
$string['generate_certificate_answer'] = 'Generate certificate message with AI';
$string['generate_chat_embeddings'] = 'AI conversation history';
$string['generate_chat_message'] = 'Generate tutor AI message';
$string['generate_chat_stream'] = 'AI response';
$string['generate_creation_course'] = 'Create complete course with AI';
$string['generate_creation_course_image'] = 'Create full course with AI (with images)';
$string['generate_creation_course_noimage'] = 'Create full course with AI (without images)';
$string['generate_forum_chat'] = 'Generate forum response with AI';
$string['generate_forum_grade'] = 'Grade forum with AI';
$string['generate_image'] = 'Generate image with AI';
$string['generate_plan_course'] = 'Generate course creation plan with AI';
$string['generate_summary'] = 'Generate summary with AI';
$string['generate_text'] = 'Generate text with AI';
$string['goto'] = 'Go to Report';
$string['hour'] = 'hour';
$string['hours'] = 'Hours';
$string['httperror'] = 'Unexpected error while processing your request (HTTP {$a}). Please try again later. If the problem persists, contact your site administrator.';
$string['id'] = 'ID';
$string['installed'] = 'Installed';
$string['instance_disabled'] = 'The Datacurso provider instance is disabled';
$string['invalidjson'] = 'JSON Invalid';
$string['invalidlicensekey'] = 'License key has expired or is invalid. Please go to <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Manage Credits</a> in the Datacurso Shop to renew or purchase a new license.';
$string['jsondecodeerror'] = 'Error processing response from Datacurso API: {$a}';
$string['license_not_allowed'] = 'Your license is not allowed to perform this request. Please manage your licenses and credits in <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Manage Credits</a> in the Datacurso Shop.';
$string['licensekey'] = 'License key';
$string['licensekey_help'] = 'Enter your Datacurso AI provider license key.';
$string['licensekey_missing'] = 'The license key is not configured';
$string['link_consumptionhistory'] = 'Credits consumption history';
$string['link_generalreport'] = 'General report';
$string['link_generalreport_datacurso'] = 'General report Datacurso AI';
$string['link_listplugings'] = 'Datacurso plugins list';
$string['link_plugin'] = 'Link';
$string['link_provider_config'] = 'Provider configuration';
$string['link_report_statistic'] = 'General statistics report';
$string['message_no_there_plugins'] = 'No plugins available';
$string['minute'] = 'minute';
$string['minutes'] = 'Minutes';
$string['month'] = 'month';
$string['months'] = 'Months';
$string['notenoughtokens'] = 'Insufficient AI credits. Please visit <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Manage Credits</a> in the Datacurso Shop to allocate or purchase more credits. Or contact your administrator.';
$string['of'] = 'of';
$string['plugin'] = 'Plugin';
$string['plugindesc_assign_ai'] = 'Review assignments with AI assistance.';
$string['plugindesc_coursegen'] = 'Create complete courses, activities, and resources with AI.';
$string['plugindesc_datacurso_ratings'] = 'Allows students to rate activities and resources; teachers and administrators can later generate AI-based course analysis.';
$string['plugindesc_dttutor'] = 'Chat with an AI tutor within the course.';
$string['plugindesc_forum_ai'] = 'Extend forums with AI-powered analysis to automatically generate summaries.';
$string['plugindesc_lifestory'] = 'AI-powered report and analysis of the student’s academic progress.';
$string['plugindesc_smartrules'] = 'Create automated activities based on students’ previous conditions.';
$string['plugindesc_socialcert'] = 'Automatically generate personalized certificates upon course completion.';
$string['pluginname'] = 'Datacurso AI Provider';
$string['pluginname_assign_ai'] = 'Assign AI';
$string['pluginname_coursegen'] = 'Course Creator AI';
$string['pluginname_datacurso_ratings'] = 'Ranking Activities AI';
$string['pluginname_dttutor'] = 'Tutor AI';
$string['pluginname_forum_ai'] = 'Forum AI';
$string['pluginname_lifestory'] = 'Student Life Story AI';
$string['pluginname_smartrules'] = 'SmartRules AI';
$string['pluginname_socialcert'] = 'Share Certificate AI';
$string['privacy:metadata'] = 'The Datacurso AI Provider plugin does not store any personal data locally. All data is processed by external Datacurso AI services.';
$string['privacy:metadata:aiprovider_datacurso'] = 'Datacurso AI request payloads sent to the external service.';
$string['privacy:metadata:aiprovider_datacurso:externalpurpose'] = 'This data is sent to Datacurso AI in order to fulfil the requested action.';
$string['privacy:metadata:aiprovider_datacurso:numberimages'] = 'Total number of images requested from the AI service.';
$string['privacy:metadata:aiprovider_datacurso:prompt'] = 'The prompt text supplied to the AI service.';
$string['privacy:metadata:aiprovider_datacurso:userid'] = 'The Moodle user ID making the AI request.';
$string['privacy:metadata:aiprovider_datacurso_consumption'] = 'Local mirror of the external credit consumption history, synced on demand for reporting.';
$string['privacy:metadata:aiprovider_datacurso_consumption:action'] = 'Action identifier.';
$string['privacy:metadata:aiprovider_datacurso_consumption:balance'] = 'Remaining balance after the consumption.';
$string['privacy:metadata:aiprovider_datacurso_consumption:credits'] = 'Credits consumed.';
$string['privacy:metadata:aiprovider_datacurso_consumption:service'] = 'Service identifier (e.g. local_coursegen).';
$string['privacy:metadata:aiprovider_datacurso_consumption:timecreated'] = 'Consumption timestamp.';
$string['privacy:metadata:aiprovider_datacurso_consumption:userid'] = 'The Moodle user who triggered the consumption.';
$string['privacy:metadata:aiprovider_datacurso_rlimit'] = 'Per-user per-service rate limit rolling usage state stored locally.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:lastsync'] = 'Last sync timestamp with the remote history.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:serviceid'] = 'Service identifier (e.g. local_coursegen).';
$string['privacy:metadata:aiprovider_datacurso_rlimit:timecreated'] = 'Time when this record was created.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:timemodified'] = 'Time when this record was last modified.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:tokensused'] = 'Credits used within the current time window.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:userid'] = 'User ID related to the tracked consumption window.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:windowstart'] = 'Window start timestamp used to compute consumption limits.';
$string['privacy:metadata:aiprovider_datacurso_userlimit'] = 'Per-user Datacurso token quotas stored locally.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:countfrom'] = 'Timestamp marking when the quota started tracking usage.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:lastsync'] = 'Last time the usage information was synchronised.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:timecreated'] = 'Time when the quota record was created.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:timemodified'] = 'Time when the quota record was last updated.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:tokenlimit'] = 'Maximum number of tokens granted to the user.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:tokensused'] = 'Tokens consumed since tracking started.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:userid'] = 'User ID associated with the quota.';
$string['ratelimit_creditperaction'] = 'Credits per action';
$string['ratelimit_creditperaction_desc'] = 'Estimated maximum credits a single action of this plugin can cost. A request is blocked upfront when the credits remaining in the window are fewer than this value.';
$string['ratelimit_creditperaction_help'] = 'Estimated maximum credits a single action of this plugin can cost. A request is blocked upfront when the credits remaining in the window are fewer than this value.';
$string['ratelimit_enable'] = 'Enable rate limit';
$string['ratelimit_enable_desc'] = 'If enabled, this plugin enforces per-user credit limits. Access control remains managed by Moodle permissions.';
$string['ratelimit_limit'] = 'Credit limit per window';
$string['ratelimit_limit_help'] = 'Maximum number of credits a user can consume within the selected time window. 0 for unlimited.';
$string['ratelimit_window'] = 'Time window';
$string['ratelimit_window_help'] = 'Select the duration and unit for the rate limit window.';
$string['ratelimit_window_unit'] = 'Window unit';
$string['ratelimit_window_value'] = 'Window value';
$string['read_context_course'] = 'Read context for AI course creation';
$string['read_context_course_model'] = 'Upload academic model for AI course creation';
$string['remainingtokens'] = 'Remaining balance';
$string['responseinvalidai'] = 'Invalid response from AI service.';
$string['responseinvalidaimage'] = 'Invalid response from AI service(No image).';
$string['responseinvalidaimagecreate'] = 'Could not create image file.';
$string['second'] = 'second';
$string['seconds'] = 'Seconds';
$string['service'] = 'Service';
$string['tokens'] = 'Credits';
$string['tokens_available'] = 'Available Credits';
$string['tokensconsumed'] = 'Credits consumed';
$string['tokensconsumedday'] = 'Credits consumed by day';
$string['tokensconsumedmonth'] = 'Credits consumed by month';
$string['tokensused'] = 'Credits used';
$string['total_consumed'] = 'Credits consumed';
$string['total_user_consumed'] = 'Total credits consumed by user';
$string['userid'] = 'User';
$string['warningconfig_instance'] = 'Warning: Only one instance should be created with this provider for proper use.';
$string['week'] = 'week';
$string['weeks'] = 'Weeks';
$string['year'] = 'year';
$string['years'] = 'Years';
