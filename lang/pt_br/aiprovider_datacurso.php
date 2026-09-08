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

$string['action'] = 'Ação';
$string['action:explain_text:endpoint'] = 'Endpoint para explicação de texto';
$string['action:explain_text:model'] = 'Modelo para a explicação';
$string['action:explain_text:model_help'] = 'Selecione o modelo que irá gerar as explicações.';
$string['action:explain_text:systeminstruction'] = 'Instrução de sistema para explicação';
$string['action:explain_text:systeminstruction_help'] = 'Forneça contexto para guiar o processo de explicação.';
$string['action:generate_image:endpoint'] = 'Endpoint da API';
$string['action:generate_image:endpoint_desc'] = 'O endpoint para gerar imagens';
$string['action:generate_image:model'] = 'Modelo para geração de imagem';
$string['action:generate_image:model_help'] = 'Selecione o modelo de IA para gerar imagens.';
$string['action:generate_image:systeminstruction'] = 'Prompt de sistema para geração de imagem';
$string['action:generate_image:systeminstruction_help'] = 'Instruções adicionais que guiam a IA na geração da imagem desejada.';
$string['action:generate_text:endpoint'] = 'Endpoint da API';
$string['action:generate_text:endpoint_desc'] = 'O endpoint para gerar texto';
$string['action:generate_text:instruction'] = 'Instrução do sistema';
$string['action:generate_text:instruction_desc'] = 'Esta instrução é enviada ao modelo de IA junto com o prompt do usuário. Não é recomendado editar esta instrução, a menos que seja absolutamente necessário.';
$string['action:generate_text:model'] = 'Modelo para geração de texto';
$string['action:generate_text:model_help'] = 'Selecione qual modelo de IA será usado para gerar o texto.';
$string['action:generate_text:systeminstruction'] = 'Instrução de sistema';
$string['action:generate_text:systeminstruction_help'] = 'Instrução ou contexto dado à IA antes de gerar o texto. Útil para controlar tom, estrutura ou propósito da resposta.';
$string['action:summarise_text:endpoint'] = 'Endpoint da API';
$string['action:summarise_text:endpoint_desc'] = 'O endpoint para gerar texto';
$string['action:summarise_text:instruction'] = 'Instrução do sistema';
$string['action:summarise_text:instruction_desc'] = 'Esta instrução é enviada ao modelo de IA junto com o prompt do usuário. Não é recomendado editar esta instrução, a menos que seja absolutamente necessário.';
$string['action:summarise_text:model'] = 'Modelo para resumo';
$string['action:summarise_text:model_help'] = 'Selecione qual modelo de IA será usado para resumir o texto.';
$string['action:summarise_text:systeminstruction'] = 'Instrução de sistema para resumo';
$string['action:summarise_text:systeminstruction_help'] = 'Contexto opcional para influenciar como o resumo é gerado.';
$string['action_activity_image'] = 'Activity with image';
$string['action_activity_noimage'] = 'Activity without image';
$string['action_course_image'] = 'Course with image';
$string['action_course_noimage'] = 'Course without image';
$string['action_default'] = 'Credits per action';
$string['action_image'] = 'Generate image';
$string['action_text'] = 'Generate text / summary';
$string['all'] = 'Todos';
$string['alt_datacurso_icon'] = 'Ícone do Datacurso';
$string['chart_actions'] = 'Distribuição de créditos por serviço';
$string['chart_tokens_by_day'] = 'Consumo de créditos por dia';
$string['chart_tokens_by_month'] = 'Número de créditos consumidos por mês';
$string['chart_user_consumption'] = 'Credits consumed by user per service';
$string['connection'] = 'Configurações de conexão';
$string['create_activity_assign_image'] = 'Criar tarefa com IA (com imagens)';
$string['create_activity_assign_noimage'] = 'Criar tarefa com IA (sem imagens)';
$string['create_activity_book_image'] = 'Criar livro com IA (com imagens)';
$string['create_activity_book_noimage'] = 'Criar livro com IA (sem imagens)';
$string['create_activity_choice_image'] = 'Criar enquete com IA (com imagens)';
$string['create_activity_choice_noimage'] = 'Criar enquete com IA (sem imagens)';
$string['create_activity_data_image'] = 'Criar banco de dados com IA (com imagens)';
$string['create_activity_data_noimage'] = 'Criar banco de dados com IA (sem imagens)';
$string['create_activity_feedback_image'] = 'Criar pesquisa com IA (com imagens)';
$string['create_activity_feedback_noimage'] = 'Criar pesquisa com IA (sem imagens)';
$string['create_activity_folder_image'] = 'Criar pasta com IA (com imagens)';
$string['create_activity_folder_noimage'] = 'Criar pasta com IA (sem imagens)';
$string['create_activity_forum_image'] = 'Criar fórum com IA (com imagens)';
$string['create_activity_forum_noimage'] = 'Criar fórum com IA (sem imagens)';
$string['create_activity_glossary_image'] = 'Criar glossário com IA (com imagens)';
$string['create_activity_glossary_noimage'] = 'Criar glossário com IA (sem imagens)';
$string['create_activity_h5pactivity_image'] = 'Criar atividade H5P com IA (com imagens)';
$string['create_activity_h5pactivity_noimage'] = 'Criar atividade H5P com IA (sem imagens)';
$string['create_activity_imscp_image'] = 'Criar pacote IMS com IA (com imagens)';
$string['create_activity_imscp_noimage'] = 'Criar pacote IMS com IA (sem imagens)';
$string['create_activity_label_image'] = 'Criar rótulo com IA (com imagens)';
$string['create_activity_label_noimage'] = 'Criar rótulo com IA (sem imagens)';
$string['create_activity_lesson_image'] = 'Criar lição com IA (com imagens)';
$string['create_activity_lesson_noimage'] = 'Criar lição com IA (sem imagens)';
$string['create_activity_page_image'] = 'Criar página com IA (com imagens)';
$string['create_activity_page_noimage'] = 'Criar página com IA (sem imagens)';
$string['create_activity_quiz_image'] = 'Criar questionário com IA (com imagens)';
$string['create_activity_quiz_noimage'] = 'Criar questionário com IA (sem imagens)';
$string['create_activity_resource_image'] = 'Criar arquivo/recurso com IA (com imagens)';
$string['create_activity_resource_noimage'] = 'Criar arquivo/recurso com IA (sem imagens)';
$string['create_activity_scorm_image'] = 'Criar pacote SCORM com IA (com imagens)';
$string['create_activity_scorm_noimage'] = 'Criar pacote SCORM com IA (sem imagens)';
$string['create_activity_url_image'] = 'Criar URL com IA (com imagens)';
$string['create_activity_url_noimage'] = 'Criar URL com IA (sem imagens)';
$string['create_activity_wiki_image'] = 'Criar wiki com IA (com imagens)';
$string['create_activity_wiki_noimage'] = 'Criar wiki com IA (sem imagens)';
$string['create_activity_workshop_image'] = 'Criar oficina com IA (com imagens)';
$string['create_activity_workshop_noimage'] = 'Criar oficina com IA (sem imagens)';
$string['curlerror'] = 'Erro cURL da API Datacurso: {$a}';
$string['custom_model_name'] = 'Nome de modelo personalizado';
$string['custom_model_name_help'] = 'Nome opcional para identificar esta configuração específica de modelo de IA.';
$string['datacurso:manage'] = 'Gerenciar configurações do provedor de IA';
$string['datacurso:use'] = 'Usar serviços de IA Datacurso';
$string['datacurso:viewreports'] = 'Ver relatórios de uso de IA';
$string['day'] = 'dia';
$string['days'] = 'Dias';
$string['description'] = 'Descrição';
$string['descriptionpagelistplugins'] = 'Aqui você pode encontrar a lista de plugins compatíveis com o provedor Datacurso';
$string['emptyprompt'] = 'Prompt vazio';
$string['emptyresponse'] = 'Sem resposta da API Datacurso.';
$string['endpointurl'] = 'URL do Endpoint';
$string['endpointurl_help'] = 'URL do endpoint base da API do Provedor de IA Datacurso. Geralmente algo como https://api.datacurso.ai/v1/.';
$string['entity_consumption'] = 'Consumo';
$string['error_ratelimit_exceeded'] = 'O limite de consumo permitido foi excedido. Por favor, tente novamente às {$a}.';
$string['errorgetbalancecredits'] = 'Não foi possível recuperar o saldo de créditos da API externa';
$string['errorgetusers'] = 'Não foi possível recuperar a lista de usuários. Por favor, tente novamente mais tarde.';
$string['filter_year'] = 'Year';
$string['forbidden'] = 'Você não tem permissão para executar esta ação com a licença atual. Por favor, verifique sua licença e créditos disponíveis em <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Gerenciar Créditos</a> na Loja Datacurso.';
$string['generate_activitie'] = 'Gerar atividade ou recurso com IA';
$string['generate_ai_reinforcement_activity'] = 'Criar atividade de reforço com IA';
$string['generate_analysis_comments'] = 'Gerar análise de avaliação de uma atividade/recurso com IA';
$string['generate_analysis_course'] = 'Gerar análise de avaliação do curso com IA';
$string['generate_analysis_general'] = 'Gerar análise de avaliação geral com IA';
$string['generate_analysis_story_student'] = 'Gerar análise da história do aluno com IA';
$string['generate_assign_answer'] = 'Gerar revisão de tarefa com IA';
$string['generate_certificate_answer'] = 'Gerar mensagem de certificado com IA';
$string['generate_chat_embeddings'] = 'Histórico de conversa IA';
$string['generate_chat_message'] = 'Gerar mensagem de tutor de IA';
$string['generate_chat_stream'] = 'Resposta IA';
$string['generate_creation_course'] = 'Criar curso completo com IA';
$string['generate_creation_course_image'] = 'Criar curso completo com IA (com imagens)';
$string['generate_creation_course_noimage'] = 'Criar curso completo com IA (sem imagens)';
$string['generate_forum_chat'] = 'Gerar resposta de fórum com IA';
$string['generate_forum_grade'] = 'Avaliar fórum com IA';
$string['generate_image'] = 'Gerar imagem com IA';
$string['generate_plan_course'] = 'Gerar plano de criação de curso com IA';
$string['generate_summary'] = 'Gerar resumo com IA';
$string['generate_text'] = 'Gerar texto com IA';
$string['goto'] = 'Ir para o Relatório';
$string['hour'] = 'hora';
$string['hours'] = 'Horas';
$string['httperror'] = 'Erro inesperado ao processar sua solicitação (HTTP {$a}). Por favor, tente novamente mais tarde. Se o problema persistir, entre em contato com o administrador do site.';
$string['id'] = 'ID';
$string['installed'] = 'Instalado';
$string['instance_disabled'] = 'A instância do provedor Datacurso está desativada';
$string['invalidjson'] = 'JSON Inválido';
$string['invalidlicensekey'] = 'A chave de licença expirou ou não é válida. Por favor, acesse <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Gerenciar Créditos</a> na Loja Datacurso para renovar ou comprar uma nova licença.';
$string['jsondecodeerror'] = 'Erro ao processar resposta da API Datacurso: {$a}';
$string['license_not_allowed'] = 'Sua licença não permite executar esta solicitação. Por favor, gerencie suas licenças e créditos em <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Gerenciar Créditos</a> na Loja Datacurso.';
$string['licensekey'] = 'Chave de licença';
$string['licensekey_help'] = 'Insira sua chave de licença do provedor de IA Datacurso.';
$string['licensekey_missing'] = 'A chave de licença não está configurada';
$string['link_consumptionhistory'] = 'Histórico de consumo de créditos';
$string['link_generalreport'] = 'Relatório geral';
$string['link_generalreport_datacurso'] = 'Relatório geral Datacurso IA';
$string['link_listplugings'] = 'Lista de plugins Datacurso';
$string['link_plugin'] = 'Link';
$string['link_provider_config'] = 'Configuração do provedor';
$string['link_report_statistic'] = 'Relatório de estatísticas gerais';
$string['message_no_there_plugins'] = 'Nenhum plugin disponível';
$string['minute'] = 'minuto';
$string['minutes'] = 'Minutos';
$string['month'] = 'mês';
$string['months'] = 'Meses';
$string['notenoughtokens'] = 'Créditos de IA insuficientes. Por favor, visite <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Gerenciar Créditos</a> na Loja Datacurso para alocar ou comprar mais créditos. Ou entre em contato com seu administrador.';
$string['of'] = 'de';
$string['plugin'] = 'Plugin';
$string['plugindesc_assign_ai'] = 'Revisar tarefas com assistência de IA.';
$string['plugindesc_coursegen'] = 'Criar cursos completos, atividades e recursos com IA.';
$string['plugindesc_datacurso_ratings'] = 'Permite que os alunos avaliem atividades e recursos; professores e administradores podem gerar análises de cursos baseadas em IA.';
$string['plugindesc_dttutor'] = 'Conversar com um tutor de IA dentro do curso.';
$string['plugindesc_forum_ai'] = 'Estender fóruns com análise alimentada por IA para gerar resumos automaticamente.';
$string['plugindesc_lifestory'] = 'Relatório e análise alimentados por IA do progresso acadêmico do aluno.';
$string['plugindesc_smartrules'] = 'Criar atividades automatizadas com base em condições anteriores dos alunos.';
$string['plugindesc_socialcert'] = 'Gerar automaticamente certificados personalizados ao concluir o curso.';
$string['pluginname'] = 'Provedor de IA Datacurso';
$string['pluginname_assign_ai'] = 'Tarefa IA';
$string['pluginname_coursegen'] = 'Criador de Curso IA';
$string['pluginname_datacurso_ratings'] = 'Classificação de Atividades IA';
$string['pluginname_dttutor'] = 'Tutor IA';
$string['pluginname_forum_ai'] = 'Fórum IA';
$string['pluginname_lifestory'] = 'História de Vida do Aluno IA';
$string['pluginname_smartrules'] = 'SmartRules IA';
$string['pluginname_socialcert'] = 'Compartilhar Certificado IA';
$string['privacy:metadata'] = 'O plugin Provedor de IA Datacurso armazena uma cópia local do histórico de consumo de créditos de cada usuário para os relatórios do administrador, e envia aos serviços externos da Datacurso os dados de solicitação necessários para cumprir cada ação de IA. Os dados enviados à Datacurso são retidos pela Datacurso de acordo com seus termos de tratamento de dados; as solicitações de exclusão de dados remotos são atualmente tratadas através do suporte da Datacurso.';
$string['privacy:metadata:aiprovider_datacurso'] = 'Provedor de IA Datacurso';
$string['privacy:metadata:aiprovider_datacurso_consumption'] = 'Cópia local do histórico externo de consumo de créditos, sincronizada sob demanda para relatórios.';
$string['privacy:metadata:aiprovider_datacurso_consumption:action'] = 'Identificador da ação.';
$string['privacy:metadata:aiprovider_datacurso_consumption:balance'] = 'Saldo restante após o consumo.';
$string['privacy:metadata:aiprovider_datacurso_consumption:credits'] = 'Créditos consumidos.';
$string['privacy:metadata:aiprovider_datacurso_consumption:externalid'] = 'O identificador do registro de consumo na Loja Datacurso.';
$string['privacy:metadata:aiprovider_datacurso_consumption:service'] = 'Identificador do serviço (ex.: local_coursegen).';
$string['privacy:metadata:aiprovider_datacurso_consumption:timecreated'] = 'Carimbo de data/hora do consumo.';
$string['privacy:metadata:aiprovider_datacurso_consumption:userid'] = 'O usuário do Moodle que originou o consumo.';
$string['privacy:metadata:datacurso_ai_services'] = 'Serviços de IA Datacurso (plugins-ai / eu.plugins-ai): recebem os dados de solicitação necessários para gerar texto, resumos e imagens, e para fornecer os recursos de IA dos plugins Datacurso. A Datacurso retém esses dados de acordo com seus termos de tratamento de dados; as solicitações de exclusão são atualmente tratadas através do suporte da Datacurso.';
$string['privacy:metadata:datacurso_ai_services:file'] = 'O conteúdo, o nome e o tipo MIME dos arquivos enviados ao serviço de IA em nome do usuário.';
$string['privacy:metadata:datacurso_ai_services:lang'] = 'O idioma do usuário que faz a solicitação.';
$string['privacy:metadata:datacurso_ai_services:licensekey'] = 'A chave de licença do site, enviada como cabeçalho da solicitação para autenticar o site.';
$string['privacy:metadata:datacurso_ai_services:messages'] = 'A conversa enviada ao modelo: a instrução do sistema e o prompt do usuário.';
$string['privacy:metadata:datacurso_ai_services:model'] = 'O modelo de IA solicitado.';
$string['privacy:metadata:datacurso_ai_services:n'] = 'O número de imagens solicitadas.';
$string['privacy:metadata:datacurso_ai_services:prompt'] = 'O texto do prompt fornecido pelo usuário para a geração de imagens.';
$string['privacy:metadata:datacurso_ai_services:ratelimit'] = 'O limite de créditos por usuário configurado para o serviço, enviado como cabeçalhos da solicitação para que o serviço possa aplicá-lo.';
$string['privacy:metadata:datacurso_ai_services:site_id'] = 'Um identificador aleatório deste site Moodle, usado para manter os contadores por usuário separados entre os sites.';
$string['privacy:metadata:datacurso_ai_services:site_url'] = 'A URL deste site Moodle.';
$string['privacy:metadata:datacurso_ai_services:size'] = 'O tamanho de imagem solicitado.';
$string['privacy:metadata:datacurso_ai_services:timezone'] = 'O fuso horário do usuário que faz a solicitação.';
$string['privacy:metadata:datacurso_ai_services:userid'] = 'O ID do usuário Moodle que faz a solicitação de IA.';
$string['privacy:metadata:datacurso_course_service'] = 'Serviço de criação de cursos Datacurso (course-ai-v2): recebe os dados necessários para gerar cursos, atividades e recursos com IA. A Datacurso retém esses dados de acordo com seus termos de tratamento de dados; as solicitações de exclusão são atualmente tratadas através do suporte da Datacurso.';
$string['privacy:metadata:datacurso_course_service:file'] = 'O conteúdo, o nome e o tipo MIME dos arquivos (como uma ementa) enviados ao serviço de cursos em nome do usuário.';
$string['privacy:metadata:datacurso_course_service:lang'] = 'O idioma do usuário que faz a solicitação.';
$string['privacy:metadata:datacurso_course_service:licensekey'] = 'A chave de licença do site, enviada como cabeçalho da solicitação para autenticar o site.';
$string['privacy:metadata:datacurso_course_service:payload'] = 'Os dados da solicitação de geração de curso fornecidos pelo usuário (instruções, contexto do curso e opções).';
$string['privacy:metadata:datacurso_course_service:ratelimit'] = 'Os cabeçalhos de limite de créditos configurados pelo administrador (indicador de ativação, limite, janela e custo estimado); não são dados pessoais.';
$string['privacy:metadata:datacurso_course_service:site_id'] = 'Um identificador aleatório deste site Moodle, usado para manter os contadores por usuário separados entre os sites.';
$string['privacy:metadata:datacurso_course_service:site_url'] = 'A URL deste site Moodle.';
$string['privacy:metadata:datacurso_course_service:timezone'] = 'O fuso horário do usuário que faz a solicitação.';
$string['privacy:metadata:datacurso_course_service:userid'] = 'O ID do usuário Moodle que faz a solicitação.';
$string['privacy:metadata:datacurso_shop'] = 'Loja Datacurso (shop.datacurso.com): valida a licença e mantém o livro-razão de consumo de créditos por usuário (ID do usuário, serviço, ação, créditos, saldo e data), que retorna a este site para o relatório de consumo local.';
$string['privacy:metadata:datacurso_shop:licensekey'] = 'A chave de licença do site, enviada como cabeçalho da solicitação para autenticar o site.';
$string['ratelimit_creditperaction'] = 'Credits per action';
$string['ratelimit_creditperaction_desc'] = 'Estimated maximum credits a single action of this plugin can cost. A request is blocked upfront when the credits remaining in the window are fewer than this value.';
$string['ratelimit_creditperaction_help'] = 'Estimated maximum credits a single action of this plugin can cost. A request is blocked upfront when the credits remaining in the window are fewer than this value.';
$string['ratelimit_enable'] = 'Habilitar limite de taxa';
$string['ratelimit_enable_desc'] = 'Se habilitado, o limite de créditos por usuário será aplicado para este plugin.';
$string['ratelimit_limit'] = 'Créditos por janela';
$string['ratelimit_limit_help'] = 'Número máximo de créditos que um usuário pode consumir dentro da janela de tempo selecionada. 0 para ilimitado.';
$string['ratelimit_window'] = 'Janela de tempo';
$string['ratelimit_window_help'] = 'Select the duration and unit for the rate limit window.';
$string['ratelimit_window_unit'] = 'Unidade da janela';
$string['ratelimit_window_value'] = 'Valor da janela';
$string['read_context_course'] = 'Ler contexto para criação de curso com IA';
$string['read_context_course_model'] = 'Carregar modelo acadêmico para criação de curso com IA';
$string['remainingtokens'] = 'Saldo restante';
$string['responseinvalidai'] = 'Resposta inválida do serviço de IA.';
$string['responseinvalidaimage'] = 'Resposta inválida do serviço de IA (sem imagem).';
$string['responseinvalidaimagecreate'] = 'Não foi possível criar o arquivo de imagem.';
$string['second'] = 'segundo';
$string['seconds'] = 'Segundos';
$string['service'] = 'Serviço';
$string['serviceunavailable'] = 'O serviço de IA está temporariamente indisponível. Por favor, tente novamente mais tarde. Se o problema persistir, entre em contato com o administrador do seu site.';
$string['tokens'] = 'Créditos';
$string['tokens_available'] = 'Créditos disponíveis';
$string['tokensconsumed'] = 'Créditos consumidos';
$string['tokensconsumedday'] = 'Créditos consumidos por dia';
$string['tokensconsumedmonth'] = 'Créditos consumidos por mês';
$string['tokensused'] = 'Créditos usados';
$string['total_consumed'] = 'Créditos consumidos';
$string['total_user_consumed'] = 'Total credits consumed by user';
$string['userid'] = 'Usuário';
$string['warningconfig_instance'] = 'Aviso: Apenas uma instância deve ser criada com este provedor para uso adequado.';
$string['week'] = 'semana';
$string['weeks'] = 'Semanas';
$string['year'] = 'ano';
$string['years'] = 'Anos';
