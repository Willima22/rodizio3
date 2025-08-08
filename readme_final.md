# Fast Escova - Sistema de Gerenciamento de Salão

Sistema inteligente de gerenciamento de atendimentos para salões de beleza com distribuição automática de clientes e rodízio justo entre profissionais.

## 🚀 Funcionalidades Principais

### ✨ **Distribuição Inteligente**
- **Algoritmo FIFO**: Primeiro a entrar, primeiro a ser atendido
- **Rodízio Justo**: Distribuição equilibrada entre profissionais
- **Controle de Carga**: Profissional com menos atendimentos recebe prioridade
- **Distribuição Manual**: Gestor pode forçar atribuições específicas

### 👥 **Gestão de Fila**
- **Cadastro Simples**: Nome, telefone, serviço desejado
- **Duplicidade Diária**: Impede cliente na fila duas vezes no mesmo dia
- **Tempo Real**: Interface atualiza automaticamente a cada 12 segundos
- **Estimativas**: Tempo de espera calculado dinamicamente

### 🔐 **Autenticação Dual**
- **Login Manual**: Usuário e senha para equipe administrativa
- **Login NFC**: Profissionais fazem login aproximando cartão (Chrome Android)
- **Perfis de Acesso**: Administrador, Gestor, Recepção, Profissional
- **Sessões Seguras**: CSRF, cookies HttpOnly, regeneração automática

### 📊 **Relatórios Completos**
- **Indicadores em Tempo Real**: Tempo médio de espera/atendimento
- **Performance por Profissional**: Ranking, carga de trabalho, eficiência
- **Gráficos**: Atendimentos por hora do dia
- **Exportação CSV**: Relatórios detalhados para análise externa
- **Métricas Financeiras**: Faturamento por período, profissional e serviço

### 🎯 **Interface Moderna**
- **Design Responsivo**: Funciona em desktop, tablet e celular
- **Painel do Profissional**: Cronômetro ao vivo, histórico do dia
- **Dashboard Gestor**: Métricas, gráficos, ações rápidas
- **Fila Visual**: Status colorido, próximo cliente destacado

## 🏗️ Arquitetura

### **Backend**
- **PHP 8.x** nativo (sem frameworks)
- **Arquitetura MVC** organizada
- **MySQL 8.x** com prepared statements
- **PDO** com modo exception
- **Autoloader** PSR-4 compatível

### **Frontend** 
- **HTML5** semântico
- **CSS3** com variables e grid
- **JavaScript** vanilla (ES6+)
- **Fetch API** para AJAX
- **Chart.js** para gráficos
- **WebNFC API** para login por cartão

### **Segurança**
- **Sanitização**: Todas as entradas são sanitizadas
- **Validação**: Frontend e backend
- **CSRF Protection**: Tokens em formulários
- **SQL Injection**: 100% prepared statements
- **XSS Prevention**: Escape de saídas
- **Rate Limiting**: Proteção contra ataques de força bruta

## 📋 Requisitos

### **Servidor**
- PHP 8.0 ou superior
- MySQL 8.0 ou superior
- Apache com mod_rewrite
- Suporte a .htaccess
- 128MB RAM mínimo

### **Cliente (NFC)**
- Chrome no Android
- NFC habilitado no dispositivo
- HTTPS (obrigatório para WebNFC)

## 📦 Instalação

### 1. **Estrutura de Arquivos**

```bash
# Baixar arquivos do sistema
# Enviar via FTP para o cPanel na pasta public_html

fast-escova/
├── config/
├── public/ (← Apontar domínio aqui)
├── src/
├── api/
└── routes/
```

### 2. **Configuração do Banco**

```sql
-- Importar o arquivo SQL fornecido:
-- opapopol_07082025.sql

-- Verificar se as tabelas foram criadas:
SHOW TABLES;

-- Verificar dados iniciais:
SELECT * FROM usuarios;
SELECT * FROM perfis;
SELECT * FROM profissionais;
SELECT * FROM servicos;
```

### 3. **Configuração da Aplicação**

Editar `config/database.php`:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'seu_banco');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
```

### 4. **Configuração do Apache**

O arquivo `.htaccess` já está configurado. Verificar se:
- `mod_rewrite` está habilitado
- `AllowOverride All` está permitido
- Arquivos foram enviados mantendo a estrutura

### 5. **Permissões**

```bash
# Via cPanel File Manager ou FTP
chmod 755 public/uploads/
chmod 644 public/.htaccess
```

## 👤 Usuários Padrão

### **Administrativos** (Login Manual)
```
Usuário: admin
Senha: password
Perfil: Administrador

Usuário: recepcao  
Senha: password
Perfil: Recepção
```

### **Profissionais** (Login NFC)
```
Nome: Maria Silva
Nome: Ana Costa  
Nome: Carla Santos
Senha: password (para associar NFC)
```

> **Nota**: Alterar senhas padrão em produção!

## 🔧 Configuração do NFC

### 1. **Associar Cartão a Profissional**

1. Login como gestor/admin
2. Ir em "Gerenciar Profissionais"
3. Editar profissional
4. Usar Chrome Android para ler cartão
5. Associar UID ao profissional

### 2. **Teste de Login NFC**

1. Acessar `/nfc` no Chrome Android
2. Clicar "Ativar NFC"
3. Aproximar cartão associado
4. Login automático no painel

## 📱 Fluxo de Uso

### **Recepção**
1. **Cadastrar Cliente**: Nome, telefone, serviço → Adiciona na fila
2. **Monitorar Fila**: Visualiza tempo de espera em tempo real
3. **Distribuir Manual**: Gestor pode forçar distribuições específicas

### **Profissional**
1. **Chegada**: Clica "Cheguei" ou faz login NFC
2. **Receber Cliente**: Sistema distribui automaticamente
3. **Atender**: Cronômetro roda automaticamente
4. **Finalizar**: Informar valor e observações
5. **Saída**: Registra saída do expediente

### **Gestor**
1. **Dashboard**: Métricas em tempo real
2. **Controlar Fila**: Distribuição manual, remoções
3. **Relatórios**: Performance, ranking, exportação
4. **Administrar**: Profissionais, serviços, usuários

## 📊 Métricas Calculadas

### **Tempo de Espera**
```sql
AVG(TIMESTAMPDIFF(MINUTE, criado_em, hora_inicio))
```

### **Tempo de Atendimento**  
```sql
AVG(TIMESTAMPDIFF(MINUTE, hora_inicio, hora_fim))
```

### **Rodízio Justo**
```sql
ORDER BY total_atendimentos_dia ASC, ordem_chegada ASC
```

## 🚨 Troubleshooting

### **NFC não funciona**
- Verificar se é Chrome no Android
- Confirmar NFC habilitado no dispositivo  
- Usar HTTPS (obrigatório)
- Testar com cartão conhecido

### **Fila não atualiza**
- Verificar conexão de internet
- Console do navegador (F12) para errors
- Confirmar permissões de API

### **Erro de banco**
- Verificar credenciais em `config/database.php`
- Confirmar tabelas foram importadas
- Checar logs do servidor

### **Distribuição não funciona**
- Verificar se há profissionais "livre" e ativo=1
- Confirmar ordem_chegada > 0
- Logs em `logs_sistema` para debug

## 🛡️ Segurança em Produção

### **Senhas**
```sql
-- Alterar senhas padrão
UPDATE usuarios SET senha = '$2y$10$hash_bcrypt_forte' WHERE usuario = 'admin';
```

### **HTTPS**
- Obrigatório para WebNFC
- Configurar certificado SSL
- Redirecionar HTTP → HTTPS

### **Backup**
```bash
# Backup diário do banco
mysqldump -u user -p banco > backup_$(date +%Y%m%d).sql

# Backup dos uploads
tar -czf uploads_backup.tar.gz public/uploads/
```

## 📈 Otimizações

### **Performance**
- Índices já otimizados no SQL
- Polling configurado para 12s (ajustável)
- Cache de consultas frequentes

### **Escalabilidade**
- Suporta múltiplas profissionais
- Banco normalizado
- Queries otimizadas com LIMIT

## 🤝 Suporte

### **Logs do Sistema**
- Acesso: Dashboard > Logs (apenas admin)
- Localização: Tabela `logs_sistema`
- Tipos: login, logout, atendimento, erro, sistema

### **Debug**
```php
// Em config/app.php
define('APP_ENV', 'development'); // Mostra erros
define('APP_DEBUG', true);        // Debug ativo
```

### **Contato**
- Sistema desenvolvido para Fast Escova
- Versão 1.0.0
- Compatível com cPanel/PHP 8.x

---

## 📄 Estrutura do Banco

### **Tabelas Principais**
- `usuarios` - Login administrativo
- `profissionais` - Equipe do salão + NFC
- `clientes` - Base de clientes
- `servicos` - Catálogo de serviços
- `atendimentos` - Fila e histórico
- `logs_sistema` - Auditoria completa

### **Relacionamentos**
- Atendimento → Cliente (N:1)
- Atendimento → Profissional (N:1) 
- Atendimento → Serviço (N:1)
- Usuário → Perfil (N:1)

---

**🎉 Sistema pronto para uso! Bom trabalho!**
