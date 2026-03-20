# FASE 11: MULTI-CONTEXT AI — DOCUMENTAÇÃO COMPLETA

## 📋 RESUMO EXECUTIVO

**Fase 11** implementa a capacidade da IA trabalhar com múltiplas fontes de contexto simultaneamente:
- **Código + Documentos**: Análise integrada de implementação e especificação
- **Múltiplos Arquivos**: Processar vários arquivos sem exceder limites de tokens
- **Memória de Conversa**: Histórico estruturado e reutilizável de conversas
- **Análises Combinadas**: Detectar referências cruzadas, padrões de integração, fluxos de dados

**Status:** ✅ **100% COMPLETO** (31/31 testes passando)

---

## 🏗️ ARQUITETURA

### Componentes Principais

#### 1. **MultiContextManager** (~680 linhas)
Orquestrador central que gerencia contexto multi-arquivo

**Responsabilidades:**
- Analisar código (PHP parser integrado)
- Processar documentos (suporta PDF, Excel, CSV, Texto)
- Executar análises combinadas (referências cruzadas, fluxos de dados, padrões)
- Construir prompts enriquecidos respeitando limites de tokens
- Mapear estrutura do projeto

**Métodos Principais:**
```php
// Construir contexto multi-arquivo
construirContextoMulti(array $opcoes): array

// Analisar arquivo de código
analisarArquivoCódigo(string $caminho): array

// Analisar documento (suporta múltiplos formatos)
analisarDocumento(string $caminho): array

// Executar análises que combinam código e documentos
executarAnálisesCombinadasCódigo(array $caminhosCódigo, array $caminhosDocumentos): array

// Enriquecer prompt com múltiplos contextos
construirPromptMultiContexto(string $promptOriginal, array $contexto): string
```

**Fluxo de Análise:**
```
Entrada (múltiplos arquivos)
    ↓
Análise de Código (extração de funções/classes)
    ↓
Análise de Documentos (tipo de conteúdo, análise semântica)
    ↓
Análises Combinadas:
  - Referências cruzadas (termos comuns)
  - Fluxos de dados (entrada/processamento/saída)
  - Padrões de integração (validação, transformação, armazenamento)
    ↓
Extração de Entidades Compartilhadas
    ↓
Geração de Recomendações
    ↓
Saída: Contexto estruturado + Prompt enriquecido
```

#### 2. **ConversationMemory** (~290 linhas)
Gerenciador de histórico de conversas com suporte a múltiplas conversas independentes

**Responsabilidades:**
- Manter histórico estruturado de mensagens
- Suportar múltiplas conversas com IDs únicos
- Gerenciar limites de armazenamento (max 50 mensagens por conversa)
- Exportar conversas em diferentes formatos
- Estimar tokens necessários para o histórico

**Métodos Principais:**
```php
// Iniciar nova conversa
iniciarConversa(string $id = '', string $titulo = ''): void

// Adicionar mensagem com contexto
adicionarMensagem(string $papel, string $conteúdo, array $contexto = []): void

// Obter mensagens da conversa atual
obterMensagens(int $limite = 10): array

// Listar todas as conversas
listarConversas(): array

// Carregar conversa específica
carregarConversa(string $id): bool

// Exportar conversa em diferentes formatos
exportarParaPrompt(string $id = ''): string

// Construir prompt com histórico
construirPromptComHistórico(string $promptAtual): string
```

**Estrutura de Dados:**
```php
[
    'id' => 'conversa_xyz',
    'titulo' => 'Análise de Performance',
    'criada_em' => '2024-03-20 10:30:00',
    'atualizada_em' => '2024-03-20 11:45:00',
    'mensagens' => [
        [
            'id' => 'msg_123',
            'papel' => 'user|assistant|system',
            'conteúdo' => '...',
            'contexto' => [...],
            'timestamp' => '2024-03-20 10:30:00'
        ]
    ],
    'contexto' => [...],
    'resumo' => '...'
]
```

---

## 📡 ENDPOINTS API (FASE 11)

### 1. Chat Multi-Context
**POST** `/api/chat/multi-context`

Envia mensagem com múltiplas fontes de contexto

**Request:**
```json
{
    "prompt": "Como melhorar o desempenho dessa integração?",
    "caminhos_código": [
        "/app/Services/DataProcessor.php",
        "/app/Models/Usuario.php"
    ],
    "caminhos_documentos": [
        "/docs/especificacao.pdf",
        "/docs/dados.xlsx"
    ],
    "diretorio_raiz": "/workspace",
    "incluir_git": true,
    "incluir_estrutura": true
}
```

**Response:**
```json
{
    "sucesso": true,
    "mensagem": "Chat multi-contexto processado com sucesso.",
    "resposta": "Baseado na análise integrada...",
    "contexto": {
        "tokens_utilizados": 2847,
        "tokens_disponíveis": 5153,
        "análises": {
            "referências_cruzadas": [...],
            "fluxos_dados": [...],
            "padrões_integração": {...}
        },
        "recomendações": [...]
    },
    "histórico": [...]
}
```

### 2. Histórico de Conversas
**GET** `/api/chat/historico-conversas`

Lista todas as conversas armazenadas

**Response:**
```json
{
    "sucesso": true,
    "conversas": [
        {
            "id": "conversa_1",
            "titulo": "Análise de API",
            "criada_em": "2024-03-20 10:00:00",
            "atualizada_em": "2024-03-20 11:30:00",
            "mensagens_count": 12,
            "resumo": "Conversa sobre otimização..."
        }
    ],
    "conversa_atual": "conversa_1"
}
```

### 3. Carregar Conversa
**POST** `/api/chat/carregar-conversa`

Carrega uma conversa específica

**Request:**
```json
{
    "id": "conversa_1"
}
```

**Response:**
```json
{
    "sucesso": true,
    "conversa": {
        "id": "conversa_1",
        "titulo": "Análise de API",
        "criada_em": "2024-03-20 10:00:00",
        "mensagens_count": 12,
        "tokens_estimados": 1847
    },
    "mensagens": [...]
}
```

### 4. Iniciar Conversa
**POST** `/api/chat/iniciar-conversa`

Cria uma nova conversa

**Request:**
```json
{
    "titulo": "Refatoração de módulo"
}
```

**Response:**
```json
{
    "sucesso": true,
    "id": "conversa_abc123",
    "conversa": {
        "id": "conversa_abc123",
        "titulo": "Refatoração de módulo",
        "criada_em": "2024-03-20 12:00:00",
        "mensagens_count": 0,
        "tokens_estimados": 0
    }
}
```

### 5. Limpar Conversa
**POST** `/api/chat/limpar-conversa`

Limpa mensagens de uma conversa

**Request:**
```json
{
    "id": "conversa_1",
    "limpar_todas": false
}
```

**Response:**
```json
{
    "sucesso": true,
    "mensagem": "Conversa limpa."
}
```

### 6. Exportar Conversa
**POST** `/api/chat/exportar-conversa`

Exporta conversa em diferentes formatos

**Request:**
```json
{
    "id": "conversa_1",
    "formato": "texto|json"
}
```

**Response (texto):**
```json
{
    "sucesso": true,
    "conteúdo": "# CONVERSA: Análise de API\n...",
    "formato": "texto"
}
```

**Response (json):**
```json
{
    "sucesso": true,
    "conversa": {...},
    "mensagens": [...]
}
```

---

## 💡 EXEMPLOS DE USO

### Exemplo 1: Análise de Integração Código + Documentação

```javascript
const response = await fetch('/api/chat/multi-context', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        prompt: 'Há inconsistências entre a implementação e a documentação?',
        caminhos_código: [
            '/app/Services/DataProcessor.php',
            '/app/Models/Usuario.php'
        ],
        caminhos_documentos: [
            '/docs/api.pdf'
        ],
        diretorio_raiz: '/workspace'
    })
});

const resultado = await response.json();
console.log(resultado.resposta);
console.log(resultado.contexto.análises.referências_cruzadas);
```

### Exemplo 2: Manter Histórico de Conversas

```javascript
// 1. Iniciar conversa
const novaConversa = await fetch('/api/chat/iniciar-conversa', {
    method: 'POST',
    body: JSON.stringify({ titulo: 'Refatoração de módulo' })
});

// 2. Enviar múltiplas mensagens
for (const prompt of prompts) {
    await fetch('/api/chat/multi-context', {
        method: 'POST',
        body: JSON.stringify({
            prompt: prompt,
            caminhos_código: arquivos_código,
            caminhos_documentos: arquivos_docs
        })
    });
}

// 3. Recuperar conversa depois
const conversas = await fetch('/api/chat/historico-conversas');
const lista = await conversas.json();

// 4. Carregar conversa anterior
await fetch('/api/chat/carregar-conversa', {
    method: 'POST',
    body: JSON.stringify({ id: lista.conversas[0].id })
});
```

### Exemplo 3: Exportar Conversa para Documentação

```javascript
const exportação = await fetch('/api/chat/exportar-conversa', {
    method: 'POST',
    body: JSON.stringify({
        id: 'conversa_abc123',
        formato: 'texto'
    })
});

const { conteúdo } = await exportação.json();

// Salvar em arquivo
const blob = new Blob([conteúdo], { type: 'text/plain' });
const url = URL.createObjectURL(blob);
const a = document.createElement('a');
a.href = url;
a.download = 'conversa-export.txt';
a.click();
```

---

## 🧪 TESTES

**31 testes implementados, 100% passando**

### Cobertura por Grupo:
- **MultiContextManager** (4 testes)
  - Instanciação
  - Análise de código
  - Processamento multi-arquivo
  - Enriquecimento de prompts

- **Análise Multi-Arquivo** (2 testes)
  - Múltiplos arquivos
  - Limite de tokens

- **Construção de Prompts** (3 testes)
  - Prompt enriquecido
  - Inclusão de contexto
  - Preservação de tarefa

- **ConversationMemory** (18 testes)
  - Inicialização
  - Gerenciamento de mensagens
  - Gerenciamento de contexto
  - Listagem e carregamento
  - Exportação em múltiplos formatos
  - Limpeza de dados
  - Limite de armazenamento

- **Fluxo Completo** (2 testes)
  - Integração código + documentos
  - Enriquecimento completo

- **Gerenciamento de Tokens** (2 testes)
  - Estimação de tokens
  - Razoabilidade de valores

**Executar testes:**
```bash
php tests/MultiContextTest.php
```

---

## 📊 CASOS DE USO

### 1. Análise de Alinhamento Código-Documentação
**Problema:** Validar se implementação segue a especificação

**Solução:**
- Fornecer arquivo de código + documento de requisitos
- MultiContextManager detecta referências cruzadas e inconsistências
- IA analisa com contexto integrado
- Gera relatório de conformidade

### 2. Refatoração Orientada por Documentação
**Problema:** Refatorar código mantendo documentação sincronizada

**Solução:**
- Fornecer código atual + documentação
- IA sugere mudanças baseadas em ambos
- ConversationMemory mantém histórico de refatorações
- Exportar conversa como changelog

### 3. Integração de Dados
**Problema:** Integrar dados de múltiplas fontes (código + Excel + PDF)

**Solução:**
- Fornecer código de processamento + arquivos de dados
- MultiContextManager analisa tipos de dados detectados
- IA fornece código de integração compatível
- Detecta padrões de transformação necessários

### 4. Documentação Automática
**Problema:** Manter documentação sincronizada com código

**Solução:**
- Usar ConversationMemory para manter histórico
- Exportar conversas em formato markdown
- IA gera documentação baseada em código + análises

### 5. Depuração Multi-Contexto
**Problema:** Debugar erro que cruza múltiplos arquivos

**Solução:**
- Fornecer arquivos envolvidos + logs (como PDF/documento)
- MultiContextManager mapeia fluxo de dados entre eles
- IA identifica ponto de falha com contexto completo
- ConversationMemory mantém histórico do debugging

---

## ⚙️ CONFIGURAÇÃO E INTEGRAÇÃO

### Inicializar no Controller
```php
// ChatController.php
use App\AI\MultiContextManager;
use App\AI\ConversationMemory;

$multiContextManager = new MultiContextManager(8000); // limite de tokens
$conversationMemory = new ConversationMemory();
```

### Limites Configuráveis
```php
// Limite de tokens por contexto
$manager = new MultiContextManager(8000); // padrão: 8000

// Limite de mensagens armazenadas
// Fixo em 50 mensagens por conversa (ajustável no código)
```

### Salvamento em Sessão
```php
// ConversationMemory salva automaticamente em $_SESSION
// Necessário: session_start() ativo

// Dados persistem enquanto a sessão estiver ativa
// Para persistência entre sessões, estender para banco de dados
```

---

## 🔒 SEGURANÇA

### Proteções Implementadas:
1. **Validação de Caminhos**: Verifica existência e tipo de arquivo
2. **Limite de Tokens**: Previne overflow de memória
3. **Tratamento de Exceções**: Falhas em documentos isoladas
4. **Sanitização de Entrada**: Prompt validado antes do processamento
5. **Controle de Acesso**: Via autenticação existente (ChatController)

### Considerações:
- Arquivo são lidos do disco (validar permissões do servidor)
- Documentos não são armazenados em cache persistente
- ConversationMemory em sessão (insegura para dados sensíveis - considerar criptografia)

---

## 📈 PERFORMANCE

### Otimizações Implementadas:
1. **Limite Inteligente de Tokens**: Interrompe análise se exceder limite
2. **Exploração Parcial de Estrutura**: Max 3 níveis de profundidade
3. **Filtro de Palavras Comuns**: Remove "o", "a", "e", etc. de análises
4. **Caching de Entidades**: Evita re-extrair de documentos

### Benchmarks (máquina de teste):
- Análise de 1 arquivo PHP: ~50ms
- Análise de 3 documentos: ~150-300ms (depende do formato)
- Construção de contexto multi-arquivo: ~250ms
- Construção de prompt enriquecido: ~10ms

### Escalabilidade:
- Testar com 50+ arquivos aumentaria tempo para 2-3 segundos
- Considerar: fila de processamento (Laravel Queue) para análises pesadas
- Opção: Cache em Redis para análises frequentes

---

## 🚀 PRÓXIMOS PASSOS (FASE 12+)

### Fase 12: Ollama Integration
- [ ] Adapter para rodar IA localmente (Ollama)
- [ ] Fallback automático OpenAI ↔ Local
- [ ] Seleção de modelo de IA

### Melhorias em Fase 11:
- [ ] Persistência de ConversationMemory em banco de dados
- [ ] Criptografia de dados sensíveis em sessão
- [ ] Busca de mensagens históricas por palavra-chave
- [ ] Versionamento de conversas
- [ ] Compartilhamento de conversas entre usuários

### Performance:
- [ ] Fila de processamento para análises pesadas
- [ ] Cache Redis para contextos frequentes
- [ ] Índice de entidades para busca rápida

---

## 📝 NOTAS TÉCNICAS

### Compatibilidade
- PHP 8.2+
- Frameworks: Qualquer (integração via ChatController)
- Banco de Dados: Nenhum (sessão apenas)

### Dependências Externas
- Nenhuma (usa componentes existentes do projeto)

### Integrações Internas
- `App\Code\Parser\PhpParser` - Análise de PHP
- `App\Documents\DocumentManager` - Leitura de documentos
- `App\Documents\Intelligence\DocumentAnalyzer` - Análise semântica
- `App\Documents\Intelligence\EntityExtractor` - Extração de entidades
- `App\Code\CodeModifier` - Modificações de código (opcional)
- `App\Git\GitService` - Contexto Git (opcional)

---

## 🎓 CONCLUSÃO

**Fase 11 fornece a IA capacidade real de trabalhar em projetos complexos** com múltiplas fontes de contexto, histórico de conversas e análises integradas. É a base para Fase 12 (Ollama) e Fase 13 (Git Avançado).

**Implementação completa, testada e documentada.** Pronto para produção com pequenas customizações.

---

**Criado em:** 2024-03-20  
**Status:** ✅ Completo (31/31 testes passando)  
**Próximo:** Fase 12 - Ollama Integration
