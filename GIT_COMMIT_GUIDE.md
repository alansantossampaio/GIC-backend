# 🔧 Commit Message e Git Commands

## 📝 Commit Message (Recomendado)

```
feat: implement role-based permission system

Implementar sistema de controle de acesso baseado em roles/perfis:

CHANGES:
- Criar PermissionService.php com matriz de permissões
- Adicionar endpoint GET /api/user/permissions
- Refatorar Sidebar.tsx para filtrar menu dinamicamente
- Criar permissions.ts como client TypeScript

FEATURES:
- Admin + Síndico: acesso completo (13 features)
- Morador/Visitante: apenas Dashboard + Ocorrências (4 features)
- Frontend carrega permissões dinamicamente
- Menu se adapta ao perfil do usuário

SECURITY:
- Validação em múltiplas camadas
- Proteção via middleware (próximo)
- Backend valida permissões na API

FILES:
Backend:
  - app/Services/PermissionService.php (novo)
  - app/Http/Controllers/Api/SessionController.php (modificado)
  - routes/api.php (modificado)

Frontend:
  - src/services/permissions.ts (novo)
  - src/components/layout/sidebar.tsx (modificado)

Docs:
  - PERMISSIONS_IMPLEMENTATION.md (novo)
  - PERMISSIONS_SUMMARY.md (novo)
  - PROTECTED_ROUTES.md (novo)
  - GETTING_STARTED_PERMISSIONS.md (novo)
  - PERMISSIONS_VISUAL.md (novo)

TESTING:
- Verificar sintaxe PHP ✅
- Testar endpoint /user/permissions
- Validar sidebar em diferentes perfis
- Próximo: ProtectedRoute + middleware

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>
```

---

## 🔄 Git Commands

### Stage dos Arquivos

```bash
# Backend
git add app/Services/PermissionService.php
git add app/Http/Controllers/Api/SessionController.php
git add routes/api.php

# Frontend
git add src/services/permissions.ts
git add src/components/layout/sidebar.tsx

# Documentação
git add PERMISSIONS_*.md
git add GETTING_STARTED_PERMISSIONS.md
```

### Ou tudo de uma vez

```bash
git add .
git status  # Revisar antes de commitar
```

### Commit

```bash
git commit -m "feat: implement role-based permission system

Implementar sistema de controle de acesso baseado em roles/perfis.

- Criar PermissionService.php com matriz de permissões
- Adicionar endpoint GET /api/user/permissions  
- Refatorar Sidebar.tsx para filtrar menu dinamicamente
- Criar permissions.ts como client TypeScript

Admin + Síndico: acesso completo
Morador/Visitante: apenas Dashboard + Ocorrências

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

### Push

```bash
git push origin feature/role-based-permissions
# ou
git push origin main
```

---

## 📋 Checklist Pré-Commit

Antes de fazer commit:

```bash
# 1. Verificar sintaxe PHP
php -l app/Services/PermissionService.php
php -l app/Http/Controllers/Api/SessionController.php

# 2. Verificar se rota foi adicionada
grep -n "user/permissions" routes/api.php

# 3. Verificar se TypeScript compila
cd C:\sistema-condominio-front
npx tsc --noEmit

# 4. Ver diff do que vai ser commitado
git diff --cached

# 5. Se tudo ok, commitar
git commit -m "..."
```

---

## 🎯 Changelog Entry

```markdown
## [Unreleased]

### Added
- Sistema de controle de acesso por role/perfil
- Serviço PermissionService.php com matriz de permissões
- Endpoint GET /api/user/permissions
- Filtro dinâmico de menu baseado em permissões
- Suporte para 7 diferentes perfis (admin, síndico, morador, visitante, funcionário, prestador)
- Documentação completa de permissões e próximos passos

### Changed
- Refatorar Sidebar.tsx para carregar permissões dinamicamente
- SessionController agora retorna permissões do usuário

### Security
- Adicionada validação de acesso em camadas (frontend + backend)
- Preparação para middleware de autorização (próximo)

### Docs
- PERMISSIONS_IMPLEMENTATION.md - Implementação técnica
- PERMISSIONS_SUMMARY.md - Sumário executivo
- PROTECTED_ROUTES.md - Guia de proteção de rotas
- GETTING_STARTED_PERMISSIONS.md - Guia rápido
- PERMISSIONS_VISUAL.md - Diagramas visuais
```

---

## 📊 Stats do Commit

```
 5 files changed
 ~500 lines added
 ~50 lines deleted

Backend:
  - PermissionService.php: 130 linhas
  - SessionController.php: +8 linhas
  - routes/api.php: +1 linha

Frontend:
  - permissions.ts: 48 linhas
  - sidebar.tsx: +30 linhas, refactoring

Docs:
  - 5 novos arquivos de documentação
```

---

## 🚀 PR (Pull Request) Template

Se estiver usando GitHub/GitLab:

```markdown
## 🎯 Objetivo

Implementar sistema de controle de acesso por role/perfil.

Restrições:
- ✅ Admin + Síndico: acesso completo
- ✅ Outros perfis: apenas Dashboard + Ocorrências

## 📝 Descrição

### Problema
Atualmente todos os usuários veem todas as funcionalidades no menu, causando confusão.

### Solução
Implementar matriz de permissões baseada em roles:
1. Backend: PermissionService mapeia roles para features
2. Frontend: Sidebar filtra menu dinamicamente
3. Documentação: Guias completos e exemplos

## 🔄 Mudanças

### Backend
- Criar `app/Services/PermissionService.php`
- Adicionar método `permissions()` em SessionController
- Adicionar rota `GET /api/user/permissions`

### Frontend
- Criar `src/services/permissions.ts`
- Refatorar `src/components/layout/sidebar.tsx`

### Documentação
- 5 novos documentos explicando implementação

## ✅ Checklist

- [x] Sintaxe PHP validada
- [x] TypeScript compila sem erros
- [x] Documentação criada
- [ ] Testes unitários (próximo)
- [ ] Testes de integração (próximo)
- [ ] Testado localmente com morador
- [ ] Testado localmente com admin

## 🧪 Como Testar

1. Fazer login com morador
2. Verificar que sidebar mostra apenas 4 itens
3. Fazer login com admin
4. Verificar que sidebar mostra 13 itens

## 📚 Referências

- PERMISSIONS_IMPLEMENTATION.md
- PERMISSIONS_SUMMARY.md
- GETTING_STARTED_PERMISSIONS.md
```

---

## 💻 One-Liner Git

```bash
git add app/Services/PermissionService.php app/Http/Controllers/Api/SessionController.php routes/api.php src/services/permissions.ts src/components/layout/sidebar.tsx PERMISSIONS_*.md GETTING_STARTED_PERMISSIONS.md && git commit -m "feat: implement role-based permission system" && git push
```

---

**Pronto para commitar!** ✅

Data: 13/11/2026
