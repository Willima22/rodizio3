# Sistema de Gerenciamento de Salão - Fast Escova

## 📋 Checklist de Desenvolvimento

### Fase 1: Estrutura Base
- [x] **1.1** Criar estrutura de pastas e arquivos
- [x] **1.2** Configurar arquivos base (.htaccess, index.php)
- [x] **1.3** Configurar conexão com banco de dados
- [x] **1.4** Criar classes utilitárias (DB, Response, Auth, etc.)

### Fase 2: Autenticação e Segurança  
- [x] **2.1** Implementar AuthController (login manual)
- [x] **2.2** Implementar NfcController (login por NFC)
- [x] **2.3** Sistema de sessões e CSRF
- [x] **2.4** Validação e sanitização
- [x] **2.5** Sistema de logs

### Fase 3: Models e Regras de Negócio
- [x] **3.1** Criar BaseModel
- [x] **3.2** Implementar Models (Usuario, Cliente, Profissional, etc.)
- [x] **3.3** Algoritmo de distribuição automática
- [x] **3.4** Regras de duplicidade e validação

### Fase 4: Controllers de Negócio
- [x] **4.1** ClienteController (cadastro, fila)
- [x] **4.2** ProfissionalController (status, chegada, saída)
- [x] **4.3** AtendimentoController (distribuir, iniciar, finalizar)
- [x] **4.4** ServicoController (CRUD)
- [x] **4.5** RelatorioController (métricas, exportação)

### Fase 5: Interface do Usuário
- [ ] **5.1** Layout base e CSS
- [ ] **5.2** Páginas de autenticação
- [ ] **5.3** Interface de recepção
- [ ] **5.4** Painel do profissional
- [ ] **5.5** Dashboard do gestor

### Fase 3: Models e Regras de Negócio
- [ ] **3.1** Criar BaseModel
- [ ] **3.2** Implementar Models (Usuario, Cliente, Profissional, etc.)
- [ ] **3.3** Algoritmo de distribuição automática
- [ ] **3.4** Regras de duplicidade e validação

### Fase 4: Controllers de Negócio
- [ ] **4.1** ClienteController (cadastro, fila)
- [ ] **4.2** ProfissionalController (status, chegada, saída)
- [ ] **4.3** AtendimentoController (distribuir, iniciar, finalizar)
- [ ] **4.4** ServicoController (CRUD)
- [ ] **4.5** RelatorioController (métricas, exportação)

### Fase 5: Interface do Usuário
- [ ] **5.1** Layout base e CSS
- [ ] **5.2** Páginas de autenticação
- [ ] **5.3** Interface de recepção
- [ ] **5.4** Painel do profissional
- [ ] **5.5** Dashboard do gestor

### Fase 6: APIs e Integração
- [ ] **6.1** APIs REST (auth, clientes, profissionais, etc.)
- [ ] **6.2** WebNFC JavaScript
- [ ] **6.3** Polling em tempo real
- [ ] **6.4** Atualização automática da interface

### Fase 7: Relatórios e Finalização
- [ ] **7.1** Sistema de relatórios
- [ ] **7.2** Exportação CSV
- [ ] **7.3** Testes finais
- [ ] **7.4** Documentação README

---

## 🗂️ Comandos CMD para Criar Estrutura

```cmd
mkdir fast-escova
cd fast-escova

mkdir config
mkdir public
mkdir routes
mkdir src
mkdir api

cd public
mkdir assets
mkdir uploads
cd ..

cd src
mkdir Controllers
mkdir Models
mkdir Utils
mkdir views
cd ..

cd src\views
mkdir auth
mkdir recepcao
mkdir profissional
mkdir gestor
cd ..\..

cd src\views\auth
echo. > login.php
echo. > nfc.php
cd ..

cd recepcao
echo. > cadastro.php
echo. > fila.php
cd ..

cd profissional
echo. > painel.php
cd ..

cd gestor
echo. > dashboard.php
echo. > profissionais.php
echo. > servicos.php
echo. > relatorios.php
cd ..\..\..

cd config
echo. > app.php
echo. > database.php
cd ..

cd public
echo. > index.php
echo. > .htaccess
cd assets
echo. > style.css
echo. > webnfc.js
echo. > app.js
cd ..\..

cd routes
echo. > web.php
cd ..

cd src\Controllers
echo. > AuthController.php
echo. > ProfissionalController.php
echo. > ClienteController.php
echo. > AtendimentoController.php
echo. > ServicoController.php
echo. > GestorController.php
echo. > RelatorioController.php
echo. > NfcController.php
echo. > LogController.php
cd ..\..

cd src\Models
echo. > BaseModel.php
echo. > Usuario.php
echo. > Perfil.php
echo. > Cliente.php
echo. > Profissional.php
echo. > Servico.php
echo. > Atendimento.php
echo. > Log.php
echo. > SessaoNfc.php
cd ..\..

cd src\Utils
echo. > Auth.php
echo. > Response.php
echo. > Validator.php
echo. > Sanitizer.php
echo. > CSRF.php
echo. > Date.php
echo. > DB.php
cd ..\..

cd api
echo. > auth.php
echo. > clientes.php
echo. > profissionais.php
echo. > atendimentos.php
echo. > servicos.php
echo. > relatorios.php
echo. > nfc.php
cd ..

echo. > README.md
```

---

## 📊 Análise do Banco de Dados (SQL Anexado)

### Adaptações Necessárias da Especificação:

1. **Campo `preco_padrao` → `preco`** em `servicos`
2. **Campo `login` → `usuario`** em `usuarios`
3. **Campo `email`** não existe em `usuarios`, apenas em `clientes`
4. **Campo `ativo`** não existe em `clientes`
5. **Tabela `sessoes_nfc`** adicionada (não estava na spec)
6. **Procedure `ResetRodizio()`** já existe
7. **Evento `reset_diario`** já configurado

### Estrutura Real das Tabelas:

**atendimentos:**
- id (char36), id_cliente, id_profissional, id_servico
- hora_inicio, hora_fim, status (enum), valor_cobrado, observacoes
- criado_em, atualizado_em

**clientes:**
- id (char36), nome, telefone, email, data_nascimento, observacoes
- criado_em, atualizado_em

**profissionais:**
- id (char36), nome, nfc_uid, senha, status (enum), ordem_chegada
- total_atendimentos_dia, ativo, criado_em, atualizado_em

**usuarios:**
- id (char36), nome, usuario, senha, id_perfil, ativo
- criado_em, atualizado_em

**servicos:**
- id (char36), nome, preco, tempo_estimado, ativo
- criado_em, atualizado_em

---

## 🎯 Próximos Passos

1. **Executar comandos CMD** para criar estrutura
2. **Começar Fase 1.2** - Configurar arquivos base
3. **Desenvolver arquivo por arquivo** conforme checklist

---

## 📝 Notas de Desenvolvimento

- **IDs:** Usar char(36) UUID conforme banco
- **Senhas:** BCRYPT já configurado no banco
- **Foreign Keys:** Seguir exatamente as referências do SQL
- **Enum Values:** Respeitar valores exatos do banco
- **Procedure:** Usar `CALL ResetRodizio()` para reset diário

---

**Status Atual:** ✅ Fase 4 CONCLUÍDA - Controllers de negócio implementados

### Próximo Passo:
Iniciar **Fase 5.1** - Layout base e CSS

**Arquivos Criados na Fase 4:**
- ✅ src/Controllers/ClienteController.php (cadastro, fila, busca, estatísticas)
- ✅ src/Controllers/ProfissionalController.php (status, chegada/saída, ranking)
- ✅ src/Controllers/AtendimentoController.php (distribuição automática/manual, FIFO)
- ✅ src/Controllers/ServicoController.php (CRUD completo, estatísticas)
- ✅ src/Controllers/RelatorioController.php (indicadores, gráficos, exportação CSV)

**Funcionalidades Principais Implementadas:**
- ✅ Algoritmo de distribuição FIFO + rodízio justo
- ✅ Regras de duplicidade diária por cliente
- ✅ Sistema completo de métricas e relatórios
- ✅ Exportação CSV de relatórios
- ✅ Controle de status de profissionais
- ✅ Sistema de logs para auditoria

**Próximos Arquivos (Fase 5):**
- [ ] public/assets/style.css
- [ ] src/views/auth/login.php
- [ ] src/views/auth/nfc.php
- [ ] src/views/recepcao/cadastro.php
- [ ] src/views/recepcao/fila.php