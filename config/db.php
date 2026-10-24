<?php
// Fuso horário fixo em Brasília — sem isso, o PHP usa o padrão do servidor (muitas
// hospedagens usam UTC por padrão), o que bagunça horário de agendamento e do link
// de calendário. Ajuste aqui se sua empresa não for de SP/horário de Brasília.
date_default_timezone_set('America/Sao_Paulo');

// ============================================================
// CONFIGURAÇÃO DO BANCO DE DADOS - SUPABASE (PostgreSQL)
// ============================================================
// No Supabase: Project Settings > Database > Connection string > URI
// (ou "Connection parameters", que mostra host/porta/user/senha separados)
//
// IMPORTANTE: use o modo "Session pooler" ou "Transaction pooler" se sua
// hospedagem PHP não permitir IPv6 (a maioria das hospedagens compartilhadas
// não permite conexão direta por IPv6 ao Supabase). O host do pooler
// normalmente é algo como: aws-0-sa-east-1.pooler.supabase.com
//
// Também dá pra configurar por variáveis de ambiente (GDS_DB_HOST, GDS_DB_PORT,
// GDS_DB_NAME, GDS_DB_USER, GDS_DB_PASS, GDS_DB_SSLMODE) — se existirem, elas
// têm prioridade sobre os valores escritos abaixo.
// ============================================================
function gds_env($nome, $padrao) {
    $v = getenv($nome);
    return ($v === false || $v === '') ? $padrao : $v;
}

define('DB_HOST', gds_env('GDS_DB_HOST', 'SEU_HOST.pooler.supabase.com')); // <-- CONFIGURE AQUI
define('DB_PORT', gds_env('GDS_DB_PORT', '6543'));                          // 6543 = transaction pooler | 5432 = conexão direta
define('DB_NAME', gds_env('GDS_DB_NAME', 'postgres'));                      // no Supabase o banco padrão chama "postgres"
define('DB_USER', gds_env('GDS_DB_USER', 'postgres.SEU_PROJECT_REF'));      // <-- CONFIGURE AQUI (formato do pooler)
define('DB_PASS', gds_env('GDS_DB_PASS', 'SUA_SENHA_DO_BANCO'));            // <-- CONFIGURE AQUI
define('DB_SSLMODE', gds_env('GDS_DB_SSLMODE', 'require'));                 // Supabase exige SSL

// Mostrar detalhes de erro na tela? Deixe false em produção (o erro vai pro log do servidor).
define('APP_DEBUG', gds_env('GDS_DEBUG', '0') === '1');

// ============================================================
// CHAVES DE INTEGRAÇÃO - CONFIGURE AQUI
// (o ideal é configurar pela tela Admin > Configurações, que grava no banco)
// ============================================================
define('IA_PROVIDER', 'gemini');               // gemini | anthropic
define('IA_API_KEY', 'SUA_CHAVE_DE_API_AQUI'); // <-- CONFIGURE AQUI
define('MERCADOPAGO_ACCESS_TOKEN', '');        // <-- CONFIGURE AQUI
define('WHATSAPP_API_TOKEN', '');              // <-- CONFIGURE AQUI

ini_set('display_errors', APP_DEBUG ? '1' : '0');
error_reporting(E_ALL);

try {
    $pdo = new PDO(
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=" . DB_SSLMODE,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Pooler em modo "transaction" do Supabase não suporta prepared statements
            // nomeados do lado do servidor — emular evita erros intermitentes.
            PDO::ATTR_EMULATE_PREPARES => true,
        ]
    );
} catch (PDOException $e) {
    // Nunca mostra host/usuário/senha pro visitante — só no log (ou na tela em modo debug).
    error_log('GDS - erro de conexão com o banco: ' . $e->getMessage());
    http_response_code(503);
    die(APP_DEBUG
        ? 'Erro ao conectar ao banco de dados: ' . htmlspecialchars($e->getMessage())
        : 'Serviço temporariamente indisponível. Tente novamente em instantes.');
}

// O driver PDO do PostgreSQL retorna colunas boolean como string 't'/'f',
// e em PHP a string 'f' é "verdadeira" em teste de condição (if/!).
// Use esta função sempre que for testar um campo boolean vindo do banco.
function pg_bool($valor) {
    return $valor === true || $valor === 't' || $valor === '1' || $valor === 1 || $valor === 'true';
}

// ============================================================
// CONFIGURAÇÕES GUARDADAS NO BANCO (tabela "configuracoes")
// Permite editar chaves de API pela tela Admin > Configurações,
// sem precisar mexer neste arquivo depois da instalação inicial.
// Se a tabela ainda não existir ou a chave não tiver sido salva,
// cai de volta nas constantes acima (compatibilidade).
// ============================================================
$GLOBALS['gds_config_cache'] = [];

function get_config($chave, $default = '') {
    global $pdo;
    $cache = &$GLOBALS['gds_config_cache'];
    if (array_key_exists($chave, $cache)) return $cache[$chave] === null ? $default : $cache[$chave];

    $valor = null;
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave=?");
        $stmt->execute([$chave]);
        $row = $stmt->fetch();
        if ($row && $row['valor'] !== '' && $row['valor'] !== null) {
            $valor = $row['valor'];
        }
    } catch (PDOException $e) {
        // Tabela "configuracoes" ainda não existe (rode o adicional_3_configuracoes.sql)
    }
    if ($valor === null) {
        $mapaConstantes = [
            'ia_provider' => 'IA_PROVIDER',
            'ia_api_key' => 'IA_API_KEY',
            'mercadopago_access_token' => 'MERCADOPAGO_ACCESS_TOKEN',
            'whatsapp_api_token' => 'WHATSAPP_API_TOKEN',
        ];
        if (isset($mapaConstantes[$chave]) && defined($mapaConstantes[$chave])) {
            $constVal = constant($mapaConstantes[$chave]);
            if ($constVal !== '' && $constVal !== 'SUA_CHAVE_DE_API_AQUI') $valor = $constVal;
        }
    }
    $cache[$chave] = $valor;
    return $valor === null ? $default : $valor;
}

function set_config($chave, $valor) {
    global $pdo;
    unset($GLOBALS['gds_config_cache'][$chave]);
    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor");
    return $stmt->execute([$chave, $valor]);
}
