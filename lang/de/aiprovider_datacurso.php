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

$string['action'] = 'Aktion';
$string['action:explain_text:endpoint'] = 'Endpunkt für Texterklärung';
$string['action:explain_text:model'] = 'Modell für die Erklärung';
$string['action:explain_text:model_help'] = 'Wählen Sie das Modell aus, das Erklärungen generieren wird.';
$string['action:explain_text:systeminstruction'] = 'Systemanweisung für die Erklärung';
$string['action:explain_text:systeminstruction_help'] = 'Stellen Sie Kontext bereit, um den Erklärungsprozess zu steuern.';
$string['action:generate_image:endpoint'] = 'API-Endpunkt';
$string['action:generate_image:endpoint_desc'] = 'Der Endpunkt zum Generieren von Bildern';
$string['action:generate_image:model'] = 'Modell für die Bilderzeugung';
$string['action:generate_image:model_help'] = 'Wählen Sie das KI-Modell zur Bilderzeugung.';
$string['action:generate_image:systeminstruction'] = 'System-Prompt für die Bilderzeugung';
$string['action:generate_image:systeminstruction_help'] = 'Zusätzliche Anweisungen, die die KI bei der Erzeugung des gewünschten Bildes leiten.';
$string['action:generate_text:endpoint'] = 'API-Endpunkt';
$string['action:generate_text:endpoint_desc'] = 'Der Endpunkt zum Generieren von Text';
$string['action:generate_text:instruction'] = 'Systemanweisung';
$string['action:generate_text:instruction_desc'] = 'Diese Anweisung wird zusammen mit der Benutzereingabe an das KI-Modell gesendet. Das Bearbeiten dieser Anweisung wird nicht empfohlen, es sei denn, es ist unbedingt erforderlich.';
$string['action:generate_text:model'] = 'Modell für die Texterzeugung';
$string['action:generate_text:model_help'] = 'Wählen Sie das KI-Modell aus, das zur Texterzeugung verwendet wird.';
$string['action:generate_text:systeminstruction'] = 'Systemanweisung';
$string['action:generate_text:systeminstruction_help'] = 'Anweisung oder Kontext, der der KI vor der Texterzeugung gegeben wird. Nützlich zur Steuerung von Ton, Struktur oder Zweck der Antwort.';
$string['action:summarise_text:endpoint'] = 'API-Endpunkt';
$string['action:summarise_text:endpoint_desc'] = 'Der Endpunkt zum Generieren von Text';
$string['action:summarise_text:instruction'] = 'Systemanweisung';
$string['action:summarise_text:instruction_desc'] = 'Diese Anweisung wird zusammen mit der Benutzereingabe an das KI-Modell gesendet. Das Bearbeiten dieser Anweisung wird nicht empfohlen, es sei denn, es ist unbedingt erforderlich.';
$string['action:summarise_text:model'] = 'Modell für die Zusammenfassung';
$string['action:summarise_text:model_help'] = 'Wählen Sie das KI-Modell aus, das zur Textzusammenfassung verwendet wird.';
$string['action:summarise_text:systeminstruction'] = 'Systemanweisung für die Zusammenfassung';
$string['action:summarise_text:systeminstruction_help'] = 'Optionaler Kontext, um zu beeinflussen, wie die Zusammenfassung generiert wird.';
$string['action_activity_image'] = 'Activity with image';
$string['action_activity_noimage'] = 'Activity without image';
$string['action_course_image'] = 'Course with image';
$string['action_course_noimage'] = 'Course without image';
$string['action_default'] = 'Credits per action';
$string['action_image'] = 'Generate image';
$string['action_text'] = 'Generate text / summary';
$string['all'] = 'Alle';
$string['alt_datacurso_icon'] = 'Datacurso-Symbol';
$string['chart_actions'] = 'Kreditverteilung nach Service';
$string['chart_tokens_by_day'] = 'Kreditverbrauch nach Tag';
$string['chart_tokens_by_month'] = 'Anzahl der pro Monat verbrauchten Kredite';
$string['chart_user_consumption'] = 'Credits consumed by user per service';
$string['connection'] = 'Verbindungseinstellungen';
$string['create_activity_assign_image'] = 'Aufgabe mit KI erstellen (mit Bildern)';
$string['create_activity_assign_noimage'] = 'Aufgabe mit KI erstellen (ohne Bilder)';
$string['create_activity_book_image'] = 'Buch mit KI erstellen (mit Bildern)';
$string['create_activity_book_noimage'] = 'Buch mit KI erstellen (ohne Bilder)';
$string['create_activity_choice_image'] = 'Abstimmung mit KI erstellen (mit Bildern)';
$string['create_activity_choice_noimage'] = 'Abstimmung mit KI erstellen (ohne Bilder)';
$string['create_activity_data_image'] = 'Datenbank mit KI erstellen (mit Bildern)';
$string['create_activity_data_noimage'] = 'Datenbank mit KI erstellen (ohne Bilder)';
$string['create_activity_feedback_image'] = 'Umfrage mit KI erstellen (mit Bildern)';
$string['create_activity_feedback_noimage'] = 'Umfrage mit KI erstellen (ohne Bilder)';
$string['create_activity_folder_image'] = 'Ordner mit KI erstellen (mit Bildern)';
$string['create_activity_folder_noimage'] = 'Ordner mit KI erstellen (ohne Bilder)';
$string['create_activity_forum_image'] = 'Forum mit KI erstellen (mit Bildern)';
$string['create_activity_forum_noimage'] = 'Forum mit KI erstellen (ohne Bilder)';
$string['create_activity_glossary_image'] = 'Glossar mit KI erstellen (mit Bildern)';
$string['create_activity_glossary_noimage'] = 'Glossar mit KI erstellen (ohne Bilder)';
$string['create_activity_h5pactivity_image'] = 'H5P-Aktivität mit KI erstellen (mit Bildern)';
$string['create_activity_h5pactivity_noimage'] = 'H5P-Aktivität mit KI erstellen (ohne Bilder)';
$string['create_activity_imscp_image'] = 'IMS-Paket mit KI erstellen (mit Bildern)';
$string['create_activity_imscp_noimage'] = 'IMS-Paket mit KI erstellen (ohne Bilder)';
$string['create_activity_label_image'] = 'Label mit KI erstellen (mit Bildern)';
$string['create_activity_label_noimage'] = 'Label mit KI erstellen (ohne Bilder)';
$string['create_activity_lesson_image'] = 'Lektion mit KI erstellen (mit Bildern)';
$string['create_activity_lesson_noimage'] = 'Lektion mit KI erstellen (ohne Bilder)';
$string['create_activity_page_image'] = 'Seite mit KI erstellen (mit Bildern)';
$string['create_activity_page_noimage'] = 'Seite mit KI erstellen (ohne Bilder)';
$string['create_activity_quiz_image'] = 'Quiz mit KI erstellen (mit Bildern)';
$string['create_activity_quiz_noimage'] = 'Quiz mit KI erstellen (ohne Bilder)';
$string['create_activity_resource_image'] = 'Datei/Ressource mit KI erstellen (mit Bildern)';
$string['create_activity_resource_noimage'] = 'Datei/Ressource mit KI erstellen (ohne Bilder)';
$string['create_activity_scorm_image'] = 'SCORM-Paket mit KI erstellen (mit Bildern)';
$string['create_activity_scorm_noimage'] = 'SCORM-Paket mit KI erstellen (ohne Bilder)';
$string['create_activity_url_image'] = 'URL mit KI erstellen (mit Bildern)';
$string['create_activity_url_noimage'] = 'URL mit KI erstellen (ohne Bilder)';
$string['create_activity_wiki_image'] = 'Wiki mit KI erstellen (mit Bildern)';
$string['create_activity_wiki_noimage'] = 'Wiki mit KI erstellen (ohne Bilder)';
$string['create_activity_workshop_image'] = 'Workshop mit KI erstellen (mit Bildern)';
$string['create_activity_workshop_noimage'] = 'Workshop mit KI erstellen (ohne Bilder)';
$string['curlerror'] = 'Datacurso API cURL-Fehler: {$a}';
$string['custom_model_name'] = 'Benutzerdefinierter Modellname';
$string['custom_model_name_help'] = 'Optionaler Name zur Identifizierung dieser spezifischen KI-Modellkonfiguration.';
$string['datacurso:manage'] = 'KI-Anbieter-Einstellungen verwalten';
$string['datacurso:use'] = 'Datacurso KI-Dienste nutzen';
$string['datacurso:viewreports'] = 'KI-Nutzungsberichte anzeigen';
$string['day'] = 'Tag';
$string['days'] = 'Tage';
$string['description'] = 'Beschreibung';
$string['descriptionpagelistplugins'] = 'Hier finden Sie die Liste der Plugins, die mit dem Datacurso-Anbieter kompatibel sind';
$string['emptyprompt'] = 'Leere Eingabeaufforderung';
$string['emptyresponse'] = 'Keine Antwort von der Datacurso-API.';
$string['endpointurl'] = 'Endpunkt-URL';
$string['endpointurl_help'] = 'Basis-API-Endpunkt-URL des Datacurso KI-Anbieters. Normalerweise so etwas wie https://api.datacurso.ai/v1/.';
$string['entity_consumption'] = 'Verbrauch';
$string['error_ratelimit_exceeded'] = 'Das zulässige Verbrauchslimit wurde überschritten. Bitte versuchen Sie es erneut um {$a}.';
$string['errorgetbalancecredits'] = 'Das Kreditguthaben konnte nicht von der externen API abgerufen werden';
$string['filter_year'] = 'Year';
$string['forbidden'] = 'Sie dürfen diese Aktion mit der aktuellen Lizenz nicht ausführen. Bitte überprüfen Sie Ihre Lizenz und verfügbaren Kredite unter <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Kredite verwalten</a> im Datacurso-Shop.';
$string['generate_activitie'] = 'Aktivität oder Ressource mit KI generieren';
$string['generate_ai_reinforcement_activity'] = 'KI-Verstärkungsaktivität erstellen';
$string['generate_analysis_comments'] = 'Bewertungsanalyse einer Aktivität/Ressource mit KI generieren';
$string['generate_analysis_course'] = 'Kursbewertungsanalyse mit KI generieren';
$string['generate_analysis_general'] = 'Allgemeine Bewertungsanalyse mit KI generieren';
$string['generate_analysis_story_student'] = 'Analysebericht des Studenten mit KI generieren';
$string['generate_assign_answer'] = 'Aufgabenbewertung mit KI generieren';
$string['generate_certificate_answer'] = 'Zertifikatsnachricht mit KI generieren';
$string['generate_chat_embeddings'] = 'KI-Gesprächsverlauf';
$string['generate_chat_message'] = 'Tutor-KI-Nachricht generieren';
$string['generate_chat_stream'] = 'KI-Antwort';
$string['generate_creation_course'] = 'Vollständigen Kurs mit KI erstellen';
$string['generate_creation_course_image'] = 'Vollständigen Kurs mit KI erstellen (mit Bildern)';
$string['generate_creation_course_noimage'] = 'Vollständigen Kurs mit KI erstellen (ohne Bilder)';
$string['generate_forum_chat'] = 'Forumantwort mit KI generieren';
$string['generate_forum_grade'] = 'Forum mit KI bewerten';
$string['generate_image'] = 'Bild mit KI generieren';
$string['generate_plan_course'] = 'Kurserstellungsplan mit KI generieren';
$string['generate_summary'] = 'Zusammenfassung mit KI generieren';
$string['generate_text'] = 'Text mit KI generieren';
$string['goto'] = 'Zum Bericht gehen';
$string['hour'] = 'Stunde';
$string['hours'] = 'Stunden';
$string['httperror'] = 'Unerwarteter Fehler bei der Verarbeitung Ihrer Anfrage (HTTP {$a}). Bitte versuchen Sie es später erneut. Wenn das Problem weiterhin besteht, wenden Sie sich an Ihren Website-Administrator.';
$string['id'] = 'ID';
$string['installed'] = 'Installiert';
$string['instance_disabled'] = 'Die Datacurso-Anbieterinstanz ist deaktiviert';
$string['invalidjson'] = 'Ungültiges JSON';
$string['invalidlicensekey'] = 'Der Lizenzschlüssel ist abgelaufen oder ungültig. Bitte gehen Sie zu <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Kredite verwalten</a> im Datacurso-Shop, um Ihre Lizenz zu erneuern oder eine neue zu erwerben.';
$string['jsondecodeerror'] = 'Fehler beim Verarbeiten der Antwort von der Datacurso-API: {$a}';
$string['license_not_allowed'] = 'Ihre Lizenz erlaubt diese Anfrage nicht. Bitte verwalten Sie Ihre Lizenzen und Kredite unter <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Kredite verwalten</a> im Datacurso-Shop.';
$string['licensekey'] = 'Lizenzschlüssel';
$string['licensekey_help'] = 'Geben Sie Ihren Datacurso KI-Anbieter-Lizenzschlüssel ein.';
$string['licensekey_missing'] = 'Der Lizenzschlüssel ist nicht konfiguriert';
$string['link_consumptionhistory'] = 'Kreditverbrauchsverlauf';
$string['link_generalreport'] = 'Allgemeiner Bericht';
$string['link_generalreport_datacurso'] = 'Allgemeiner Bericht Datacurso AI';
$string['link_listplugings'] = 'Liste der Datacurso-Plugins';
$string['link_plugin'] = 'Link';
$string['link_provider_config'] = 'Anbieterkonfiguration';
$string['link_report_statistic'] = 'Allgemeiner Statistikbericht';
$string['message_no_there_plugins'] = 'Keine Plugins verfügbar';
$string['minute'] = 'Minute';
$string['minutes'] = 'Minuten';
$string['month'] = 'Monat';
$string['months'] = 'Monate';
$string['notenoughtokens'] = 'Unzureichende KI-Kredite. Bitte besuchen Sie <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Kredite verwalten</a> im Datacurso-Shop, um mehr Kredite zuzuweisen oder zu erwerben. Oder wenden Sie sich an Ihren Administrator.';
$string['of'] = 'von';
$string['plugin'] = 'Plugin';
$string['plugindesc_assign_ai'] = 'Aufgaben mit KI-Unterstützung bewerten.';
$string['plugindesc_coursegen'] = 'Vollständige Kurse, Aktivitäten und Ressourcen mit KI erstellen.';
$string['plugindesc_datacurso_ratings'] = 'Ermöglicht Studierenden, Aktivitäten und Ressourcen zu bewerten; Lehrende und Administratoren können später KI-basierte Kursanalysen erstellen.';
$string['plugindesc_dttutor'] = 'Mit einem KI-Tutor im Kurs chatten.';
$string['plugindesc_forum_ai'] = 'Foren mit KI-gestützter Analyse erweitern, um automatisch Zusammenfassungen zu generieren.';
$string['plugindesc_lifestory'] = 'KI-gestützter Bericht und Analyse des akademischen Fortschritts des Studierenden.';
$string['plugindesc_smartrules'] = 'Automatisierte Aktivitäten basierend auf früheren Bedingungen der Studierenden erstellen.';
$string['plugindesc_socialcert'] = 'Personalisierte Zertifikate bei Kursabschluss automatisch generieren.';
$string['pluginname'] = 'Datacurso KI-Anbieter';
$string['pluginname_assign_ai'] = 'Aufgaben-KI';
$string['pluginname_coursegen'] = 'Kurserstellungs-KI';
$string['pluginname_datacurso_ratings'] = 'Aktivitätsbewertungs-KI';
$string['pluginname_dttutor'] = 'Tutor-KI';
$string['pluginname_forum_ai'] = 'Forum-KI';
$string['pluginname_lifestory'] = 'Studenten-Lebensgeschichte-KI';
$string['pluginname_smartrules'] = 'SmartRules-KI';
$string['pluginname_socialcert'] = 'Zertifikat-teilen-KI';
$string['privacy:metadata'] = 'Das Datacurso KI-Anbieter-Plugin speichert keine personenbezogenen Daten lokal. Alle Daten werden von externen Datacurso-KI-Diensten verarbeitet.';
$string['privacy:metadata:aiprovider_datacurso'] = 'Datacurso KI-Anfrage-Nutzdaten, die an den externen Dienst gesendet werden.';
$string['privacy:metadata:aiprovider_datacurso:externalpurpose'] = 'Diese Daten werden an Datacurso KI gesendet, um die angeforderte Aktion auszuführen.';
$string['privacy:metadata:aiprovider_datacurso:numberimages'] = 'Gesamtzahl der vom KI-Dienst angeforderten Bilder.';
$string['privacy:metadata:aiprovider_datacurso:prompt'] = 'Der an den KI-Dienst übermittelte Eingabetext.';
$string['privacy:metadata:aiprovider_datacurso:userid'] = 'Die Moodle-Benutzer-ID, die die KI-Anfrage stellt.';
$string['privacy:metadata:aiprovider_datacurso_rlimit'] = 'Lokal gespeicherter laufender Nutzungsstatus des Rate Limits pro Benutzer und Dienst.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:lastsync'] = 'Letzter Synchronisations-Zeitstempel mit der Remote-Historie.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:serviceid'] = 'Dienst-ID (z. B. local_coursegen).';
$string['privacy:metadata:aiprovider_datacurso_rlimit:timecreated'] = 'Zeitpunkt, zu dem dieser Datensatz erstellt wurde.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:timemodified'] = 'Zeitpunkt, zu dem dieser Datensatz zuletzt geändert wurde.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:tokensused'] = 'Innerhalb des aktuellen Zeitfensters verbrauchte Credits.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:userid'] = 'Benutzer-ID, die mit dem verfolgten Verbrauchsfenster verbunden ist.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:windowstart'] = 'Startzeitstempel des Fensters, der zur Berechnung der Verbrauchslimits verwendet wird.';
$string['privacy:metadata:aiprovider_datacurso_userlimit'] = 'Lokal gespeicherte Datacurso Token-Quoten pro Benutzer.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:countfrom'] = 'Zeitstempel, der markiert, wann die Quote mit der Verfolgung des Verbrauchs begonnen hat.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:lastsync'] = 'Letzte Synchronisation der Verbrauchsinformationen.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:timecreated'] = 'Zeitpunkt, zu dem der Quoten-Datensatz erstellt wurde.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:timemodified'] = 'Zeitpunkt, zu dem der Quoten-Datensatz zuletzt aktualisiert wurde.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:tokenlimit'] = 'Maximale Anzahl an Tokens, die dem Benutzer gewährt werden.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:tokensused'] = 'Seit Beginn der Verfolgung verbrauchte Tokens.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:userid'] = 'Mit der Quote verbundene Benutzer-ID.';
$string['privacy:metadata:aiprovider_datacurso_consumption'] = 'Lokale Kopie des externen Kreditverbrauchsverlaufs, bei Bedarf für Berichte synchronisiert.';
$string['privacy:metadata:aiprovider_datacurso_consumption:userid'] = 'Der Moodle-Benutzer, der den Verbrauch ausgelöst hat.';
$string['privacy:metadata:aiprovider_datacurso_consumption:service'] = 'Dienstkennung (z. B. local_coursegen).';
$string['privacy:metadata:aiprovider_datacurso_consumption:action'] = 'Aktionskennung.';
$string['privacy:metadata:aiprovider_datacurso_consumption:credits'] = 'Verbrauchte Credits.';
$string['privacy:metadata:aiprovider_datacurso_consumption:balance'] = 'Verbleibendes Guthaben nach dem Verbrauch.';
$string['privacy:metadata:aiprovider_datacurso_consumption:timecreated'] = 'Zeitstempel des Verbrauchs.';
$string['ratelimit_creditperaction'] = 'Credits per action';
$string['ratelimit_creditperaction_desc'] = 'Estimated maximum credits a single action of this plugin can cost. A request is blocked upfront when the credits remaining in the window are fewer than this value.';
$string['ratelimit_creditperaction_help'] = 'Estimated maximum credits a single action of this plugin can cost. A request is blocked upfront when the credits remaining in the window are fewer than this value.';
$string['ratelimit_enable'] = 'Rate Limit aktivieren';
$string['ratelimit_enable_desc'] = 'Wenn aktiviert, wird das Credit-Limit pro Benutzer für dieses Plugin durchgesetzt.';
$string['ratelimit_limit'] = 'Credit-Limit pro Fenster';
$string['ratelimit_limit_help'] = 'Die maximale Anzahl von Anforderungen, die pro Rate-Limit-Fenster zulässig ist.';
$string['ratelimit_window'] = 'Zeitfenster';
$string['ratelimit_window_help'] = 'Select the duration and unit for the rate limit window.';
$string['ratelimit_window_unit'] = 'Fenster-Einheit';
$string['ratelimit_window_value'] = 'Fensterwert';
$string['read_context_course'] = 'Kontext für KI-Kurserstellung lesen';
$string['read_context_course_model'] = 'Akademisches Modell für KI-Kurserstellung hochladen';
$string['remainingtokens'] = 'Verbleibendes Guthaben';
$string['responseinvalidai'] = 'Ungültige Antwort vom KI-Dienst.';
$string['responseinvalidaimage'] = 'Ungültige Antwort vom KI-Dienst (kein Bild).';
$string['responseinvalidaimagecreate'] = 'Bilddatei konnte nicht erstellt werden.';
$string['second'] = 'Sekunde';
$string['seconds'] = 'Sekunden';
$string['service'] = 'Dienst';
$string['tokens'] = 'Kredite';
$string['tokens_available'] = 'Verfügbare Kredite';
$string['tokensconsumed'] = 'Verbrauchte Kredite';
$string['tokensconsumedday'] = 'Pro Tag verbrauchte Kredite';
$string['tokensconsumedmonth'] = 'Pro Monat verbrauchte Kredite';
$string['tokensused'] = 'Verwendete Kredite';
$string['total_consumed'] = 'Verbrauchte Kredite';
$string['total_user_consumed'] = 'Total credits consumed by user';
$string['userid'] = 'Benutzer';
$string['warningconfig_instance'] = 'Warnung: Es sollte nur eine Instanz mit diesem Anbieter für eine ordnungsgemäße Verwendung erstellt werden.';
$string['week'] = 'Woche';
$string['weeks'] = 'Wochen';
$string['year'] = 'Jahr';
$string['years'] = 'Jahre';
