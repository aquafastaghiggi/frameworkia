<?php

declare(strict_types=1);

/**
 * Frameworkia - XAMPP Setup Script
 * Executa instalação automática no XAMPP local
 * 
 * Use: http://localhost/frameworkia/setup.php
 */

// Disable time limit
set_time_limit(0);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Colors for output
class CLI {
    const GREEN = "\033[92m";
    const RED = "\033[91m";
    const YELLOW = "\033[93m";
    const BLUE = "\033[94m";
    const RESET = "\033[0m";

    public static function success(string $msg): void {
        echo self::GREEN . "✅ " . $msg . self::RESET . "\n";
    }

    public static function error(string $msg): void {
        echo self::RED . "❌ " . $msg . self::RESET . "\n";
    }

    public static function warning(string $msg): void {
        echo self::YELLOW . "⚠️ " . $msg . self::RESET . "\n";
    }

    public static function info(string $msg): void {
        echo self::BLUE . "ℹ️ " . $msg . self::RESET . "\n";
    }
}

echo <<<'BANNER'

╔════════════════════════════════════════════════════════════════╗
║     FRAMEWORKIA - XAMPP LOCAL SETUP WIZARD                   ║
║     Configuração automática para desenvolvimento local        ║
╚════════════════════════════════════════════════════════════════╝

BANNER;

// ============================================================
// 1. VERIFICAR REQUISITOS
// ============================================================
echo "\n[1/5] Verificando requisitos...\n";
echo "─────────────────────────────────────────────────────\n";

$errors = [];

// PHP Version
if (version_compare(PHP_VERSION, '8.2', '<')) {
    $errors[] = "PHP 8.2+ required (current: " . PHP_VERSION . ")";
} else {
    CLI::success("PHP " . PHP_VERSION . " ✓");
}

// Required Extensions
$extensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'fileinfo'];
foreach ($extensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Extension '$ext' not loaded";
    } else {
        CLI::success("Extension '$ext' loaded ✓");
    }
}

// Directories
$dirs = [
    'storage' => 'writable storage directory',
    'storage/logs' => 'writable logs directory',
    'storage/uploads' => 'writable uploads directory',
    'workspaces' => 'writable workspaces directory',
    'public' => 'public directory',
];

foreach ($dirs as $dir => $desc) {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            $errors[] = "Cannot create $desc: $dir";
        } else {
            CLI::success("Directory $dir created ✓");
        }
    } else {
        CLI::success("Directory $dir exists ✓");
    }
}

// Check if errors
if (!empty($errors)) {
    echo "\n";
    foreach ($errors as $error) {
        CLI::error($error);
    }
    exit(1);
}

// ============================================================
// 2. CONFIGURAR .ENV
// ============================================================
echo "\n[2/5] Configurando arquivo .env...\n";
echo "─────────────────────────────────────────────────────\n";

if (file_exists('.env')) {
    CLI::warning(".env já existe, pulando criação");
} else {
    if (file_exists('.env.xampp')) {
        if (copy('.env.xampp', '.env')) {
            CLI::success(".env criado a partir de .env.xampp");
        } else {
            CLI::error("Erro ao criar .env");
            exit(1);
        }
    } elseif (file_exists('.env.example')) {
        if (copy('.env.example', '.env')) {
            CLI::success(".env criado a partir de .env.example");
        } else {
            CLI::error("Erro ao criar .env");
            exit(1);
        }
    } else {
        CLI::error(".env.xampp ou .env.example não encontrado!");
        exit(1);
    }
}

// Generate APP_KEY
$envContent = file_get_contents('.env');
if (!str_contains($envContent, 'APP_KEY=base64:')) {
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    $envContent = str_replace('APP_KEY=', "APP_KEY=$appKey", $envContent);
    file_put_contents('.env', $envContent);
    CLI::success("APP_KEY gerada");
}

// ============================================================
// 3. CRIAR BANCO DE DADOS
// ============================================================
echo "\n[3/5] Criando banco de dados MySQL...\n";
echo "─────────────────────────────────────────────────────\n";

try {
    // Carrega variáveis de .env
    $lines = file('.env', FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
    $config = [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $config[trim($k)] = trim($v);
        }
    }

    $dbHost = $config['DB_HOST'] ?? 'localhost';
    $dbUser = $config['DB_USERNAME'] ?? 'root';
    $dbPass = $config['DB_PASSWORD'] ?? '';
    $dbName = $config['DB_DATABASE'] ?? 'frameworkia_db';

    // Conectar ao MySQL
    try {
        $pdo = new PDO(
            "mysql:host=$dbHost",
            $dbUser,
            $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Criar banco de dados
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        CLI::success("Banco de dados '$dbName' criado");

        // Selecionar banco
        $pdo->exec("USE `$dbName`");

        // Executar migrations
        $migrations = file_get_contents('database/migrations.sql');
        $statements = array_filter(
            array_map('trim', explode(';', $migrations)),
            fn($s) => !empty($s) && !str_starts_with($s, '--')
        );

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                } catch (Exception $e) {
                    // Skip if already exists
                    if (!str_contains($e->getMessage(), 'already exists')) {
                        throw $e;
                    }
                }
            }
        }

        CLI::success("Migrations executadas com sucesso");

    } catch (PDOException $e) {
        CLI::error("Erro MySQL: " . $e->getMessage());
        CLI::warning("Certifique-se de que MySQL está rodando no XAMPP");
        exit(1);
    }

} catch (Exception $e) {
    CLI::error("Erro ao configurar banco: " . $e->getMessage());
    exit(1);
}

// ============================================================
// 4. CRIAR DIRETÓRIOS NECESSÁRIOS
// ============================================================
echo "\n[4/5] Criando estrutura de diretórios...\n";
echo "─────────────────────────────────────────────────────\n";

$dirs_to_create = [
    'storage/logs' => 'Diretório de logs',
    'storage/uploads' => 'Diretório de uploads',
    'storage/cache' => 'Diretório de cache',
    'storage/indices' => 'Diretório de índices',
    'workspaces' => 'Diretório de workspaces',
    'public/css' => 'CSS assets',
    'public/js' => 'JavaScript assets',
];

foreach ($dirs_to_create as $dir => $desc) {
    if (!is_dir($dir)) {
        if (@mkdir($dir, 0755, true)) {
            CLI::success("$desc criado: $dir");
        } else {
            CLI::warning("Erro ao criar: $dir (verifique permissões)");
        }
    }
}

// ============================================================
// 5. INFORMAÇÕES FINAIS
// ============================================================
echo "\n[5/5] Finalizando setup...\n";
echo "─────────────────────────────────────────────────────\n";

CLI::success("Setup completado com sucesso!");

echo <<<'INFO'

════════════════════════════════════════════════════════════════

🎉 FRAMEWORKIA ESTÁ PRONTO PARA USAR!

📝 Próximos passos:

1. INICIAR XAMPP
   • Abra C:\xampp\xampp-control.exe
   • Clique em "Start" para Apache e MySQL

2. ACESSAR A APLICAÇÃO
   • URL: http://localhost/frameworkia
   • Ou configure virtual host (ver instruções abaixo)

3. LOGIN
   • Usuário: admin
   • Senha: admin123
   • Email: admin@frameworkia.local

4. CONFIGURAR OpenAI (OPCIONAL)
   • Edite .env
   • OPENAI_API_KEY=sua-chave-aqui
   • (Sem chave, funcionará com mock provider)

════════════════════════════════════════════════════════════════

🔧 CONFIGURAÇÃO DE VIRTUAL HOST (OPCIONAL)

Se quiser acessar via http://frameworkia.local:

1. Edite C:\xampp\apache\conf\extra\httpd-vhosts.conf

2. Adicione no final:
   
   <VirtualHost *:80>
       DocumentRoot "C:/xampp/htdocs/frameworkia/public"
       ServerName frameworkia.local
       <Directory "C:/xampp/htdocs/frameworkia/public">
           Require all granted
           RewriteEngine On
           RewriteCond %{REQUEST_FILENAME} !-f
           RewriteCond %{REQUEST_FILENAME} !-d
           RewriteRule ^ index.php [QSA,L]
       </Directory>
   </VirtualHost>

3. Edite C:\Windows\System32\drivers\etc\hosts
   Adicione: 127.0.0.1 frameworkia.local

4. Reinicie Apache no XAMPP

════════════════════════════════════════════════════════════════

📊 INFORMAÇÕES DO SETUP

✅ PHP Version: PHP_VERSION
✅ Database: frameworkia_db (MySQL)
✅ Admin User: admin/admin123
✅ Storage: Preparado para uploads
✅ Logs: Salvos em storage/logs
✅ Frontend: Vue.js (public/index.html)
✅ API: Endpoints REST prontos

════════════════════════════════════════════════════════════════

💡 DICAS

• Para desenvolvimento, APP_DEBUG=true em .env
• Logs estão em storage/logs/
• Uploads vão para storage/uploads/
• Workspaces em workspaces/
• Documentação em README.md

════════════════════════════════════════════════════════════════

INFO;

echo "\n";
exit(0);
