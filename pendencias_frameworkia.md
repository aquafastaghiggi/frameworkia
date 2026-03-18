# 🧠 Frameworkia: O que falta implementar?

Após as implementações das **Prioridades 1, 2, 4 e 5**, o sistema agora é muito mais estável e capaz. No entanto, para atingir a visão final de uma "IDE AI Autônoma", ainda existem passos importantes no roadmap:

## 1. 🤖 IA e Inteligência (Fases 8 e 11)
- **Multi-contexto Avançado:** Permitir que a IA analise **múltiplos arquivos simultaneamente** (ex: ler um `Controller` e o seu `Model` ao mesmo tempo para sugerir mudanças).
- **Indexação de Conteúdo:** Criar um índice (ou vetores simples) para que a IA "conheça" todo o projeto sem precisar ler todos os arquivos em cada prompt.

## 2. 📄 Document Intelligence (Fases 10 e 11)
- **Insights Automáticos de Excel:** Fazer a IA detetar cabeçalhos e gerar resumos ou gráficos automáticos a partir de planilhas carregadas.
- **Análise Semântica de PDF:** Melhorar o parser de PDF para que a IA consiga extrair informações estruturadas de documentos complexos.
- **Chunking de Conteúdo:** Para arquivos muito grandes, implementar a divisão em pedaços (chunks) para não estourar o limite de tokens da IA.

## 3. 🧬 Git Avançado (Fase 13)
- **Merge Assistido por IA:** Usar a IA para ajudar a resolver conflitos de merge.
- **Commit Automático por IA:** Sugerir mensagens de commit baseadas nas alterações detectadas no stage.
- **Visualizador de Diff Gráfico:** Uma interface para comparar visualmente as versões antes de confirmar o commit (estilo VSCode).

## 4. 🔐 Segurança e Performance (Fases 15 e 16)
- **Sandbox de Arquivos:** Restringir ainda mais o que o sistema pode ler/escrever para evitar danos acidentais ao sistema operacional.
- **Controle de Permissões:** Sistema básico de login e permissões por workspace.
- **Fila para IA (Queue):** Implementar uma fila de processamento para requisições longas da IA, evitando timeouts no navegador.
- **Cache de Arquivos:** Melhorar a velocidade de carregamento do Explorer lateral em projetos grandes.

## 5. 🧠 AI Agent Autônomo (Fase 17)
- **Planejamento de Tarefas:** Capacidade da IA de quebrar um pedido complexo em várias etapas e executá-las uma a uma.
- **Auto-debug:** Se a IA sugerir um código que cause erro, ela deve ser capaz de ler o log de erro e tentar corrigir automaticamente.

---

### 🚀 Próxima Sugestão de Prioridade:
Se quiser continuar a evolução, recomendo focar na **Fase 12 (Ollama)** para ter independência de APIs pagas, ou na **Fase 11 (Multi-contexto)** para que a IA consiga trabalhar em projetos reais que envolvem vários arquivos.

*Relatório gerado em 17/03/2026*
