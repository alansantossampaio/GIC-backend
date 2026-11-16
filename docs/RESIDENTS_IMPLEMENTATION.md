# Implementação do CRUD de Moradores

## Sumário das Mudanças

### Backend (Laravel)

#### 1. **Arquitetura Service/Repository**

**Contrato de Interface**
- [`app/Repositories/Contracts/ResidentInterface.php`](/C:/sistema-gestao-condominio/app/Repositories/Contracts/ResidentInterface.php)
  - Define os métodos para gerenciar residentes
  - Implementado por `ResidentRepository`

**Implementação do Repositório**
- [`app/Repositories/Core/ResidentRepository.php`](/C:/sistema-gestao-condominio/app/Repositories/Core/ResidentRepository.php)
  - Realiza operações CRUD no banco de dados
  - Encapsula lógica de persistência

**Serviços**
- [`app/Services/ResidentService.php`](/C:/sistema-gestao-condominio/app/Services/ResidentService.php)
  - Orquestra operações de criar, atualizar e deletar residentes
  - Gerencia transações e validações de negócio
  - Protege dados compartilhados entre condomínios
  
- [`app/Services/StructureService.php`](/C:/sistema-gestao-condominio/app/Services/StructureService.php)
  - Gerencia blocos e unidades
  - Valida duplicatas de números de unidade por bloco

**Controller**
- [`app/Http/Controllers/Api/ResidentController.php`](/C:/sistema-gestao-condominio/app/Http/Controllers/Api/ResidentController.php)
  - Responsável apenas por requisições HTTP
  - Delega lógica para serviços
  - Valida permissões e autorização

#### 2. **Autorização e Segurança**

- Apenas o administrador geral (`admin@admin.com.br`) ou usuários com vínculos ativos no condomínio e perfis reconhecidos (`Admin`, `Administrador`, `Síndico`, `Sindico`) podem gerenciar moradores
- Isolamento por condomínio: usuários locais só veem contas vinculadas ao seu condomínio
- Proteção de dados compartilhados: administradores locais não podem alterar dados de um morador vinculado a outro condomínio

#### 3. **Rotas Adicionadas**

```php
// Obter condomínios onde o usuário pode gerenciar moradores
GET /api/resident-condominiums

// Gerenciar moradores e estrutura de um condomínio
GET /api/condominiums/{condominium}/residents
POST /api/condominiums/{condominium}/blocks
POST /api/condominiums/{condominium}/units
POST /api/condominiums/{condominium}/residents
GET /api/condominiums/{condominium}/residents/{link}
PUT /api/condominiums/{condominium}/residents/{link}
DELETE /api/condominiums/{condominium}/residents/{link}
```

#### 4. **Testes**

- [`tests/Unit/ResidentServiceTest.php`](/C:/sistema-gestao-condominio/tests/Unit/ResidentServiceTest.php)
  - Testes unitários da arquitetura
  - Validação de interfaces
  
- [`tests/Feature/ResidentTest.php`](/C:/sistema-gestao-condominio/tests/Feature/ResidentTest.php)
  - Testes de integração (requer SQLite)
  - Casos de isolamento entre condomínios
  - Validação de integridade de dados

#### 5. **Service Provider**

- [`app/Providers/AppServiceProvider.php`](/C:/sistema-gestao-condominio/app/Providers/AppServiceProvider.php)
  - Registro da interface `ResidentInterface` com sua implementação

---

### Frontend (React/Vite + TypeScript)

#### 1. **Serviço de API**

- [`src/services/residents.ts`](/C:/sistema-condominio-front/src/services/residents.ts)
  - Tipagem completa das respostas da API
  - Validação de tipos em tempo de execução
  - Métodos: `listResidentCondominiums()`, `getResidentOverview()`

#### 2. **Página de Moradores**

- [`src/app/admin/moradores/page.tsx`](/C:/sistema-condominio-front/src/app/admin/moradores/page.tsx)
  - Interface completa para CRUD
  - Seleção de condomínio com filtro de permissões
  - Cadastro de blocos e unidades (pré-requisitos)
  - Formulário para criar/editar/deletar moradores
  - Lista de moradores com ações
  - Validação de dados e feedback visual

#### 3. **Navegação**

- [`src/components/layout/sidebar.tsx`](/C:/sistema-condominio-front/src/components/layout/sidebar.tsx)
  - Rota de "Moradores" atualizada para `/admin/moradores`

---

## Fluxo de Uso

### 1. **Seleção de Condomínio**
   - Usuário seleciona um condomínio da lista (filtrada por permissões)

### 2. **Cadastro de Blocos e Unidades**
   - Opcionalmente cria blocos (torres, setores)
   - Cria unidades associando-as a blocos

### 3. **Cadastro de Moradores**
   - Seleciona usuário já vinculado ao condomínio (ou qualquer um, se admin geral)
   - Seleciona unidade
   - Preenche dados pessoais, tipo de vínculo e data de início
   - Sistema valida dados e previne duplicatas

### 4. **Edição de Moradores**
   - Administrador geral pode editar qualquer informação
   - Administrador local não pode alterar dados compartilhados com outros condomínios

### 5. **Exclusão de Moradores**
   - Exclui apenas se não houver:
     - Outros vínculos do morador
     - Ocorrências registradas
     - Reservas ativas
   - A conta de usuário é preservada

---

## Regras de Negócio Implementadas

### RN-020 (Atualizada)
- Todo morador cadastrado está associado a uma conta de usuário
- Dados pessoais são compartilhados entre vínculos do mesmo morador
- Administrador geral pode alterar dados compartilhados; administrador local não

### RN-021
- Vínculo temporal: `unit_residents` registra tipo, início, fim e situação
- Encerramento não apaga histórico

### RN-022
- Uma unidade pode ter vários moradores (simultaneamente com status ativo e sem end_date)

### Isolamento Multi-tenant
- Cada condomínio gerencia seus próprios moradores
- Usuários locais não acessam dados de outros condomínios
- Dados compartilhados entre condomínios são protegidos

---

## Stack Tecnológico

### Backend
- PHP 8.5 + Laravel 13
- Padrão: Service/Repository
- Autenticação: Laravel Sanctum
- Banco: Multi-tenant por condomínio

### Frontend
- React/Vite
- TypeScript 5
- Tailwind CSS 4
- Tipagem em runtime

---

## Próximos Passos

1. Criar testes com banco de dados real ou usar MySQL para CI/CD
2. Adicionar endpoints de relatórios de moradores
3. Integração com financeiro (cobrança de taxa de morador)
4. Integração com ocorrências (suporte a moradores)
5. Dashboard de moradores por condomínio
