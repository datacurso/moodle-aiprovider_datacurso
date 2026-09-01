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

$string['action'] = 'Действие';
$string['action:explain_text:endpoint'] = 'Конечная точка для объяснения текста';
$string['action:explain_text:model'] = 'Модель для объяснения';
$string['action:explain_text:model_help'] = 'Выберите модель, которая будет генерировать объяснения.';
$string['action:explain_text:systeminstruction'] = 'Системная инструкция для объяснения';
$string['action:explain_text:systeminstruction_help'] = 'Предоставьте контекст для управления процессом объяснения.';
$string['action:generate_image:endpoint'] = 'Конечная точка API';
$string['action:generate_image:endpoint_desc'] = 'Конечная точка для генерации изображений';
$string['action:generate_image:model'] = 'Модель для генерации изображения';
$string['action:generate_image:model_help'] = 'Выберите модель ИИ для генерации изображений.';
$string['action:generate_image:systeminstruction'] = 'Системный запрос для генерации изображения';
$string['action:generate_image:systeminstruction_help'] = 'Дополнительные инструкции, которые направляют ИИ при генерации желаемого изображения.';
$string['action:generate_text:endpoint'] = 'Конечная точка API';
$string['action:generate_text:endpoint_desc'] = 'Конечная точка для генерации текста';
$string['action:generate_text:instruction'] = 'Системная инструкция';
$string['action:generate_text:instruction_desc'] = 'Эта инструкция отправляется модели ИИ вместе с запросом пользователя. Не рекомендуется редактировать эту инструкцию, если это не является абсолютно необходимым.';
$string['action:generate_text:model'] = 'Модель для генерации текста';
$string['action:generate_text:model_help'] = 'Выберите, какая модель ИИ будет использоваться для генерации текста.';
$string['action:generate_text:systeminstruction'] = 'Системная инструкция';
$string['action:generate_text:systeminstruction_help'] = 'Инструкция или контекст, предоставляемый ИИ перед генерацией текста. Полезно для контроля тона, структуры или цели ответа.';
$string['action:summarise_text:endpoint'] = 'Конечная точка API';
$string['action:summarise_text:endpoint_desc'] = 'Конечная точка для генерации текста';
$string['action:summarise_text:instruction'] = 'Системная инструкция';
$string['action:summarise_text:instruction_desc'] = 'Эта инструкция отправляется модели ИИ вместе с запросом пользователя. Не рекомендуется редактировать эту инструкцию, если это не является абсолютно необходимым.';
$string['action:summarise_text:model'] = 'Модель для суммирования';
$string['action:summarise_text:model_help'] = 'Выберите, какая модель ИИ будет использоваться для суммирования текста.';
$string['action:summarise_text:systeminstruction'] = 'Системная инструкция для резюме';
$string['action:summarise_text:systeminstruction_help'] = 'Необязательный контекст для влияния на то, как генерируется резюме.';
$string['action_activity_image'] = 'Activity with image';
$string['action_activity_noimage'] = 'Activity without image';
$string['action_course_image'] = 'Course with image';
$string['action_course_noimage'] = 'Course without image';
$string['action_default'] = 'Credits per action';
$string['action_image'] = 'Generate image';
$string['action_text'] = 'Generate text / summary';
$string['all'] = 'Все';
$string['alt_datacurso_icon'] = 'Иконка Datacurso';
$string['chart_actions'] = 'Распределение кредитов по сервисам';
$string['chart_tokens_by_day'] = 'Потребление кредитов по дням';
$string['chart_tokens_by_month'] = 'Количество потребленных кредитов в месяц';
$string['chart_user_consumption'] = 'Credits consumed by user per service';
$string['connection'] = 'Настройки подключения';
$string['create_activity_assign_image'] = 'Создать задание с помощью ИИ (с изображениями)';
$string['create_activity_assign_noimage'] = 'Создать задание с помощью ИИ (без изображений)';
$string['create_activity_book_image'] = 'Создать книгу с помощью ИИ (с изображениями)';
$string['create_activity_book_noimage'] = 'Создать книгу с помощью ИИ (без изображений)';
$string['create_activity_choice_image'] = 'Создать голосование с помощью ИИ (с изображениями)';
$string['create_activity_choice_noimage'] = 'Создать голосование с помощью ИИ (без изображений)';
$string['create_activity_data_image'] = 'Создать базу данных с помощью ИИ (с изображениями)';
$string['create_activity_data_noimage'] = 'Создать базу данных с помощью ИИ (без изображений)';
$string['create_activity_feedback_image'] = 'Создать опрос с помощью ИИ (с изображениями)';
$string['create_activity_feedback_noimage'] = 'Создать опрос с помощью ИИ (без изображений)';
$string['create_activity_folder_image'] = 'Создать папку с помощью ИИ (с изображениями)';
$string['create_activity_folder_noimage'] = 'Создать папку с помощью ИИ (без изображений)';
$string['create_activity_forum_image'] = 'Создать форум с помощью ИИ (с изображениями)';
$string['create_activity_forum_noimage'] = 'Создать форум с помощью ИИ (без изображений)';
$string['create_activity_glossary_image'] = 'Создать глоссарий с помощью ИИ (с изображениями)';
$string['create_activity_glossary_noimage'] = 'Создать глоссарий с помощью ИИ (без изображений)';
$string['create_activity_h5pactivity_image'] = 'Создать H5P активность с помощью ИИ (с изображениями)';
$string['create_activity_h5pactivity_noimage'] = 'Создать H5P активность с помощью ИИ (без изображений)';
$string['create_activity_imscp_image'] = 'Создать IMS пакет с помощью ИИ (с изображениями)';
$string['create_activity_imscp_noimage'] = 'Создать IMS пакет с помощью ИИ (без изображений)';
$string['create_activity_label_image'] = 'Создать метку с помощью ИИ (с изображениями)';
$string['create_activity_label_noimage'] = 'Создать метку с помощью ИИ (без изображений)';
$string['create_activity_lesson_image'] = 'Создать урок с помощью ИИ (с изображениями)';
$string['create_activity_lesson_noimage'] = 'Создать урок с помощью ИИ (без изображений)';
$string['create_activity_page_image'] = 'Создать страницу с помощью ИИ (с изображениями)';
$string['create_activity_page_noimage'] = 'Создать страницу с помощью ИИ (без изображений)';
$string['create_activity_quiz_image'] = 'Создать тест с помощью ИИ (с изображениями)';
$string['create_activity_quiz_noimage'] = 'Создать тест с помощью ИИ (без изображений)';
$string['create_activity_resource_image'] = 'Создать файл/ресурс с помощью ИИ (с изображениями)';
$string['create_activity_resource_noimage'] = 'Создать файл/ресурс с помощью ИИ (без изображений)';
$string['create_activity_scorm_image'] = 'Создать SCORM пакет с помощью ИИ (с изображениями)';
$string['create_activity_scorm_noimage'] = 'Создать SCORM пакет с помощью ИИ (без изображений)';
$string['create_activity_url_image'] = 'Создать URL с помощью ИИ (с изображениями)';
$string['create_activity_url_noimage'] = 'Создать URL с помощью ИИ (без изображений)';
$string['create_activity_wiki_image'] = 'Создать вики с помощью ИИ (с изображениями)';
$string['create_activity_wiki_noimage'] = 'Создать вики с помощью ИИ (без изображений)';
$string['create_activity_workshop_image'] = 'Создать семинар с помощью ИИ (с изображениями)';
$string['create_activity_workshop_noimage'] = 'Создать семинар с помощью ИИ (без изображений)';
$string['curlerror'] = 'Ошибка cURL API Datacurso: {$a}';
$string['custom_model_name'] = 'Пользовательское имя модели';
$string['custom_model_name_help'] = 'Необязательное имя для идентификации этой конкретной конфигурации модели ИИ.';
$string['datacurso:manage'] = 'Управление настройками провайдера ИИ';
$string['datacurso:use'] = 'Использование сервисов ИИ Datacurso';
$string['datacurso:viewreports'] = 'Просмотр отчетов об использовании ИИ';
$string['day'] = 'день';
$string['days'] = 'Дни';
$string['description'] = 'Описание';
$string['descriptionpagelistplugins'] = 'Здесь вы можете найти список плагинов, совместимых с провайдером Datacurso';
$string['emptyprompt'] = 'Пустой запрос';
$string['emptyresponse'] = 'Нет ответа от API Datacurso.';
$string['endpointurl'] = 'URL конечной точки';
$string['endpointurl_help'] = 'Базовый URL конечной точки API провайдера ИИ Datacurso. Обычно что-то вроде https://api.datacurso.ai/v1/.';
$string['entity_consumption'] = 'Потребление';
$string['error_ratelimit_exceeded'] = 'Разрешенный лимит потребления превышен. Пожалуйста, попробуйте снова в {$a}.';
$string['errorgetbalancecredits'] = 'Не удалось получить баланс кредитов из внешнего API';
$string['filter_year'] = 'Year';
$string['forbidden'] = 'Вам не разрешено выполнять это действие с текущей лицензией. Пожалуйста, проверьте вашу лицензию и доступные кредиты в <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Управление кредитами</a> в магазине Datacurso.';
$string['generate_activitie'] = 'Создать активность или ресурс с помощью ИИ';
$string['generate_ai_reinforcement_activity'] = 'Создать активность для закрепления с ИИ';
$string['generate_analysis_comments'] = 'Создать анализ оценки активности/ресурса с помощью ИИ';
$string['generate_analysis_course'] = 'Создать анализ оценки курса с помощью ИИ';
$string['generate_analysis_general'] = 'Создать общий анализ оценки с помощью ИИ';
$string['generate_analysis_story_student'] = 'Создать анализ истории студента с помощью ИИ';
$string['generate_assign_answer'] = 'Создать проверку задания с помощью ИИ';
$string['generate_certificate_answer'] = 'Создать сообщение сертификата с помощью ИИ';
$string['generate_chat_embeddings'] = 'История разговора с ИИ';
$string['generate_chat_message'] = 'Сгенерировать сообщение ИИ-тьютора';
$string['generate_chat_stream'] = 'Ответ ИИ';
$string['generate_creation_course'] = 'Создать полный курс с помощью ИИ';
$string['generate_creation_course_image'] = 'Создать полный курс с помощью ИИ (с изображениями)';
$string['generate_creation_course_noimage'] = 'Создать полный курс с помощью ИИ (без изображений)';
$string['generate_forum_chat'] = 'Создать ответ на форуме с помощью ИИ';
$string['generate_forum_grade'] = 'Оценить форум с помощью ИИ';
$string['generate_image'] = 'Создать изображение с помощью ИИ';
$string['generate_plan_course'] = 'Создать план создания курса с помощью ИИ';
$string['generate_summary'] = 'Создать резюме с помощью ИИ';
$string['generate_text'] = 'Создать текст с помощью ИИ';
$string['goto'] = 'Перейти к отчету';
$string['hour'] = 'час';
$string['hours'] = 'Часы';
$string['httperror'] = 'Неожиданная ошибка при обработке вашего запроса (HTTP {$a}). Пожалуйста, попробуйте позже. Если проблема сохраняется, свяжитесь с администратором сайта.';
$string['id'] = 'ID';
$string['installed'] = 'Установлено';
$string['instance_disabled'] = 'Экземпляр провайдера Datacurso отключен';
$string['invalidjson'] = 'Недопустимый JSON';
$string['invalidlicensekey'] = 'Ключ лицензии истек или недействителен. Пожалуйста, перейдите в <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Управление кредитами</a> в магазине Datacurso для обновления или покупки новой лицензии.';
$string['jsondecodeerror'] = 'Ошибка обработки ответа от API Datacurso: {$a}';
$string['license_not_allowed'] = 'Ваша лицензия не позволяет выполнить этот запрос. Пожалуйста, управляйте своими лицензиями и кредитами в <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Управление кредитами</a> в магазине Datacurso.';
$string['licensekey'] = 'Ключ лицензии';
$string['licensekey_help'] = 'Введите ваш лицензионный ключ провайдера ИИ Datacurso.';
$string['licensekey_missing'] = 'Лицензионный ключ не настроен';
$string['link_consumptionhistory'] = 'История потребления кредитов';
$string['link_generalreport'] = 'Общий отчет';
$string['link_generalreport_datacurso'] = 'Общий отчет Datacurso ИИ';
$string['link_listplugings'] = 'Список плагинов Datacurso';
$string['link_plugin'] = 'Ссылка';
$string['link_provider_config'] = 'Настройка провайдера';
$string['link_report_statistic'] = 'Отчет общей статистики';
$string['message_no_there_plugins'] = 'Нет доступных плагинов';
$string['minute'] = 'минута';
$string['minutes'] = 'Минуты';
$string['month'] = 'месяц';
$string['months'] = 'Месяцы';
$string['notenoughtokens'] = 'Недостаточно кредитов ИИ. Пожалуйста, посетите <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Управление кредитами</a> в магазине Datacurso, чтобы выделить или купить больше кредитов. Или свяжитесь с вашим администратором.';
$string['of'] = 'из';
$string['plugin'] = 'Плагин';
$string['plugindesc_assign_ai'] = 'Проверка заданий с помощью ИИ.';
$string['plugindesc_coursegen'] = 'Создание полных курсов, активностей и ресурсов с помощью ИИ.';
$string['plugindesc_datacurso_ratings'] = 'Позволяет студентам оценивать активности и ресурсы; преподаватели и администраторы могут затем генерировать анализ курсов на основе ИИ.';
$string['plugindesc_dttutor'] = 'Общение с ИИ-тьютором внутри курса.';
$string['plugindesc_forum_ai'] = 'Расширение форумов с помощью анализа на основе ИИ для автоматического создания резюме.';
$string['plugindesc_lifestory'] = 'Отчет и анализ академического прогресса студента на основе ИИ.';
$string['plugindesc_smartrules'] = 'Создание автоматизированных активностей на основе предыдущих условий студентов.';
$string['plugindesc_socialcert'] = 'Автоматическое создание персонализированных сертификатов при завершении курса.';
$string['pluginname'] = 'Провайдер ИИ Datacurso';
$string['pluginname_assign_ai'] = 'Задание ИИ';
$string['pluginname_coursegen'] = 'Создатель курсов ИИ';
$string['pluginname_datacurso_ratings'] = 'Рейтинг активностей ИИ';
$string['pluginname_dttutor'] = 'Тьютор ИИ';
$string['pluginname_forum_ai'] = 'Форум ИИ';
$string['pluginname_lifestory'] = 'История жизни студента ИИ';
$string['pluginname_smartrules'] = 'SmartRules ИИ';
$string['pluginname_socialcert'] = 'Поделиться сертификатом ИИ';
$string['privacy:metadata'] = 'Плагин Провайдер ИИ Datacurso не хранит никаких персональных данных локально. Все данные обрабатываются внешними сервисами ИИ Datacurso.';
$string['privacy:metadata:aiprovider_datacurso'] = 'Полезные нагрузки запросов ИИ Datacurso, отправляемые во внешний сервис.';
$string['privacy:metadata:aiprovider_datacurso:externalpurpose'] = 'Эти данные отправляются в Datacurso ИИ для выполнения запрошенного действия.';
$string['privacy:metadata:aiprovider_datacurso:numberimages'] = 'Общее количество изображений, запрошенных у сервиса ИИ.';
$string['privacy:metadata:aiprovider_datacurso:prompt'] = 'Текст запроса, предоставленный сервису ИИ.';
$string['privacy:metadata:aiprovider_datacurso:userid'] = 'ID пользователя Moodle, делающего запрос к ИИ.';
$string['privacy:metadata:aiprovider_datacurso_rlimit'] = 'Локально хранимый скользящий статус использования лимита частоты на пользователя и на сервис.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:lastsync'] = 'Метка времени последней синхронизации с удаленной историей.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:serviceid'] = 'Идентификатор сервиса (например, local_coursegen).';
$string['privacy:metadata:aiprovider_datacurso_rlimit:timecreated'] = 'Время создания этой записи.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:timemodified'] = 'Время последнего изменения этой записи.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:tokensused'] = 'Кредиты, использованные в текущем временном окне.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:userid'] = 'ID пользователя, связанный с отслеживаемым окном потребления.';
$string['privacy:metadata:aiprovider_datacurso_rlimit:windowstart'] = 'Метка времени начала окна, используемая для расчета лимитов потребления.';
$string['privacy:metadata:aiprovider_datacurso_userlimit'] = 'Локально хранимые квоты токенов Datacurso на пользователя.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:countfrom'] = 'Метка времени, отмечающая, когда квота начала отслеживать использование.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:lastsync'] = 'Время последней синхронизации информации об использовании.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:timecreated'] = 'Время создания записи квоты.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:timemodified'] = 'Время последнего обновления записи квоты.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:tokenlimit'] = 'Максимальное количество токенов, предоставленных пользователю.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:tokensused'] = 'Токены, потребленные с момента начала отслеживания.';
$string['privacy:metadata:aiprovider_datacurso_userlimit:userid'] = 'ID пользователя, связанный с квотой.';
$string['privacy:metadata:aiprovider_datacurso_consumption'] = 'Локальная копия внешней истории потребления кредитов, синхронизируемая по требованию для отчётов.';
$string['privacy:metadata:aiprovider_datacurso_consumption:userid'] = 'Пользователь Moodle, инициировавший потребление.';
$string['privacy:metadata:aiprovider_datacurso_consumption:service'] = 'Идентификатор сервиса (например, local_coursegen).';
$string['privacy:metadata:aiprovider_datacurso_consumption:action'] = 'Идентификатор действия.';
$string['privacy:metadata:aiprovider_datacurso_consumption:credits'] = 'Использованные кредиты.';
$string['privacy:metadata:aiprovider_datacurso_consumption:balance'] = 'Оставшийся баланс после потребления.';
$string['privacy:metadata:aiprovider_datacurso_consumption:timecreated'] = 'Отметка времени потребления.';
$string['ratelimit_creditperaction'] = 'Credits per action';
$string['ratelimit_creditperaction_desc'] = 'Estimated maximum credits a single action of this plugin can cost. A request is blocked upfront when the credits remaining in the window are fewer than this value.';
$string['ratelimit_creditperaction_help'] = 'Estimated maximum credits a single action of this plugin can cost. A request is blocked upfront when the credits remaining in the window are fewer than this value.';
$string['ratelimit_enable'] = 'Включить лимит';
$string['ratelimit_enable_desc'] = 'Если включено, для этого плагина будет применяться лимит кредитов на пользователя.';
$string['ratelimit_limit'] = 'Кредиты за окно';
$string['ratelimit_limit_help'] = 'Максимальное количество запросов, разрешенных в пределах окна лимита частоты.';
$string['ratelimit_window'] = 'Временное окно';
$string['ratelimit_window_help'] = 'Select the duration and unit for the rate limit window.';
$string['ratelimit_window_unit'] = 'Единица окна';
$string['ratelimit_window_value'] = 'Значение окна';
$string['read_context_course'] = 'Чтение контекста для создания курса с ИИ';
$string['read_context_course_model'] = 'Загрузка академической модели для создания курса с ИИ';
$string['remainingtokens'] = 'Остаток баланса';
$string['responseinvalidai'] = 'Недопустимый ответ от сервиса ИИ.';
$string['responseinvalidaimage'] = 'Недопустимый ответ от сервиса ИИ (нет изображения).';
$string['responseinvalidaimagecreate'] = 'Не удалось создать файл изображения.';
$string['second'] = 'секунда';
$string['seconds'] = 'Секунды';
$string['service'] = 'Сервис';
$string['tokens'] = 'Кредиты';
$string['tokens_available'] = 'Доступные кредиты';
$string['tokensconsumed'] = 'Потребленные кредиты';
$string['tokensconsumedday'] = 'Кредиты, потребленные за день';
$string['tokensconsumedmonth'] = 'Кредиты, потребленные за месяц';
$string['tokensused'] = 'Использованные кредиты';
$string['total_consumed'] = 'Потребленные кредиты';
$string['total_user_consumed'] = 'Total credits consumed by user';
$string['userid'] = 'Пользователь';
$string['warningconfig_instance'] = 'Предупреждение: Для правильного использования с этим провайдером должен быть создан только один экземпляр.';
$string['week'] = 'неделя';
$string['weeks'] = 'Недели';
$string['year'] = 'год';
$string['years'] = 'Годы';
