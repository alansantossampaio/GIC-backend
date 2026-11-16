# 🔐 Sistema de Permissões - Implementação

## 📋 Resumo

Implementado um **sistema de controle de acesso baseado em roles** que:
- ✅ **Admin + Síndico**: Acesso a TODAS as funcionalidades
- ✅ **Outros perfis** (Morador, Visitante, etc): Apenas **Dashboard + Ocorrências**

---

## 🎯 Estrutura de Permissões

### Admin / Administrador / Síndico
```
✅ Dashboard
✅ Condomínios
✅ Moradores
✅ Funcionários
✅ Prestadores
✅ Financeiro
✅ Documentos
✅ Ocorrências
✅ Relatórios
✅ Configurações
✅ Identidade e Acessos
✅ Notificações
✅ Meu Perfil
```

### Morador / Visitante / Funcionário / Prestador
```
✅ Dashboard
✅ Ocorrências (abrir)
✅ Notificações
✅ Meu Perfil
❌ Tudo mais (oculto)
```

---

## 🛠️ Implementação

### 1. Backend - PermissionService

**Arquivo**: `app/Services/PermissionService.php`

Define as permissões por role:

```php
private const ROLE_PERMISSIONS = [
    'admin' => [ /* 13 features */ ],
    'administrador' => [ /* 13 features */ ],
    'sindico' => [ /* 13 features */ ],
    'morador' => ['dashboard', 'ocorrencias', 'notificacoes', 'perfil'],
    'visitante' => ['dashboard', 'ocorrencias', 'notificacoes', 'perfil'],
    'funcionario' => ['dashboard', 'ocorrencias', 'notificacoes', 'perfil'],
    'prestador' => ['dashboard', 'ocorrencias', 'notificacoes', 'perfil'],
];
```

**Métodos principais**:

| Método | Retorna | Uso |
|--------|---------|-----|
| `getUserPermissions(User, ?int)` | `array` | Obter permissões do usuário |
| `hasAccess(User, Feature, ?int)` | `bool` | Verificar se tem acesso |
| `getAccessibleFeatures(User, ?int)` | `Collection` | Listar features acessíveis |
| `isAdmin(User)` | `bool` | Verificar se é admin/síndico |

---

### 2. Backend - SessionController

**Arquivo**: `app/Http/Controllers/Api/SessionController.php`

Novo endpoint: `GET /user/permissions`

```php
public function permissions(Request $request): JsonResponse
{
    $permissionService = new PermissionService();
    return response()->json([
        'features' => $permissionService->getAccessibleFeatures($request->user()),
        'is_admin' => $permissionService->isAdmin($request->user()),
    ]);
}
```

**Resposta exemplo**:

```json
{
  "features": [
    "dashboard",
    "ocorrencias",
    "notificacoes",
    "perfil"
  ],
  "is_admin": false
}
```

---

### 3. Frontend - permissions.ts

**Arquivo**: `src/services/permissions.ts`

```typescript
// Obter permissões do usuário
const perms = await getUserPermissions();

// Verificar se tem acesso
if (hasAccess(perms, 'moradores')) {
  // Mostrar menu
}

// Obter lista de features
const features = getAccessibleFeatures(perms);

// Verificar se é admin
if (isAdmin(perms)) {
  // Mostrar menu admin
}
```

---

### 4. Frontend - Sidebar.tsx

**Arquivo**: `src/components/layout/sidebar.tsx`

Filtro dinâmico de menu:

```typescript
useEffect(() => {
  const perms = await getUserPermissions();
  setPermissions(perms);
}, []);

const visibleItems = permissions 
  ? availableItems.filter(item => hasAccess(permissions, item.feature))
  : [];
```

---

## 🔄 Fluxo de Funcionamento

```
1. Usuário faz login
   ↓
2. Frontend carrega permissões: GET /user/permissions
   ↓
3. PermissionService busca roles do usuário
   ↓
4. Mapeia roles para features permitidas
   ↓
5. Retorna lista de features + flag is_admin
   ↓
6. Sidebar filtra menu dinamicamente
   ↓
7. Apenas features permitidas são mostradas
```

---

## 📱 Exemplo de Uso

### Cenário: Usuário Morador tenta acessar

```
GET /user/permissions
↓
Retorna:
{
  "features": ["dashboard", "ocorrencias", "notificacoes", "perfil"],
  "is_admin": false
}
↓
Sidebar filtra menu:
- ✅ Dashboard
- ❌ Condomínios (oculto)
- ❌ Moradores (oculto)
- ✅ Ocorrências
- ✅ Notificações
- ✅ Meu Perfil
```

### Cenário: Usuário Admin tenta acessar

```
GET /user/permissions
↓
Retorna:
{
  "features": ["dashboard", "condominios", "moradores", ..., "acessos"],
  "is_admin": true
}
↓
Sidebar filtra menu:
- ✅ Todas as 13 features
- ✅ Menu admin habilitado
```

---

## 🧪 Como Testar

### Teste 1: Verificar se morador vê apenas dashboard + ocorrências

1. Fazer login com conta de morador
2. Verificar que o sidebar mostra APENAS:
   - Dashboard ✅
   - Ocorrências ✅
   - Notificações ✅
   - Meu Perfil ✅
3. Outros itens devem estar **OCULTOS** ❌

### Teste 2: Verificar se admin vê tudo

1. Fazer login com admin
2. Verificar que o sidebar mostra:
   - Todas as 13 features ✅
   - Identidade e Acessos ✅
3. Nada deve estar oculto ✅

### Teste 3: Testar endpoint de permissões

```bash
curl -X GET http://localhost:8000/api/user/permissions \
  -H "Accept: application/json" \
  -H "Cookie: XSRF-TOKEN=...; laravel_session=..."

# Resposta esperada:
{
  "features": ["dashboard", "ocorrencias", "notificacoes", "perfil"],
  "is_admin": false
}
```

---

## 🔐 Segurança

### Proteção em Múltiplas Camadas

1. **Frontend**: Menu filtrado (UX)
2. **Backend**: Validação de permissões em rotas
3. **Banco**: Foreign keys garantem integridade

### Rotas Protegidas

```php
// Apenas quem tem permissão 'manage-access' acessa
Route::prefix('admin')->middleware('can:manage-access')->group(function () {
    Route::post('/users/invite', [AccessManagementController::class, 'invite']);
    // ... outras rotas admin
});
```

---

## 📝 Arquivos Modificados/Criados

### Criados
- ✅ `app/Services/PermissionService.php` - Serviço de permissões
- ✅ `src/services/permissions.ts` - Cliente TypeScript

### Modificados
- ✅ `app/Http/Controllers/Api/SessionController.php` - Adicionado método `permissions()`
- ✅ `routes/api.php` - Adicionada rota `GET /user/permissions`
- ✅ `src/components/layout/sidebar.tsx` - Filtro dinâmico de menu

---

## 📊 Tabela de Features

| Feature | Admin | Síndico | Morador | Visitante |
|---------|-------|---------|---------|-----------|
| dashboard | ✅ | ✅ | ✅ | ✅ |
| condominios | ✅ | ✅ | ❌ | ❌ |
| moradores | ✅ | ✅ | ❌ | ❌ |
| funcionarios | ✅ | ✅ | ❌ | ❌ |
| prestadores | ✅ | ✅ | ❌ | ❌ |
| financeiro | ✅ | ✅ | ❌ | ❌ |
| documentos | ✅ | ✅ | ❌ | ❌ |
| ocorrencias | ✅ | ✅ | ✅ | ✅ |
| relatorios | ✅ | ✅ | ❌ | ❌ |
| configuracoes | ✅ | ✅ | ❌ | ❌ |
| acessos | ✅ | ✅ | ❌ | ❌ |
| notificacoes | ✅ | ✅ | ✅ | ✅ |
| perfil | ✅ | ✅ | ✅ | ✅ |

---

## 🎯 Próximos Passos

1. ✅ **Backend**: PermissionService implementado
2. ✅ **Frontend**: Sidebar filtrado
3. ⏳ **Proteção de rotas**: Adicionar middleware nas páginas individuais
4. ⏳ **Auditoria**: Registrar quem acessou o quê
5. ⏳ **Granularidade**: Permissões por feature + por condomínio

---

## 🚀 Deploy

```bash
# 1. Atualizar código
git pull

# 2. Limpar cache
php artisan config:clear

# 3. Rebuild frontend
npm run build

# 4. Reiniciar servidor
php artisan serve
npm run dev
```

---

**Status**: ✅ **IMPLEMENTADO**  
**Data**: 13/11/2026  
**Responsável**: Copilot
