Perfeito. Com base no estado atual do repositório e na análise de maturidade, o caminho para levar o projeto perto de **9/10 em visão, capacidade, estabilidade e coesão arquitetural** é entrar numa fase de **estabilização orientada a produto**, com escopo fechado e checklist operacional. O repositório já mostra uma base rica em features — rotas amplas de workspace/Git/chat, dependências para Excel/PDF e um `ChatController` com bastante responsabilidade — então o gargalo agora não é falta de função, e sim **confiabilidade, desacoplamento e previsibilidade de execução local**.

# Escopo técnico de estabilização e amadurecimento

## Objetivo macro

Transformar o `frameworkia` de um sistema “feature-rich, but fragile” em uma base consistente, local-first, com boot previsível, erros observáveis, módulos desacoplados e fluxos críticos confiáveis. Hoje o projeto já tem dependências e módulos suficientes para isso: PHP 8.2, leitura de planilhas/PDF, rotas de Git mais amplas e chat contextual.

## Meta de maturidade

Chegar próximo de 9/10 em:

* visão de produto
* capacidade entregue
* estabilidade
* coesão arquitetural
* operabilidade local
* extensibilidade

---

# Frente 1 — Boot local 100% previsível

## Escopo

Fechar o ambiente local no XAMPP para que qualquer subida do sistema tenha comportamento determinístico.

## Entregáveis

* documento único “Como subir local”
* verificação de dependências antes do boot
* verificação de extensões PHP exigidas
* criação automática de diretórios de `storage`
* tela de diagnóstico do ambiente local

## Checklist

* [ ] `composer install` roda sem erro
* [ ] `composer dump-autoload` faz parte do fluxo de setup
* [ ] extensões obrigatórias documentadas e validadas (`gd`, `mbstring`, `xml`, `zip`)
* [ ] `storage/` e subpastas são criadas automaticamente no boot ou setup
* [ ] existe endpoint `/health` com diagnóstico expandido
* [ ] existe endpoint `/debug/system` só para ambiente local
* [ ] `worker.php` está documentado com passo de execução separado
* [ ] existe verificação clara se o worker está rodando ou não

## Resultado esperado

Qualquer pessoa com XAMPP consegue subir o projeto sem tentativa e erro. O projeto hoje já exige dependências específicas no Composer, então essa frente é obrigatória.

---

# Frente 2 — Contrato de resposta e tratamento global de erros

## Escopo

Garantir que o frontend nunca mais receba HTML acidental em chamadas AJAX.

## Entregáveis

* handler global de exceções
* padrão único para resposta JSON
* distinção clara entre erro de tela HTML e erro de API
* log estruturado por request

## Checklist

* [ ] todo endpoint AJAX retorna JSON sempre
* [ ] `Response::json()` encerra a execução sempre
* [ ] qualquer exceção de controller vira JSON em rotas AJAX
* [ ] erros PHP não são exibidos na tela em ambiente normal, só logados
* [ ] existe middleware ou camada central para capturar exceções
* [ ] respostas de erro possuem formato único: `success`, `message`, `code`, `details?`
* [ ] frontend trata erro de rede, erro de JSON e erro de negócio separadamente
* [ ] `storage/php-error.log` e logs de aplicação ficam separados

## Resultado esperado

Fim dos erros `Unexpected token '<'` e `Unexpected end of JSON input`, que são sintomas de resposta inconsistente entre backend e frontend.

---

# Frente 3 — Redução de acoplamento do ChatController

## Escopo

O `ChatController` atual concentra responsabilidades demais: IA, Git, workspace, leitura de anexo, histórico, contexto e fila. Isso precisa ser quebrado em serviços menores.

## Entregáveis

* `ChatContextBuilder`
* `AttachmentContextService`
* `GitContextService`
* `ChatHistoryService`
* `ChatJobDispatcher`
* `ChatController` apenas orquestrando

## Checklist

* [ ] `ChatController` não lê arquivo diretamente
* [ ] `ChatController` não monta contexto manualmente
* [ ] `ChatController` não conhece regras de documento
* [ ] histórico do chat sai do controller
* [ ] integração com fila sai do controller
* [ ] contexto do Git sai do controller
* [ ] existe serviço único que devolve o “contexto final do prompt”
* [ ] testes de unidade cobrem esse builder de contexto

## Resultado esperado

Mais legibilidade, menos regressão e evolução futura muito mais simples para OpenAI, Ollama, multi-arquivo e anexos.

---

# Frente 4 — Camada de documentos estável

## Escopo

Consolidar a pasta `app/Documents` como subsistema real, com contrato claro e fallback seguro por tipo de arquivo. O projeto já aponta nessa direção com `DocumentManager` e dependências para Excel/PDF.

## Entregáveis

* `DocumentReaderInterface` definitivo
* `TextReader`
* `SpreadsheetReader`
* `PdfReader`
* `ImageReader` planejado
* `DocumentManager` com registro de leitores e fallback
* logs de falha por leitor

## Checklist

* [ ] `DocumentManager` só instancia leitores que existem
* [ ] cada reader é testável isoladamente
* [ ] Excel retorna resumo estruturado
* [ ] PDF retorna texto ou motivo claro de falha
* [ ] texto simples funciona para `.txt`, `.md`, `.json`, `.csv`, `.log`
* [ ] anexos não suportados falham com mensagem amigável
* [ ] anexos grandes têm limite e truncamento explícitos
* [ ] existe metadata mínima por anexo: tipo, tamanho, data, nome original, caminho relativo

## Resultado esperado

A IDE passa a realmente “entender” anexos sem instabilidade estrutural.

---

# Frente 5 — Fluxo de IA seguro para edição de código

## Escopo

Hoje a edição por IA já existe, mas ainda depende muito do formato de resposta e pode quebrar código se a estratégia falhar. O próximo nível é tornar isso confiável.

## Entregáveis

* aplicação parcial por padrão
* fallback para arquivo inteiro só quando explicitamente autorizado
* preview antes de aplicar
* rollback real e visível
* validação sintática antes de salvar

## Checklist

* [ ] substituição parcial é a estratégia default
* [ ] replace literal tem validação de ocorrência única/opcional
* [ ] aplicação completa só acontece com confirmação explícita
* [ ] backup é criado sempre antes de alterar
* [ ] existe botão de desfazer funcional e confiável
* [ ] existe preview “antes/depois”
* [ ] PHP alterado passa por `php -l` antes de salvar, quando aplicável
* [ ] mudanças aplicadas ficam registradas em log

## Resultado esperado

Você passa de “prova de conceito de aplicação” para “edição assistida confiável”.

---

# Frente 6 — Git operacional completo e seguro

## Escopo

As rotas do projeto já mostram ambição de Git avançado (push, pull, branches, remotes etc.), mas isso precisa ser consolidado com UX e segurança.

## Entregáveis

* status confiável
* stage/unstage/discard previsíveis
* commit confiável
* branch listing/switch estável
* fetch/pull/push com retorno compreensível
* proteção para comandos perigosos

## Checklist

* [ ] status Git sempre reflete estado real
* [ ] stage e unstage funcionam por arquivo
* [ ] discard exige confirmação
* [ ] commit valida mensagem e usuário Git configurado
* [ ] branch list mostra branch atual
* [ ] switch branch trata alterações não commitadas
* [ ] push/pull retornam mensagens úteis
* [ ] logs registram comando, repo e resultado
* [ ] nada usa shell sem sanitização estrita

## Resultado esperado

Git vira parte confiável do fluxo, não um bloco frágil dentro da IDE.

---

# Frente 7 — Frontend de IDE mais coeso

## Escopo

A UI já tem boa direção, mas precisa virar app mesmo: sem rolagem confusa, com estados claros e comportamento previsível. Você já melhorou parte disso, e vale consolidar.

## Entregáveis

* layout de altura fechada
* painéis independentes
* estados de loading/erro/sucesso
* tabs de arquivo
* seleção clara de anexo
* feedback visual de queue/job

## Checklist

* [ ] não existe scroll global desnecessário
* [ ] cada painel rola isoladamente
* [ ] chat mostra status de envio e processamento
* [ ] anexos selecionados ficam visualmente destacados
* [ ] editor mostra arquivo atual e estado “modificado”
* [ ] ações perigosas pedem confirmação
* [ ] erros aparecem inline e não só em `alert()`
* [ ] fila de jobs tem painel simples de acompanhamento

## Resultado esperado

A ferramenta fica usável de verdade por horas, não só “funcional”.

---

# Frente 8 — Fila e worker observáveis

## Escopo

O projeto já usa `worker.php` e fila para chat, então isso precisa sair da condição “mágica” e entrar em modo observável.

## Entregáveis

* status do worker
* lista de jobs
* retry controlado
* logs por job
* limpeza de jobs concluídos/expirados

## Checklist

* [ ] worker roda com comando documentado
* [ ] existe indicador visual se o worker está ativo
* [ ] jobs mostram status: pendente, processando, concluído, erro
* [ ] erro do job fica persistido
* [ ] retry não duplica resultado
* [ ] clear completed não apaga jobs com erro sem confirmação
* [ ] timeout de job é tratado
* [ ] queue usa formato previsível em disco ou storage

## Resultado esperado

O chat deixa de parecer “quebrado” quando na verdade está apenas esperando worker.

---

# Frente 9 — Observabilidade e suporte a manutenção

## Escopo

Criar instrumentos para você entender o sistema sem depender de tentativa e erro.

## Entregáveis

* logger central
* níveis de log
* request id
* log de IA
* log de Git
* log de documentos

## Checklist

* [ ] cada request recebe um ID
* [ ] logs incluem timestamp, módulo e mensagem
* [ ] chamadas à IA registram modelo, tamanho do contexto e resultado
* [ ] chamadas Git registram comando e saída resumida
* [ ] leitura de documento registra tipo, tamanho e sucesso/erro
* [ ] logs do app e logs PHP ficam separados
* [ ] existe modo debug local controlado por config

## Resultado esperado

Você consegue diagnosticar erro em minutos, não em horas.

---

# Frente 10 — Contrato de configuração e ambiente

## Escopo

Padronizar tudo que hoje está espalhado em arquivos e estados locais.

## Entregáveis

* arquivo `.env` ou `config` central consistente
* flags de ambiente
* config de OpenAI
* config de storage/queue
* config de debug

## Checklist

* [ ] provider de IA é configurável sem editar controller
* [ ] modelo e limites de tokens são configuráveis
* [ ] diretórios de storage são configuráveis
* [ ] modo local/prod está definido
* [ ] endpoints críticos não têm URL hardcoded desnecessária
* [ ] feature flags permitem desligar módulos instáveis

## Resultado esperado

Menos divergência entre ambiente local, testes e futuro deploy.

---

# Frente 11 — Testes mínimos obrigatórios

## Escopo

Sem testes, o projeto vai continuar voltando para erros já corrigidos.

## Entregáveis

* testes do boot
* testes de workspace
* testes de Git service
* testes de document readers
* testes de response JSON

## Checklist

* [ ] `/health` testado
* [ ] `WorkspaceManager` testado
* [ ] `DocumentManager` testado por tipo
* [ ] `Response::json()` testado
* [ ] `ChatContextBuilder` testado
* [ ] `GitService` testado com casos básicos
* [ ] parser de aplicação parcial testado

## Resultado esperado

Regressões começam a cair bastante.

---

# Prioridade prática de ataque

Se o objetivo é chegar perto de 9/10 em todos os quesitos, eu atacaria nesta ordem:

## Sprint 1 — estabilização bruta

* boot local
* JSON consistente
* logs
* remover HTML acidental em APIs
* storage/checks

## Sprint 2 — desacoplamento central

* quebrar `ChatController`
* consolidar `DocumentManager`
* consolidar `WorkspaceManager`
* consolidar fila

## Sprint 3 — confiabilidade funcional

* edição segura por IA
* Git previsível
* anexos confiáveis

## Sprint 4 — refinamento de produto

* UX
* tabs
* preview de mudanças
* observabilidade melhor
* docs de setup

---

# Checklist executivo resumido

## Para sair do estado atual e subir muito a maturidade:

* [ ] boot local previsível
* [ ] todas APIs retornando JSON válido sempre
* [ ] `ChatController` desacoplado
* [ ] `DocumentManager` funcional por tipo
* [ ] edição IA segura com preview e rollback
* [ ] Git completo e confiável
* [ ] worker observável
* [ ] logs centralizados
* [ ] config central consistente
* [ ] testes mínimos nos fluxos críticos

---

# Minha recomendação final

O melhor ataque agora não é “mais feature”.
É este:

**congele novas features por um curto período e execute uma fase de estabilização arquitetural.**

Esse é o caminho mais rápido para o projeto deixar de parecer experimental e começar a se comportar como uma plataforma sólida. O repositório já tem escopo suficiente para isso; o ganho agora vem de consolidar o que já foi construído.

Se você quiser, no próximo passo eu posso transformar esse escopo em um **plano de execução por sprint**, com prioridade, ordem de arquivos e entregáveis de cada fase.
