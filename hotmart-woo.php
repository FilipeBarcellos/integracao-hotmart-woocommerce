<?php
/*
Plugin Name: WooCommerce - Integrador de compras hotmart
Description: Plugin para integrar as compras feitas na hotmart com o WooCommerce da Academia do Importador.
Author: Filipe Barcellos
Version: 1.0
*/

/**
 * Registra mensagens de erro em um arquivo de log se o registro estiver habilitado nas opções do plugin.
 * Agora inclui a capacidade de registrar contexto extra, como IDs de transação ou detalhes do usuário.
 *
 * @param string $message Mensagem de erro para registrar.
 * @param bool $log_raw_data Se deve registrar dados brutos.
 * @param bool $is_critical Se o erro é crítico.
 * @param array|string $extra_context Contexto extra para adicionar ao log.
 */
function hotmart_log_error($message, $log_raw_data = false, $is_critical = false, $extra_context = '') {
    if (get_option('hotmart_logging_enabled', 'no') === 'yes') {
        if ($log_raw_data && get_option('hotmart_log_raw_data', 'no') !== 'yes') {
            return; // Não registra se a opção de dados brutos não estiver habilitada.
        }
        $log_file_path = get_option('hotmart_log_file_path', plugin_dir_path(__FILE__) . 'hotmart.log');
        if (!$log_file_path) {
            $log_file_path = plugin_dir_path(__FILE__) . 'hotmart.log';
        }
      
        $date = date("Y-m-d H:i:s");
        $log_entry = sprintf("[%s] %s", $date, is_array($message) || is_object($message) ? print_r($message, true) : $message);

        // Adiciona contexto extra ao log, se fornecido
        if (!empty($extra_context)) {
            $log_entry .= " | Contexto Extra: " . (is_array($extra_context) || is_object($extra_context) ? print_r($extra_context, true) : $extra_context);
        }

        $log_entry .= "\n";
        error_log($log_entry, 3, $log_file_path);
      
        if ($is_critical) {
            hotmart_send_error_email($message); // Chama a função para enviar o e-mail
        }   
    }
}

function hotmart_display_error_log() {
    $log_file_path = get_option('hotmart_log_file_path', plugin_dir_path(__FILE__) . 'hotmart.log');
    if (file_exists($log_file_path)) {
        echo "<h2>Log de Erros</h2>";
        echo "<textarea readonly rows='20' cols='100'>" . esc_textarea(file_get_contents($log_file_path)) . "</textarea>";
    } else {
        echo "<p>Arquivo de log não encontrado. Verifique o caminho ou as permissões.</p>";
    }
}

/**
 * Adiciona uma página de menu ao painel de administração do WordPress para o plugin.
 */
function hotmart_add_admin_menu() {
    add_menu_page('Webhook hotmart', 'Webhook hotmart', 'manage_options', 'hotmart_webhook', 'hotmart_options_page');
    add_submenu_page('hotmart_webhook', 'Log de Erros', 'Log de Erros', 'manage_options', 'hotmart_error_log', 'hotmart_display_error_log');
}

/**
 * Exibe a página de opções do plugin no painel de administração.
 */
function hotmart_options_page() {
    // Verifica se o usuário deseja limpar o log e tem a permissão para isso
    if (isset($_POST['hotmart_clear_log']) && check_admin_referer('hotmart_clear_log_action', 'hotmart_clear_log_nonce')) {
        $log_file_path = get_option('hotmart_log_file_path', plugin_dir_path(__FILE__) . 'hotmart.log');
        file_put_contents($log_file_path, '');
        echo "<div class='updated'><p>Log limpo.</p></div>";
    }
    ?>
    <div class="wrap">
        <h2>Configurações do Webhook hotmart</h2>
        <form action="options.php" method="post">
            <?php
            settings_fields('hotmart_logger_options');
            do_settings_sections('hotmart_logger');
            submit_button();
            ?>
        </form>
        <form action="" method="post">
            <?php
            wp_nonce_field('hotmart_clear_log_action', 'hotmart_clear_log_nonce');
            submit_button('Limpar Log', 'delete', 'hotmart_clear_log', false);
            ?>
        </form>
    </div>
    <?php
}

/**
 * Registra as configurações do plugin, como a opção de habilitar o registro de log
 * e o caminho do arquivo de log. Define as seções e campos na página de configurações.
 */
function hotmart_register_settings() {
    register_setting('hotmart_logger_options', 'hotmart_logging_enabled');
    register_setting('hotmart_logger_options', 'hotmart_log_file_path');
    add_settings_section('hotmart_logger_main', 'Configurações principais', 'hotmart_logger_section_text', 'hotmart_logger');
    add_settings_field('hotmart_logging_enabled', 'Habilitar registro', 'hotmart_logging_enabled_field', 'hotmart_logger', 'hotmart_logger_main');
    add_settings_field('hotmart_log_file_path', 'Caminho do arquivo de log', 'hotmart_log_file_path_field', 'hotmart_logger', 'hotmart_logger_main');
    add_settings_field('hotmart_log_contents', 'Conteúdo do Log', 'hotmart_log_contents_field', 'hotmart_logger', 'hotmart_logger_main');
  register_setting('hotmart_logger_options', 'hotmart_log_raw_data');
  add_settings_field('hotmart_log_raw_data', 'Registrar Dados Brutos', 'hotmart_log_raw_data_field', 'hotmart_logger', 'hotmart_logger_main');
      register_setting('hotmart_logger_options', 'hotmart_error_email');
    add_settings_field('hotmart_error_email', 'E-mail para Notificações de Erro', 'hotmart_error_email_field', 'hotmart_logger', 'hotmart_logger_main');

}
function hotmart_error_email_field() {
    $error_email = get_option('hotmart_error_email', '');
    echo "<input id='hotmart_error_email' name='hotmart_error_email' type='email' value='" . esc_attr($error_email) . "' />";
}

/**
 * Exibe texto introdutório para a seção de configurações principais.
 */
function hotmart_logger_section_text() {
    echo '<p>Configuração principal do Webhook hotmart.</p>';
}

/**
 * Campo para definir o caminho do arquivo de log na página de configurações.
 */
function hotmart_log_file_path_field() {
    $log_file_path = get_option('hotmart_log_file_path', plugin_dir_path(__FILE__) . 'hotmart.log');
    echo "<input id='hotmart_log_file_path' name='hotmart_log_file_path' type='text' value='" . esc_attr($log_file_path) . "' />";

}
function hotmart_log_raw_data_field() {
    $log_raw_data = get_option('hotmart_log_raw_data', 'no');
    echo "<input id='hotmart_log_raw_data' name='hotmart_log_raw_data' type='checkbox' " . checked('yes', $log_raw_data, false) . " value='yes'> Registrar dados brutos recebidos no log";
}
/**
 * Campo para exibir o conteúdo do arquivo de log na página de configurações.
 */
function hotmart_log_contents_field() {
    $log_file_path = get_option('hotmart_log_file_path', plugin_dir_path(__FILE__) . 'hotmart.log');
    if (file_exists($log_file_path)) {
        echo "<textarea readonly rows='10' cols='70'>" . esc_textarea(file_get_contents($log_file_path)) . "</textarea>";
    } else {
        echo "<p>Arquivo de log não encontrado. Verifique o caminho ou as permissões.</p>";
        echo "<textarea readonly rows='10' cols='70'>" . esc_textarea(file_get_contents($log_file_path)) . "</textarea>";

    }
}

/**
 * Campo para habilitar ou desabilitar o registro de log na página de configurações.
 */
function hotmart_logging_enabled_field() {
    $logging_enabled = get_option('hotmart_logging_enabled', 'no');
    echo "<input id='hotmart_logging_enabled' name='hotmart_logging_enabled' type='checkbox' " . checked('yes', $logging_enabled, false) . " value='yes'> ";
}

// Hooks para adicionar a página de menu e registrar as configurações no WordPress
add_action('admin_menu', 'hotmart_add_admin_menu');
add_action('admin_init', 'hotmart_register_settings');

/**
 * Divide um nome completo em primeiro e último nome.
 */
function split_full_name($full_name) {
    $parts = explode(' ', $full_name);
    $last_name = array_pop($parts);
    $first_name = implode(' ', $parts);
    return array($first_name, $last_name);
}
/**
 * Registra um endpoint da API REST para o webhook da hotmart.
 * Este endpoint será chamado para processar dados recebidos via POST.
 */
function hotmart_webhook_endpoint() {
    register_rest_route('hotmart-webhook/v1', '/process/', array(
        'methods' => 'POST',
        'callback' => 'hotmart_webhook_callback',
        'permission_callback' => '__return_true', // Permite que qualquer um chame este endpoint.
    ));
}
add_action('rest_api_init', 'hotmart_webhook_endpoint'); // Adiciona a ação ao inicializar a API REST.

/**
 * A função de callback que é chamada quando o endpoint do webhook é atingido.
 * Processa os dados recebidos e executa ações com base neles.
 */
function hotmart_webhook_callback(WP_REST_Request $request) {
    // Log dos dados brutos recebidos
    $data_raw = $request->get_body();
    hotmart_log_error("Dados brutos recebidos: " . $data_raw, true);

    // Obtém os dados JSON enviados para o webhook.
    $data = $request->get_json_params(); 
    if (!$data) {
        hotmart_log_error('No data provided in request.', false, true, ['Request Body' => $request->get_body()]);
        return new WP_REST_Response(array('message' => 'No data provided'), 400);
    }

    // Obtém o hottok da query string da URL
    $hottok_recebido = $request->get_param('hottok');

    // Seu hottok real (substitua pelo seu hottok)
    $hottok_esperado = 'SdRqVOJ1rCBJBgORfpAmavAYh0Nj3U76908';

    // Compara o hottok recebido com o esperado
    if ($hottok_recebido !== $hottok_esperado) {
        hotmart_log_error('Hottok inválido: ' . $hottok_recebido);
        return new WP_REST_Response(array('message' => 'Hottok inválido'), 403); // Retorna erro 403 Forbidden
    }

    // Definindo as variáveis $transactionId e $userDetails
    $transactionId = $data["purchase"]["transaction"];
    $userDetails = $data["buyer"];


    // Verifica se todos os campos necessários estão presentes nos dados.
    $required_keys = ["buyer", "product", "purchase", "event"];
    foreach ($required_keys as $key) {
        if (!isset($data[$key])) {
            hotmart_log_error("Missing data: $key in request.");
            return new WP_REST_Response(array('message' => "Missing data: $key"), 400);
        }
    }

    // Valida o formato dos dados recebidos.
    if (!is_array($data["buyer"]) || !is_array($data["product"]) || !is_array($data["purchase"])) {
        hotmart_log_error('Invalid data format in request.');
        return new WP_REST_Response(array('message' => 'Invalid data format'), 400);
    }

    // Valida e sanitiza o e-mail do cliente.
    $email = sanitize_email($data["buyer"]["email"]);
    if (!is_email($email)) {
        hotmart_log_error('Invalid email address provided: ' . $email);
        return new WP_REST_Response(array('message' => 'Invalid email address'), 400);
    }

    // Sanitiza e valida o nome completo do cliente.
    $full_name = sanitize_text_field($data["buyer"]["name"]);
    if (empty($full_name)) {
        hotmart_log_error('Full name is empty.');
        return new WP_REST_Response(array('message' => 'Full name is empty'), 400);
    }
    list($first_name, $last_name) = split_full_name($full_name); // Divide o nome completo em primeiro e último nome.
    $username = str_replace(' ', '', strtolower($full_name)); // Cria um nome de usuário a partir do nome completo, em minúsculas.

    // Verifica se o nome de usuário já existe e ajusta se necessário.
    if (username_exists($username)) {
        $suffix = 1;
        $new_username = $username . $suffix;
        while (username_exists($new_username)) {
            $suffix++;
            $new_username = $username . $suffix;
        }
        $username = $new_username;
    }

    $nickname = $full_name; // Define o apelido do usuário.
    $product_name = sanitize_text_field($data["product"]["name"]); // Sanitiza o nome do produto.
    $token = sanitize_text_field($request->get_header('authorization')); // Obtém o token de autorização do cabeçalho da requisição.

    // Processa a venda com base no status atual.
    $current_status = $data["event"];
    $transaction_id = $data["purchase"]["transaction"];

    if ($current_status == "PURCHASE_PROTEST" || $current_status == "PURCHASE_CHARGEBACK") {
        wc_custom_refund_order_by_transaction_id($transaction_id); // Processa o reembolso com base no número da transação.

      
    } elseif ($current_status == "PURCHASE_APPROVED") {
        $user = get_user_by('email', $email); // Obtém o usuário pelo e-mail.
        if (!$user) {
            // Se o usuário não existir, cria um novo.
            $password = wp_generate_password(); // Gera uma senha.
            $user_id = wp_create_user($username, $password, $email); // Cria o usuário.
            if (is_wp_error($user_id)) {
    hotmart_log_error("Error creating user: " . $user_id->get_error_message());
    return new WP_REST_Response(array('message' => 'Failed to create user'), 500);
}

            // Atualiza os dados do usuário com informações fornecidas.
            wp_update_user(array('ID' => $user_id, 'first_name' => $first_name, 'last_name' => $last_name, 'nickname' => $nickname, 'display_name' => $full_name));
            send_welcome_email($email, $first_name, $password); // Envia um e-mail de boas-vindas ao novo usuário.
            $order = wc_custom_create_order_hotmart(array('status' => 'completed', 'customer_id' => $user_id), $first_name, $email, $product_name, $transaction_id); // Cria um pedido para o novo usuário.
if (is_wp_error($order)) {
    hotmart_log_error("Erro ao criar pedido durante o webhook: " . $order->get_error_message(), false, true);
    return new WP_REST_Response(array('message' => 'Failed to create order'), 500);
}

        } else {
            // Se o usuário já existir, processa o pedido para o usuário existente.
            wc_custom_process_order_for_existing_user($user->ID, $product_name);
            send_product_available_email($user->user_email, $user->first_name, $product_name); // Envia um e-mail informando que o produto está disponível.
        }
    } else {
        // Se o status da venda for desconhecido, registra o erro e responde com falha.
        hotmart_log_error('Evento desconhecido: ' . $current_status);
        return new WP_REST_Response(array('message' => 'Evento desconhecido'), 400);
    }
if (is_wp_error($order)) {
    hotmart_log_error("Erro ao criar pedido durante o webhook: " . $order->get_error_message(), false, true);
    return new WP_REST_Response(array('message' => 'Failed to create order'), 500);
}

    // Se tudo ocorrer bem, responde com sucesso.
    return new WP_REST_Response(array('success' => true, 'message' => 'Processed successfully!'), 200);
}


/**
 * Cria um pedido no WooCommerce com base nos dados fornecidos.
 * Utilizado para criar um pedido quando um novo usuário é criado após uma compra.
 *
 * @param array $order_data Dados do pedido.
 * @param string $first_name Primeiro nome do cliente.
 * @param string $email E-mail do cliente.
 * @param string $product_name Nome do produto comprado.
 * @return WC_Order|WP_Error Retorna o objeto do pedido ou um erro se algo der errado.
 */
function wc_custom_create_order_hotmart($order_data, $first_name, $email, $product_name, $transaction_id) {
    // Define o endereço de cobrança usando o nome e e-mail fornecidos.
    $address = array(
        'first_name' => $first_name,
        'email'      => $email,
    );

    // Verifica se o produto existe
    $product = get_page_by_title($product_name, OBJECT, 'product');
    if (!$product) {
        $error_message = "Product not found: " . $product_name;
        hotmart_log_error($error_message, false, true); // Marca como erro crítico
        return new WP_Error('product_not_found', $error_message);
    }

    // Cria um novo pedido com os dados fornecidos.
    $order = wc_create_order($order_data);
    if (is_wp_error($order)) {
        hotmart_log_error("Erro ao criar pedido durante o webhook: " . $order->get_error_message(), false, true, ['Transaction ID' => $transaction_id, 'User Details' => ['email' => $email, 'first_name' => $first_name]]);
        return $order;
    }

    // Adiciona o produto encontrado ao pedido
if (!$order->add_product(wc_get_product($product->ID), 1)) {
    $error_message = "Error adding product to order: " . $product_name;
    hotmart_log_error($error_message, false, true);
    return new WP_Error('error_adding_product', $error_message);
}


    // Define o endereço de cobrança, calcula os totais e atualiza o status para 'completed'.
    $order->set_address($address, 'billing');
    $order->calculate_totals();
    if (!$order->update_status("completed", '[Compra pela hotmart]')) {
        $error_message = "Error updating order status for order ID: " . $order->get_id();
        hotmart_log_error($error_message, false, true);
        return new WP_Error('error_updating_status', $error_message);
    }

    // Armazena o número da transação da hotmart como metadado do pedido
    $order->update_meta_data('hotmart_transaction_id', $transaction_id);
    $order->save();

    return $order;
}

/**
 * Processa um pedido para um usuário existente.
 * Utilizado quando um usuário existente faz uma nova compra.
 *
 * @param int $user_id ID do usuário.
 * @param string $product_name Nome do produto comprado.
 */
function wc_custom_process_order_for_existing_user($user_id, $product_name) {
    // Verifica se o produto existe
    $product = get_page_by_title($product_name, OBJECT, 'product');
    if (!$product) {
        $error_message = "Product not found for existing user: " . $product_name;
        hotmart_log_error($error_message, false, true);
        return new WP_Error('product_not_found', $error_message);
    }

    // Cria um novo pedido
    $order = wc_create_order();
    if (is_wp_error($order)) {
        // Aqui você recupera as informações do usuário
        $user_info = get_userdata($user_id);
        $user_details = [
            'User ID' => $user_id,
            'Email' => $user_info->user_email,
            'Name' => $user_info->display_name
            // Outras informações que você achar relevantes
        ];
        hotmart_log_error("Error creating order for existing user: " . $order->get_error_message(), false, true, $user_details);
        return $order;
    }
  
    // Adiciona o produto ao pedido
    if (!$order->add_product(wc_get_product($product->ID), 1)) {
        $error_message = "Error adding product to order for existing user: " . $product_name;
        hotmart_log_error($error_message, false, true);
        return new WP_Error('error_adding_product', $error_message);
    }

    // Define o ID do cliente, calcula os totais e atualiza o status
    $order->set_customer_id($user_id);
    $order->calculate_totals();
    if (!$order->update_status('completed', 'Pedido completado automaticamente para usuário existente.', TRUE)) {
        $error_message = "Error updating order status for existing user: " . $user_id;
        hotmart_log_error($error_message, false, true);
        return new WP_Error('error_updating_status', $error_message);
    }

    return $order;
}

/**
 * Envia um e-mail de boas-vindas ao usuário com detalhes de login.
 *
 * @param string $email E-mail do usuário.
 * @param string $first_name Primeiro nome do usuário.
 * @param string $password Senha do usuário.
 */
function send_welcome_email($email, $first_name, $password) {
    // Define o assunto e a mensagem do e-mail.
    $subject = 'Bem-vindo ao nosso site!';
    $message = "Olá $first_name, Aqui estão seus detalhes de acesso:\nE-mail: $email\nSenha: $password\n\nAcesse agora em: https://academiadoimportador.com.br/cursos/wp-login.php e comece a aprender!";
    // Envia o e-mail.
    wp_mail($email, $subject, $message);
}

/**
 * Envia um e-mail ao usuário informando que um novo produto foi adicionado à sua conta.
 *
 * @param string $user_email E-mail do usuário.
 * @param string $user_name Nome do usuário.
 * @param string $product_name Nome do produto adicionado.
 */
function send_product_available_email($user_email, $user_name, $product_name) {
    // Define URLs úteis e o assunto e a mensagem do e-mail.
    $login_url = 'https://academiadoimportador.com.br/cursos/wp-login.php';
    $reset_password_url = 'https://academiadoimportador.com.br/cursos/wp-login.php?action=lostpassword';
    $instructions_url = 'https://academiadoimportador.com.br/login-academia-do-importador/';
    $subject = 'Seu novo curso foi adicionado à sua conta!';
    $message = "<p>Olá $user_name,</p>\n\n" .
               "<p>O curso '$product_name' foi adicionado à sua conta. Você já pode acessá-lo em sua área de membros.</p>\n\n" .
               "<p>Acesse a plataforma: <a href='$login_url'>$login_url</a></p>\n\n" .
               "<p>Se você não lembra seus dados de acesso, <a href='$reset_password_url'>clique aqui</a> para redefinir a sua senha ou veja as instruções no link a seguir: <a href='$instructions_url'>$instructions_url</a></p>\n\n" .
               "<p>Equipe</p>";
    // Envia o e-mail com o tipo de conteúdo definido para HTML.
    wp_mail($user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
}

/**
 * Processa um reembolso ou chargeback no WooCommerce com base no número da transação da hotmart.
 *
 * @param string $transaction_id Número da transação da hotmart.
 */
function wc_custom_refund_order_by_transaction_id($transaction_id) {
    // Procura por pedidos que contenham o metadado 'hotmart_transaction_id' igual ao $transaction_id.
    $orders = wc_get_orders(array(
        'meta_key' => 'hotmart_transaction_id',
        'meta_value' => $transaction_id,
        'status' => array('wc-completed', 'wc-processing'),
    ));

    if (empty($orders)) {
        hotmart_log_error("No orders found for transaction ID: " . $transaction_id, false, true); // Marca como erro crítico
        return;
    }
  /**
 * Envia um e-mail de notificação de erro crítico para o administrador do site.
 *
 * @param string $error_message A mensagem de erro a ser enviada.
 */
function hotmart_send_error_email($error_message) {
    $error_email = get_option('hotmart_error_email', get_option('admin_email')); // Usa o e-mail configurado ou o e-mail do administrador como padrão
    $subject = "Erro Crítico no Plugin hotmart";
    $body = "Um erro crítico ocorreu no plugin hotmart: \n\n" . $error_message;
    wp_mail($error_email, $subject, $body); // Envia o e-mail
}

    foreach ($orders as $order) {
        // Processa o reembolso para o pedido encontrado.
        $order->update_status('wc-refunded', 'Pedido reembolsado automaticamente devido a chargeback ou reembolso.');
    }
}
