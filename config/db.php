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
// ============================================================
define('DB_HOST', 'SEU_HOST.pooler.supabase.com'); // <-- CONFIGURE AQUI
define('DB_PORT', '6543');                          // 6543 = transaction pooler | 5432 = conexão direta
define('DB_NAME', 'postgres');                      // no Supabase o banco padrão chama "postgres"
define('DB_USER', 'postgres.SEU_PROJECT_REF');       // <-- CONFIGURE AQUI (formato do pooler)
define('DB_PASS', 'SUA_SENHA_DO_BANCO');             // <-- CONFIGURE AQUI (a senha que você definiu ao criar o projeto)

// ============================================================
// CHAVES DE INTEGRAÇÃO - CONFIGURE AQUI
// ============================================================
define('IA_PROVIDER', 'anthropic');           // anthropic | openai | gemini
define('IA_API_KEY', 'SUA_CHAVE_DE_API_AQUI'); // <-- CONFIGURE AQUI
define('MERCADOPAGO_ACCESS_TOKEN', '');        // <-- CONFIGURE AQUI
define('WHATSAPP_API_TOKEN', '');              // <-- CONFIGURE AQUI

try {
    $pdo = new PDO(
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
}

// O driver PDO do PostgreSQL retorna colunas boolean como string 't'/'f',
// e em PHP a string 'f' é "verdadeira" em teste de condição (if/!).
// Use esta função sempre que for testar um campo boolean vindo do banco.
function pg_bool($valor) {
    return $valor === true || $valor === 't' || $valor === '1' || $valor === 1;
}

// ============================================================
// CONFIGURAÇÕES GUARDADAS NO BANCO (tabela "configuracoes")
// Permite editar chaves de API pela tela Admin > Configurações,
// sem precisar mexer neste arquivo depois da instalação inicial.
// Se a tabela ainda não existir ou a chave não tiver sido salva,
// cai de volta nas constantes acima (compatibilidade).
// ============================================================
function get_config($chave, $default = '') {
    global $pdo;
    static $cache = [];
    if (array_key_exists($chave, $cache)) return $cache[$chave];

    $valor = $default;
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave=?");
        $stmt->execute([$chave]);
        $row = $stmt->fetch();
        if ($row && $row['valor'] !== '' && $row['valor'] !== null) {
            $valor = $row['valor'];
        } else {
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
    } catch (PDOException $e) {
        // Tabela "configuracoes" ainda não existe (rode o adicional_3_configuracoes.sql)
    }
    $cache[$chave] = $valor;
    return $valor;
}

function set_config($chave, $valor) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor");
    return $stmt->execute([$chave, $valor]);
}
