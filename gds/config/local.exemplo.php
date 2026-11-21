<?php
// Copie este arquivo para "local.php" (na mesma pasta) e preencha com os dados do seu banco.
// O local.php fica fora do GitHub, então a senha não vaza no repositório.
// Supabase: Project Settings → Database → Connection string → "Session pooler".
return [
    'GDS_DB_HOST'    => 'aws-0-sa-east-1.pooler.supabase.com',
    'GDS_DB_PORT'    => '5432',                      // 5432 = session pooler | 6543 = transaction pooler
    'GDS_DB_NAME'    => 'postgres',
    'GDS_DB_USER'    => 'postgres.SEU_PROJECT_REF',
    'GDS_DB_PASS'    => 'SUA_SENHA_DO_BANCO',
    'GDS_DB_SSLMODE' => 'require',
    'GDS_DEBUG'      => '0',                         // '1' mostra erros na tela (só para testes)
    // Recuperação de emergência do administrador (/recuperar_acesso.php). Use uma frase longa (16+ caracteres).
    'GDS_CHAVE_RECUPERACAO' => '',
];
