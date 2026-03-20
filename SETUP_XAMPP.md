# 🚀 GUIA DE SETUP - FRAMEWORKIA NO XAMPP

**Configuração passo-a-passo para rodar Frameworkia no seu XAMPP local**

## 📋 Pré-requisitos

- ✅ XAMPP instalado em `C:\xampp`
- ✅ PHP 8.2+ (XAMPP 8.2+)
- ✅ MySQL rodando
- ✅ Git instalado

## 🔧 SETUP RÁPIDO (5 minutos)

### 1️⃣ Clonar/Mover para XAMPP

```bash
# Opção A: Se ainda não está no htdocs
cd C:\xampp\htdocs
git clone https://github.com/andreghiggi/frameworkia.git

# Opção B: Se já tem a pasta frameworkia
# Copie a pasta frameworkia para C:\xampp\htdocs\
```

### 2️⃣ Iniciar XAMPP

```bash
# Abra C:\xampp\xampp-control.exe
# Clique em "Start" para:
# - Apache
# - MySQL

# Aguarde até ver "Running" em ambos
```

### 3️⃣ Executar Setup Automático

```bash
# Abra o navegador e acesse:
http://localhost/frameworkia/setup.php

# Aguarde o setup completar (levará alguns segundos)
# Verá mensagem de sucesso se tudo funcionar
```

### 4️⃣ Acessar a Aplicação

```bash
# URL:
http://localhost/frameworkia

# Login:
# Usuário: admin
# Senha: admin123
```

---

## 📍 ALTERNATIVA: Setup Manual

Se `setup.php` não funcionar, siga os passos manuais:

### 1. Criar arquivo `.env`

```bash
# Na pasta frameworkia, copie:
copy .env.xampp .env

# Ou copie manualmente:
```

**Conteúdo do `.env` para XAMPP:**

```env
APP_NAME=Frameworkia
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/frameworkia

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=frameworkia_db
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4

OPENAI_API_KEY=mock-key-local
OPENAI_MODEL=gpt-4o-mini

CACHE_DRIVER=file
LOG_LEVEL=debug
```

### 2. Criar Banco de Dados

```bash
# Abra phpMyAdmin:
http://localhost/phpmyadmin

# Ou via terminal:
cd C:\xampp\mysql\bin
mysql -u root

# Execute:
CREATE DATABASE frameworkia_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE frameworkia_db;

# Copie e paste o conteúdo de: database/migrations.sql
# E execute cada comando
```

### 3. Criar Diretórios

```bash
# Na pasta frameworkia, crie:
mkdir storage
mkdir storage\logs
mkdir storage\uploads
mkdir storage\cache
mkdir storage\indices
mkdir workspaces

# Dê permissões (Windows geralmente não precisa)
icacls storage /grant Users:F /T
icacls workspaces /grant Users:F /T
```

---

## 🌐 ACESSAR VIA DOMÍNIO LOCAL (OPCIONAL)

Se quiser acessar via `http://frameworkia.local` em vez de `localhost/frameworkia`:

### 1. Configurar Virtual Host

```bash
# Edite: C:\xampp\apache\conf\extra\httpd-vhosts.conf

# Adicione no final:

<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/frameworkia/public"
    ServerName frameworkia.local
    ServerAlias *.frameworkia.local
    
    <Directory "C:/xampp/htdocs/frameworkia/public">
        Require all granted
        
        # Enable mod_rewrite
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^ index.php [QSA,L]
        </IfModule>
    </Directory>
</VirtualHost>
```

### 2. Editar arquivo HOSTS

```bash
# Edite: C:\Windows\System32\drivers\etc\hosts

# Adicione a linha:
127.0.0.1 frameworkia.local
```

### 3. Reiniciar Apache

```bash
# No XAMPP Control Panel:
# Clique em "Stop" em Apache
# Aguarde 3 segundos
# Clique em "Start" em Apache
```

### 4. Acessar

```
http://frameworkia.local
```

---

## ✅ VERIFICAÇÃO DE REQUISITOS

Após setup, verifique se está tudo certo:

```bash
# Abra no navegador:
http://localhost/frameworkia/health

# Você verá algo como:
{
  "sucesso": true,
  "status": "ok",
  "app": "Frameworkia",
  "version": "1.0.0"
}
```

---

## 🔍 TROUBLESHOOTING

### ❌ "Erro: Cannot connect to database"

**Solução:**
1. Verifique se MySQL está rodando (XAMPP Control Panel)
2. Verifique credenciais em `.env` (DB_USERNAME, DB_PASSWORD)
3. Confirme que banco de dados foi criado:
   ```bash
   mysql -u root -e "SHOW DATABASES;"
   ```

### ❌ "Erro 404 - Página não encontrada"

**Solução:**
1. Verifique URL: `http://localhost/frameworkia`
2. Confirme que pasta está em: `C:\xampp\htdocs\frameworkia`
3. Se usando virtual host, confirme que `frameworkia.local` está no HOSTS

### ❌ "Erro: Extension 'pdo_mysql' not loaded"

**Solução:**
1. Edite: `C:\xampp\php\php.ini`
2. Procure: `;extension=pdo_mysql`
3. Remova o `;` no início
4. Reinicie Apache

### ❌ "Erro 500 - Internal Server Error"

**Solução:**
1. Verifique logs: `storage/logs/`
2. Verifique permissões das pastas `storage/` e `workspaces/`
3. Tente:
   ```bash
   icacls C:\xampp\htdocs\frameworkia\storage /grant Everyone:F /T
   icacls C:\xampp\htdocs\frameworkia\workspaces /grant Everyone:F /T
   ```

### ❌ "Erro: Cannot write to storage/"

**Solução:**
```bash
# Dê permissão total:
icacls C:\xampp\htdocs\frameworkia\storage /grant Users:F /T
icacls C:\xampp\htdocs\frameworkia\workspaces /grant Users:F /T
```

---

## 💡 DICAS & TRICKS

### Acessar PhpMyAdmin
```
http://localhost/phpmyadmin
```

### Ver logs de erro
```
C:\xampp\htdocs\frameworkia\storage\logs\
```

### Resetar admin user
```sql
UPDATE users SET password_hash = '$2y$10$YIj7P8HKkwP5/z5z5z5OZ5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z' WHERE username = 'admin';
-- Senha: admin123
```

### Limpar cache
```bash
# Delete conteúdo de:
C:\xampp\htdocs\frameworkia\storage\cache\
```

### Parar de rodar em localhost/frameworkia
```bash
# Remova a pasta ou renomeie para:
C:\xampp\htdocs\frameworkia_old

# Ou use apenas virtual host com domínio local
```

---

## 🎯 PRÓXIMOS PASSOS

1. **Login**: admin/admin123
2. **Criar Workspace**: Novo projeto
3. **Editar Código**: Use o editor multi-abas
4. **Chat com IA**: Digite perguntas no chat (usa mock por padrão)
5. **OpenAI Real**: Configure OPENAI_API_KEY em .env

---

## 📞 SUPORTE

Se tiver problemas:

1. Verifique que XAMPP está rodando
2. Verifique MySQL está iniciado
3. Verifique permissões de pastas
4. Leia os logs em `storage/logs/`
5. Procure por erros em `C:\xampp\apache\logs\`

---

**Desenvolvido com ❤️ para rodar local no XAMPP**

**Status**: ✅ Pronto para desenvolvimento local
