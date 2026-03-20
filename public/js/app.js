// ============================================================
// FRAMEWORKIA - Vue.js Application
// Frontend IDE with AI Integration
// ============================================================

const { createApp, ref, reactive, computed, watch, onMounted } = Vue;

const app = createApp({
    template: `
        <div id="app">
            <!-- HEADER -->
            <div class="header">
                <div class="header-logo">Frameworkia</div>
                <div class="header-actions">
                    <button class="header-btn" @click="showSettings = true">⚙️ Configurações</button>
                    <button class="header-btn" @click="saveProject">💾 Salvar</button>
                    <button class="header-btn" @click="logout">🚪 Sair</button>
                </div>
            </div>

            <!-- CONTAINER PRINCIPAL -->
            <div class="container">
                <!-- SIDEBAR - FILE EXPLORER -->
                <div class="sidebar">
                    <div class="sidebar-header">
                        <h3>🗂️ Explorador</h3>
                        <button class="sidebar-btn" @click="showNewFileModal = true" title="Novo arquivo">➕</button>
                    </div>
                    <div class="file-explorer">
                        <div class="file-tree">
                            <div 
                                v-for="file in files" 
                                :key="file.path"
                                class="file-item"
                                :class="{ active: currentFile?.path === file.path }"
                                @click="selectFile(file)"
                            >
                                <span class="file-item-icon">📄</span>
                                <span>{{ file.name }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- EDITOR AREA -->
                <div class="editor-area">
                    <!-- TABS -->
                    <div class="tabs" v-if="openTabs.length > 0">
                        <div 
                            v-for="tab in openTabs"
                            :key="tab.path"
                            class="tab"
                            :class="{ active: currentTab?.path === tab.path }"
                            @click="selectTab(tab)"
                        >
                            <span>{{ tab.name }}</span>
                            <button class="tab-close" @click.stop="closeTab(tab)">✕</button>
                        </div>
                    </div>

                    <!-- EDITOR + CHAT -->
                    <div class="editor-content">
                        <!-- EDITOR -->
                        <div class="editor" v-if="currentTab">
                            <div class="editor-header">
                                📝 {{ currentTab.name }}
                                <span style="margin-left: auto; color: var(--text-tertiary);">
                                    {{ currentTab.modified ? '●' : '' }} Linhas: {{ editorLines.length }}
                                </span>
                            </div>
                            <div class="editor-wrapper">
                                <div class="editor-lines">
                                    <div v-for="(_, i) in editorLines" :key="i">{{ i + 1 }}</div>
                                </div>
                                <textarea 
                                    v-model="currentTab.content"
                                    class="editor-input"
                                    @input="currentTab.modified = true"
                                    @keydown.tab="handleTab"
                                    spellcheck="false"
                                ></textarea>
                                <div class="editor-code">
                                    <code v-html="highlightedCode"></code>
                                </div>
                            </div>
                        </div>

                        <div style="flex: 1; display: flex; align-items: center; justify-content: center; color: var(--text-tertiary);" v-else>
                            Selecione um arquivo para começar
                        </div>

                        <!-- CHAT SIDEBAR -->
                        <div class="chat-sidebar">
                            <div class="chat-header">
                                <h2>🤖 Assistente IA</h2>
                                <select v-model="selectedModel" class="chat-model-select">
                                    <option value="gpt-4o-mini">GPT-4o Mini</option>
                                    <option value="gpt-4">GPT-4</option>
                                    <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                                </select>
                            </div>
                            <div class="chat-messages">
                                <div 
                                    v-for="(msg, i) in chatMessages" 
                                    :key="i"
                                    class="message"
                                    :class="msg.role"
                                >
                                    {{ msg.content }}
                                </div>
                            </div>
                            <div class="chat-input-area">
                                <div class="chat-input-wrapper">
                                    <textarea 
                                        v-model="chatInput"
                                        class="chat-input"
                                        placeholder="Digite sua pergunta..."
                                        @keydown.enter.ctrl="sendMessage"
                                    ></textarea>
                                    <button 
                                        class="chat-btn"
                                        @click="sendMessage"
                                        :disabled="!chatInput || chatLoading"
                                    >
                                        {{ chatLoading ? '⏳' : '📤' }}
                                    </button>
                                </div>
                                <div style="font-size: 11px; color: var(--text-tertiary); text-align: center;">
                                    Ctrl+Enter para enviar
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- NOTIFICATIONS -->
            <div class="notifications">
                <div 
                    v-for="(notif, i) in notifications"
                    :key="i"
                    class="notification"
                    :class="notif.type"
                >
                    <span class="notification-icon">
                        {{ notif.type === 'success' ? '✅' : notif.type === 'error' ? '❌' : 'ℹ️' }}
                    </span>
                    <span class="notification-message">{{ notif.message }}</span>
                    <button class="notification-close" @click="notifications.splice(i, 1)">✕</button>
                </div>
            </div>

            <!-- NEW FILE MODAL -->
            <div v-if="showNewFileModal" class="modal-overlay" @click.self="showNewFileModal = false">
                <div class="modal">
                    <div class="modal-header">
                        <h2 class="modal-title">Novo Arquivo</h2>
                        <button class="modal-close" @click="showNewFileModal = false">✕</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Nome do arquivo</label>
                            <input 
                                v-model="newFileName"
                                type="text"
                                class="form-input"
                                placeholder="exemplo.php"
                            >
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" @click="showNewFileModal = false">Cancelar</button>
                        <button class="btn btn-primary" @click="createFile">Criar</button>
                    </div>
                </div>
            </div>

            <!-- SETTINGS MODAL -->
            <div v-if="showSettings" class="modal-overlay" @click.self="showSettings = false">
                <div class="modal">
                    <div class="modal-header">
                        <h2 class="modal-title">Configurações</h2>
                        <button class="modal-close" @click="showSettings = false">✕</button>
                    </div>
                    <div class="modal-body">
                        <h3 style="margin-bottom: 15px; color: var(--primary-color);">IDE Settings</h3>
                        <div class="form-group">
                            <label class="form-label">Auto-save (segundos)</label>
                            <input 
                                v-model="settings.autoSaveInterval"
                                type="number"
                                class="form-input"
                            >
                        </div>
                        <div class="form-group">
                            <label class="form-label">Máximo de abas</label>
                            <input 
                                v-model="settings.maxTabs"
                                type="number"
                                class="form-input"
                            >
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" @click="showSettings = false">Fechar</button>
                        <button class="btn btn-primary" @click="saveSettings">Salvar</button>
                    </div>
                </div>
            </div>
        </div>
    `,

    setup() {
        // State
        const files = ref([
            { path: '/index.php', name: 'index.php', content: '<?php\n\n?>', modified: false },
            { path: '/composer.json', name: 'composer.json', content: '{}', modified: false }
        ]);

        const openTabs = ref([]);
        const currentTab = ref(null);
        const currentFile = ref(null);

        const chatMessages = ref([
            { role: 'system', content: 'Bem-vindo ao Frameworkia! Como posso ajudá-lo?' }
        ]);
        const chatInput = ref('');
        const chatLoading = ref(false);
        const selectedModel = ref('gpt-4o-mini');

        const notifications = ref([]);
        const showNewFileModal = ref(false);
        const showSettings = ref(false);
        const newFileName = ref('');

        const settings = reactive({
            autoSaveInterval: 5,
            maxTabs: 20
        });

        // Computed
        const editorLines = computed(() => {
            if (!currentTab.value) return [];
            return currentTab.value.content.split('\n');
        });

        const highlightedCode = computed(() => {
            if (!currentTab.value) return '';
            let code = currentTab.value.content;
            if (code.length > 10000) {
                return code.replace(/</g, '&lt;').replace(/>/g, '&gt;');
            }
            try {
                return hljs.highlight(code, { language: 'php', ignoreIllegals: true }).value;
            } catch (e) {
                return code.replace(/</g, '&lt;').replace(/>/g, '&gt;');
            }
        });

        // Methods
        const selectFile = (file) => {
            if (!openTabs.value.find(t => t.path === file.path)) {
                if (openTabs.value.length >= settings.maxTabs) {
                    showNotification('Máximo de abas atingido', 'warning');
                    return;
                }
                openTabs.value.push({ ...file });
            }
            currentTab.value = openTabs.value.find(t => t.path === file.path);
            currentFile.value = file;
        };

        const selectTab = (tab) => {
            currentTab.value = tab;
            currentFile.value = files.value.find(f => f.path === tab.path);
        };

        const closeTab = (tab) => {
            if (tab.modified) {
                if (!confirm('Você tem alterações não salvas. Deseja fechar mesmo assim?')) {
                    return;
                }
            }
            const idx = openTabs.value.indexOf(tab);
            openTabs.value.splice(idx, 1);
            if (currentTab.value === tab) {
                currentTab.value = openTabs.value[0] || null;
            }
        };

        const createFile = () => {
            if (!newFileName.value.trim()) {
                showNotification('Nome do arquivo é obrigatório', 'error');
                return;
            }
            const newFile = {
                path: '/' + newFileName.value,
                name: newFileName.value,
                content: '',
                modified: false
            };
            files.value.push(newFile);
            selectFile(newFile);
            showNewFileModal.value = false;
            newFileName.value = '';
            showNotification('Arquivo criado: ' + newFileName.value, 'success');
        };

        const sendMessage = async () => {
            if (!chatInput.value.trim()) return;

            const userMessage = chatInput.value;
            chatMessages.value.push({ role: 'user', content: userMessage });
            chatInput.value = '';
            chatLoading.value = true;

            try {
                const response = await axios.post('/api/chat/send', {
                    mensagem: userMessage,
                    contexto: currentTab.value?.content || '',
                    modelo: selectedModel.value
                });

                if (response.data.sucesso) {
                    chatMessages.value.push({
                        role: 'assistant',
                        content: response.data.dados.resposta
                    });
                } else {
                    showNotification(response.data.mensagem, 'error');
                }
            } catch (error) {
                showNotification('Erro ao enviar mensagem: ' + error.message, 'error');
            } finally {
                chatLoading.value = false;
            }
        };

        const saveProject = async () => {
            try {
                const workspace = {
                    files: files.value,
                    openTabs: openTabs.value.map(t => ({ path: t.path, name: t.name }))
                };
                await axios.post('/api/workspace/save', workspace);
                files.value.forEach(f => f.modified = false);
                openTabs.value.forEach(t => t.modified = false);
                showNotification('Projeto salvo com sucesso!', 'success');
            } catch (error) {
                showNotification('Erro ao salvar projeto: ' + error.message, 'error');
            }
        };

        const saveSettings = () => {
            localStorage.setItem('frameworkia-settings', JSON.stringify(settings));
            showSettings.value = false;
            showNotification('Configurações salvas!', 'success');
        };

        const logout = () => {
            if (confirm('Deseja sair?')) {
                window.location.href = '/logout';
            }
        };

        const handleTab = (e) => {
            if (e.key === 'Tab') {
                e.preventDefault();
                const ta = e.target;
                const start = ta.selectionStart;
                const end = ta.selectionEnd;
                ta.value = ta.value.substring(0, start) + '\t' + ta.value.substring(end);
                ta.selectionStart = ta.selectionEnd = start + 1;
                currentTab.value.modified = true;
            }
        };

        const showNotification = (message, type = 'info') => {
            const notif = { message, type };
            notifications.value.push(notif);
            setTimeout(() => {
                const idx = notifications.value.indexOf(notif);
                if (idx > -1) notifications.value.splice(idx, 1);
            }, 3000);
        };

        // Lifecycle
        onMounted(() => {
            const saved = localStorage.getItem('frameworkia-settings');
            if (saved) {
                Object.assign(settings, JSON.parse(saved));
            }
            showNotification('Bem-vindo ao Frameworkia!', 'success');
        });

        return {
            files,
            openTabs,
            currentTab,
            currentFile,
            editorLines,
            highlightedCode,
            chatMessages,
            chatInput,
            chatLoading,
            selectedModel,
            notifications,
            showNewFileModal,
            showSettings,
            newFileName,
            settings,
            selectFile,
            selectTab,
            closeTab,
            createFile,
            sendMessage,
            saveProject,
            saveSettings,
            logout,
            handleTab,
            showNotification
        };
    }
});

app.mount('#app');
