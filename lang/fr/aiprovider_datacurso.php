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
$string['action:explain_text:endpoint'] = 'Point de terminaison pour l\'explication de texte';
$string['action:explain_text:model'] = 'Modèle pour l\'explication';
$string['action:explain_text:model_help'] = 'Sélectionnez le modèle qui générera les explications.';
$string['action:explain_text:systeminstruction'] = 'Instruction système pour l\'explication';
$string['action:explain_text:systeminstruction_help'] = 'Fournissez le contexte pour guider le processus d\'explication.';
$string['action:generate_image:endpoint'] = 'Point de terminaison API';
$string['action:generate_image:endpoint_desc'] = 'Le point de terminaison pour générer des images';
$string['action:generate_image:model'] = 'Modèle pour la génération d\'image';
$string['action:generate_image:model_help'] = 'Sélectionnez le modèle d\'IA pour générer des images.';
$string['action:generate_image:systeminstruction'] = 'Invite système pour la génération d\'image';
$string['action:generate_image:systeminstruction_help'] = 'Instructions supplémentaires qui guident l\'IA dans la génération de l\'image souhaitée.';
$string['action:generate_text:endpoint'] = 'Point de terminaison API';
$string['action:generate_text:endpoint_desc'] = 'Le point de terminaison pour générer du texte';
$string['action:generate_text:instruction'] = 'Instruction système';
$string['action:generate_text:instruction_desc'] = 'Cette instruction est envoyée au modèle IA avec la demande de l\'utilisateur. La modification de cette instruction n\'est pas recommandée sauf si absolument nécessaire.';
$string['action:generate_text:model'] = 'Modèle pour la génération de texte';
$string['action:generate_text:model_help'] = 'Sélectionnez le modèle d\'IA qui sera utilisé pour générer le texte.';
$string['action:generate_text:systeminstruction'] = 'Instruction système';
$string['action:generate_text:systeminstruction_help'] = 'Instruction ou contexte donné à l\'IA avant de générer le texte. Utile pour contrôler le ton, la structure ou le but de la réponse.';
$string['action:summarise_text:endpoint'] = 'Point de terminaison API';
$string['action:summarise_text:endpoint_desc'] = 'Le point de terminaison pour générer du texte';
$string['action:summarise_text:instruction'] = 'Instruction système';
$string['action:summarise_text:instruction_desc'] = 'Cette instruction est envoyée au modèle IA avec la demande de l\'utilisateur. La modification de cette instruction n\'est pas recommandée sauf si absolument nécessaire.';
$string['action:summarise_text:model'] = 'Modèle pour la synthèse';
$string['action:summarise_text:model_help'] = 'Sélectionnez le modèle d\'IA qui sera utilisé pour résumer le texte.';
$string['action:summarise_text:systeminstruction'] = 'Instruction système pour le résumé';
$string['action:summarise_text:systeminstruction_help'] = 'Contexte facultatif pour influencer la manière dont le résumé est généré.';
$string['action_activity_image'] = 'Activity with image';
$string['action_activity_noimage'] = 'Activity without image';
$string['action_course_image'] = 'Course with image';
$string['action_course_noimage'] = 'Course without image';
$string['action_default'] = 'Credits per action';
$string['action_image'] = 'Generate image';
$string['action_text'] = 'Generate text / summary';
$string['all'] = 'Tous';
$string['alt_datacurso_icon'] = 'Icône Datacurso';
$string['chart_actions'] = 'Distribution des crédits par service';
$string['chart_tokens_by_day'] = 'Consommation de crédits par jour';
$string['chart_tokens_by_month'] = 'Nombre de crédits consommés par mois';
$string['chart_user_consumption'] = 'Credits consumed by user per service';
$string['connection'] = 'Paramètres de connexion';
$string['create_activity_assign_image'] = 'Créer un devoir avec IA (avec images)';
$string['create_activity_assign_noimage'] = 'Créer un devoir avec IA (sans images)';
$string['create_activity_book_image'] = 'Créer un livre avec IA (avec images)';
$string['create_activity_book_noimage'] = 'Créer un livre avec IA (sans images)';
$string['create_activity_choice_image'] = 'Créer un choix avec IA (avec images)';
$string['create_activity_choice_noimage'] = 'Créer un choix avec IA (sans images)';
$string['create_activity_data_image'] = 'Créer une base de données avec IA (avec images)';
$string['create_activity_data_noimage'] = 'Créer une base de données avec IA (sans images)';
$string['create_activity_feedback_image'] = 'Créer un sondage avec IA (avec images)';
$string['create_activity_feedback_noimage'] = 'Créer un sondage avec IA (sans images)';
$string['create_activity_folder_image'] = 'Créer un dossier avec IA (avec images)';
$string['create_activity_folder_noimage'] = 'Créer un dossier avec IA (sans images)';
$string['create_activity_forum_image'] = 'Créer un forum avec IA (avec images)';
$string['create_activity_forum_noimage'] = 'Créer un forum avec IA (sans images)';
$string['create_activity_glossary_image'] = 'Créer un glossaire avec IA (avec images)';
$string['create_activity_glossary_noimage'] = 'Créer un glossaire avec IA (sans images)';
$string['create_activity_h5pactivity_image'] = 'Créer une activité H5P avec IA (avec images)';
$string['create_activity_h5pactivity_noimage'] = 'Créer une activité H5P avec IA (sans images)';
$string['create_activity_imscp_image'] = 'Créer un paquet IMS avec IA (avec images)';
$string['create_activity_imscp_noimage'] = 'Créer un paquet IMS avec IA (sans images)';
$string['create_activity_label_image'] = 'Créer une étiquette avec IA (avec images)';
$string['create_activity_label_noimage'] = 'Créer une étiquette avec IA (sans images)';
$string['create_activity_lesson_image'] = 'Créer une leçon avec IA (avec images)';
$string['create_activity_lesson_noimage'] = 'Créer une leçon avec IA (sans images)';
$string['create_activity_page_image'] = 'Créer une page avec IA (avec images)';
$string['create_activity_page_noimage'] = 'Créer une page avec IA (sans images)';
$string['create_activity_quiz_image'] = 'Créer un quiz avec IA (avec images)';
$string['create_activity_quiz_noimage'] = 'Créer un quiz avec IA (sans images)';
$string['create_activity_resource_image'] = 'Créer un fichier/ressource avec IA (avec images)';
$string['create_activity_resource_noimage'] = 'Créer un fichier/ressource avec IA (sans images)';
$string['create_activity_scorm_image'] = 'Créer un paquet SCORM avec IA (avec images)';
$string['create_activity_scorm_noimage'] = 'Créer un paquet SCORM avec IA (sans images)';
$string['create_activity_url_image'] = 'Créer une URL avec IA (avec images)';
$string['create_activity_url_noimage'] = 'Créer une URL avec IA (sans images)';
$string['create_activity_wiki_image'] = 'Créer un wiki avec IA (avec images)';
$string['create_activity_wiki_noimage'] = 'Créer un wiki avec IA (sans images)';
$string['create_activity_workshop_image'] = 'Créer un atelier avec IA (avec images)';
$string['create_activity_workshop_noimage'] = 'Créer un atelier avec IA (sans images)';
$string['curlerror'] = 'Erreur cURL de l\'API Datacurso : {$a}';
$string['custom_model_name'] = 'Nom de modèle personnalisé';
$string['custom_model_name_help'] = 'Nom facultatif pour identifier cette configuration de modèle d\'IA spécifique.';
$string['datacurso:manage'] = 'Gérer les paramètres du fournisseur IA';
$string['datacurso:use'] = 'Utiliser les services IA Datacurso';
$string['datacurso:viewreports'] = 'Voir les rapports d\'utilisation de l\'IA';
$string['day'] = 'jour';
$string['days'] = 'Jours';
$string['description'] = 'Description';
$string['descriptionpagelistplugins'] = 'Vous trouverez ici la liste des plugins compatibles avec le fournisseur Datacurso';
$string['emptyprompt'] = 'Invite vide';
$string['emptyresponse'] = 'Aucune réponse de l\'API Datacurso.';
$string['endpointurl'] = 'URL du point de terminaison';
$string['endpointurl_help'] = 'URL du point de terminaison de l\'API de base du fournisseur d\'IA Datacurso. Généralement quelque chose comme https://api.datacurso.ai/v1/.';
$string['error_ratelimit_exceeded'] = 'La limite de consommation autorisée a été dépassée. Veuillez réessayer à {$a}.';
$string['errorgetbalancecredits'] = 'Impossible de récupérer le solde de crédits depuis l\'API externe';
$string['errorinitinformation'] = 'Les informations initiales n\'ont pas pu être obtenues.';
$string['export_csv'] = 'Export CSV';
$string['filter_year'] = 'Year';
$string['forbidden'] = 'Vous n\'êtes pas autorisé à effectuer cette action avec la licence actuelle. Veuillez vérifier votre licence et les crédits disponibles dans <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Gérer les crédits</a> dans la boutique Datacurso.';
$string['generate_activitie'] = 'Générer une activité ou une ressource avec l\'IA';
$string['generate_ai_reinforcement_activity'] = 'Créer une activité de renforcement IA';
$string['generate_analysis_comments'] = 'Générer une analyse de notation d\'une activité/ressource avec l\'IA';
$string['generate_analysis_course'] = 'Générer une analyse de notation du cours avec l\'IA';
$string['generate_analysis_general'] = 'Générer une analyse de notation générale avec l\'IA';
$string['generate_analysis_story_student'] = 'Générer une analyse de l\'histoire de l\'étudiant avec l\'IA';
$string['generate_assign_answer'] = 'Générer une révision de devoir avec l\'IA';
$string['generate_certificate_answer'] = 'Générer un message de certificat avec l\'IA';
$string['generate_chat_embeddings'] = 'Historique de conversation IA';
$string['generate_chat_message'] = 'Générer un message de tuteur IA';
$string['generate_chat_stream'] = 'Réponse IA';
$string['generate_creation_course'] = 'Créer un cours complet avec l\'IA';
$string['generate_creation_course_image'] = 'Créer un cours complet avec l\'IA (avec images)';
$string['generate_creation_course_noimage'] = 'Créer un cours complet avec l\'IA (sans images)';
$string['generate_forum_chat'] = 'Générer une réponse de forum avec l\'IA';
$string['generate_forum_grade'] = 'Noter le forum avec l\'IA';
$string['generate_image'] = 'Générer une image avec l\'IA';
$string['generate_plan_course'] = 'Générer un plan de création de cours avec l\'IA';
$string['generate_summary'] = 'Générer un résumé avec l\'IA';
$string['generate_text'] = 'Générer du texte avec l\'IA';
$string['goto'] = 'Aller au rapport';
$string['gotopage'] = 'Aller à la page';
$string['hour'] = 'heure';
$string['hours'] = 'Heures';
$string['httperror'] = 'Erreur inattendue lors du traitement de votre demande (HTTP {$a}). Veuillez réessayer plus tard. Si le problème persiste, contactez votre administrateur de site.';
$string['id'] = 'ID';
$string['installed'] = 'Installé';
$string['instance_disabled'] = 'L\'instance du fournisseur Datacurso est désactivée';
$string['invalidjson'] = 'JSON invalide';
$string['invalidlicensekey'] = 'La clé de licence a expiré ou n\'est pas valide. Veuillez aller sur <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Gérer les crédits</a> dans la boutique Datacurso pour renouveler ou acheter une nouvelle licence.';
$string['jsondecodeerror'] = 'Erreur lors du traitement de la réponse de l\'API Datacurso : {$a}';
$string['license_not_allowed'] = 'Votre licence ne permet pas d\'effectuer cette demande. Veuillez gérer vos licences et crédits dans <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Gérer les crédits</a> dans la boutique Datacurso.';
$string['licensekey'] = 'Clé de licence';
$string['licensekey_help'] = 'Entrez votre clé de licence du fournisseur d\'IA Datacurso.';
$string['licensekey_missing'] = 'La clé de licence n\'est pas configurée';
$string['link_consumptionhistory'] = 'Historique de consommation des crédits';
$string['link_generalreport'] = 'Rapport général';
$string['link_generalreport_datacurso'] = 'Rapport général Datacurso IA';
$string['link_listplugings'] = 'Liste des plugins Datacurso';
$string['link_plugin'] = 'Lien';
$string['link_provider_config'] = 'Configuration du fournisseur';
$string['link_report_statistic'] = 'Rapport de statistiques générales';
$string['message_no_there_plugins'] = 'Aucun plugin disponible';
$string['minute'] = 'minute';
$string['minutes'] = 'Minutes';
$string['month'] = 'mois';
$string['months'] = 'Mois';
$string['nodata'] = 'Aucune information trouvée';
$string['notenoughtokens'] = 'Crédits IA insuffisants. Veuillez visiter <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Gérer les crédits</a> dans la boutique Datacurso pour allouer ou acheter plus de crédits. Ou contactez votre administrateur.';
$string['of'] = 'de';
$string['pageinfo'] = 'Page {$a->current} sur {$a->totalpages} ({$a->total} enregistrements)';
$string['plugin'] = 'Plugin';
$string['plugindesc_assign_ai'] = 'Réviser les devoirs avec l\'assistance de l\'IA.';
$string['plugindesc_coursegen'] = 'Créer des cours complets, des activités et des ressources avec l\'IA.';
$string['plugindesc_datacurso_ratings'] = 'Permet aux étudiants de noter les activités et les ressources ; les enseignants et administrateurs peuvent ensuite générer une analyse de cours basée sur l\'IA.';
$string['plugindesc_dttutor'] = 'Discuter avec un tuteur IA dans le cours.';
$string['plugindesc_forum_ai'] = 'Étendre les forums avec une analyse alimentée par l\'IA pour générer automatiquement des résumés.';
$string['plugindesc_lifestory'] = 'Rapport et analyse alimentés par l\'IA des progrès académiques de l\'étudiant.';
$string['plugindesc_smartrules'] = 'Créer des activités automatisées basées sur les conditions antérieures des étudiants.';
$string['plugindesc_socialcert'] = 'Générer automatiquement des certificats personnalisés à la fin du cours.';
$string['pluginname'] = 'Fournisseur IA Datacurso';
$string['pluginname_assign_ai'] = 'Devoir IA';
$string['pluginname_coursegen'] = 'Créateur de cours IA';
$string['pluginname_datacurso_ratings'] = 'Notation d\'activités IA';
$string['pluginname_dttutor'] = 'Tuteur IA';
$string['pluginname_forum_ai'] = 'Forum IA';
$string['pluginname_lifestory'] = 'Histoire de vie de l\'étudiant IA';
$string['pluginname_smartrules'] = 'SmartRules IA';
$string['pluginname_socialcert'] = 'Partager certificat IA';
$string['privacy:metadata'] = 'Le plugin Fournisseur IA Datacurso ne stocke aucune donnée personnelle localement. Toutes les données sont traitées par les services IA externes de Datacurso.';
$string['privacy:metadata:aiprovider_datacurso'] = 'Charges utiles des demandes IA Datacurso envoyées au service externe.';
$string['privacy:metadata:aiprovider_datacurso:externalpurpose'] = 'Ces données sont envoyées à Datacurso IA pour répondre à l\'action demandée.';
$string['privacy:metadata:aiprovider_datacurso:numberimages'] = 'Nombre total d\'images demandées au service IA.';
$string['privacy:metadata:aiprovider_datacurso:prompt'] = 'Le texte d\'invite fourni au service IA.';
$string['privacy:metadata:aiprovider_datacurso:userid'] = 'L\'ID utilisateur Moodle effectuant la demande IA.';
$string['privacy:metadata:aiprovider_datacurso_rlimit'] = 'État d\'utilisation continue de la limite de taux par utilisateur et par service stocké localement.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:lastsync'] = 'Horodatage de la dernière synchronisation avec l\'historique distant.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:serviceid'] = 'Identifiant du service (par exemple, local_coursegen).';
$string['privacy:metadata:aiprovider_datacurso_rlimit:timecreated'] = 'Heure à laquelle cet enregistrement a été créé.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:timemodified'] = 'Heure à laquelle cet enregistrement a été modifié pour la dernière fois.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:tokensused'] = 'Crédits utilisés dans la fenêtre de temps actuelle.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:userid'] = 'ID utilisateur lié à la fenêtre de consommation suivie.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:windowstart'] = 'Horodatage de début de fenêtre utilisé pour calculer les limites de consommation.';
$string['privacy:metadata:aiprovider_datacurso_userlimit'] = 'Quotas de jetons Datacurso par utilisateur stockés localement.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:countfrom'] = 'Horodatage marquant le début du suivi de l\'utilisation par le quota.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:lastsync'] = 'Dernière synchronisation des informations d\'utilisation.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:timecreated'] = 'Heure à laquelle l\'enregistrement de quota a été créé.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:timemodified'] = 'Heure à laquelle l\'enregistrement de quota a été mis à jour pour la dernière fois.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:tokenlimit'] = 'Nombre maximum de jetons accordés à l\'utilisateur.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:tokensused'] = 'Jetons consommés depuis le début du suivi.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:userid'] = 'ID utilisateur associé au quota.';
$string['ratelimit_creditperaction'] = 'Credits per action';
$string['ratelimit_creditperaction_desc'] = 'Estimated maximum credits a single action of this plugin can cost. A request is blocked upfront when the credits remaining in the window are fewer than this value.';
$string['ratelimit_creditperaction_help'] = 'Estimated maximum credits a single action of this plugin can cost. A request is blocked upfront when the credits remaining in the window are fewer than this value.';
$string['ratelimit_enable'] = 'Activer la limite de taux';
$string['ratelimit_enable_desc'] = 'Si activé, la limite de crédit par utilisateur sera appliquée pour ce plugin.';
$string['ratelimit_limit'] = 'Limite de crédit par fenêtre';
$string['ratelimit_limit_help'] = 'Le nombre maximum de requêtes autorisées par fenêtre de limite de taux.';
$string['ratelimit_window'] = 'Fenêtre de temps';
$string['ratelimit_window_help'] = 'Select the duration and unit for the rate limit window.';
$string['ratelimit_window_unit'] = 'Unité de fenêtre';
$string['ratelimit_window_value'] = 'Valeur de fenêtre';
$string['read_context_course'] = 'Lire le contexte pour la création de cours IA';
$string['read_context_course_model'] = 'Télécharger le modèle académique pour la création de cours IA';
$string['remainingtokens'] = 'Solde restant';
$string['responseinvalidai'] = 'Réponse invalide du service IA.';
$string['responseinvalidaimage'] = 'Réponse invalide du service IA (pas d\'image).';
$string['responseinvalidaimagecreate'] = 'Impossible de créer le fichier image.';
$string['second'] = 'seconde';
$string['seconds'] = 'Secondes';
$string['service'] = 'Service';
$string['showrows'] = 'Afficher les lignes';
$string['tokens'] = 'Crédits';
$string['tokens_available'] = 'Crédits disponibles';
$string['tokensconsumed'] = 'Crédits consommés';
$string['tokensconsumedday'] = 'Crédits consommés par jour';
$string['tokensconsumedmonth'] = 'Crédits consommés par mois';
$string['tokensused'] = 'Crédits utilisés';
$string['total_consumed'] = 'Crédits consommés';
$string['total_user_consumed'] = 'Total credits consumed by user';
$string['userid'] = 'Utilisateur';
$string['warningconfig_instance'] = 'Avertissement : Une seule instance doit être créée avec ce fournisseur pour une utilisation appropriée.';
$string['week'] = 'semaine';
$string['weeks'] = 'Semaines';
$string['year'] = 'an';
$string['years'] = 'Années';
