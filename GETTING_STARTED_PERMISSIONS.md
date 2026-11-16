# 🚀 Guia de Implementação - Sistema de Permissões

## 📋 Resumo da Alteração

**Objetivo**: Restringir acesso por perfil
- ✅ **Admin + Síndico**: Acesso completo
- ✅ **Outros**: Apenas Dashboard + Ocorrências

**Status**: ✅ Backend implementado | ⏳ Frontend em progresso

---

## 🛠️ O que foi feito

### Backend ✅

#### 1. Criar PermissionService
```bash
# Arquivo criado:
app/Services/PermissionService.php

# O que faz:
- Define permissões por role
- Verifica se usuário tem acesso a feature
- Retorna lista de features permitidas
```

#### 2. Atualizar SessionController
```bash
# Método adicionado:
public function permissions(Request $request)

# Endpoint:
GET /api/user/permissions

# Retorna:
{
  "features": ["dashboard", "ocorrencias", ...],
  "is_admin": true/false
}
```

#### 3. Adicionar Rota
```php
Route::get('/user/permissions', [SessionController::class, 'permissions']);
```

---

### Frontend ✅

#### 1. Criar permissions.ts
```bash
# Arquivo criado:
src/services/permissions.ts

# Funções:
- getUserPermissions()      → Busca permissões do backend
- hasAccess(perms, feature) → Verifica se tem acesso
- getAccessibleFeatures()   → Lista features
- isAdmin()                  → Verifica se é admin
```

#### 2. Refatorar Sidebar
```bash
# Arquivo modificado:
src/components/layout/sidebar.tsx

# Mudanças:
- useEffect carrega permissões
- filter filtra menu dinamicamente
- loading indica ao carregar
- Apenas features permitidas aparecem
```

---

## 🧪 Teste Rápido

### 1. Verificar Backend

```bash
# Terminal 1: Iniciar servidor Laravel
cd C:\sistema-gestao-condominio
php artisan serve

# Terminal 2: Testar endpoint
curl http://localhost:8000/api/user/permissions \
  -H "Cookie: laravel_session=..."

# Resposta esperada:
{
  "features": ["dashboard", "ocorrencias", "notificacoes", "perfil"],
  "is_admin": false
}
```

### 2. Testar Frontend

```bash
# Terminal 3: Iniciar Next.js
cd C:\sistema-condominio-front
npm run dev

# Abrir no navegador:
http://localhost:3000/admin/dashboard

# Verificar:
- Menu deve mostrar APENAS 4 itens (se morador)
- Menu deve mostrar TODOS (se admin)
```

---

## 📝 Próximos Passos

### Fase 1: Proteção de Rotas (PRÓXIMA)

1. **Criar middleware.ts**
```typescript
// src/middleware.ts
import { NextRequest, NextResponse } from "next/server";

export async function middleware(request: NextRequest) {
  if (request.nextUrl.pathname.startsWith('/admin')) {
    const session = request.cookies.get('laravel_session');
    if (!session) {
      return NextResponse.redirect(new URL('/auth/login', request.url));
    }
  }
  return NextResponse.next();
}

export const config = {
  matcher: ['/admin/:path*', '/auth/:path*'],
};
```

2. **Criar ProtectedRoute.tsx**
```typescript
// src/components/protected-route.tsx
'use client';

export function ProtectedRoute({ children, requiredFeature }) {
  // Validar permissão
  // Se ok: renderizar children
  // Se não: redirecionar para dashboard
}
```

3. **Usar em páginas**
```typescript
// src/app/admin/moradores/page.tsx
export default function Page() {
  return (
    <ProtectedRoute requiredFeature="moradores">
      <ResidentsPage />
    </ProtectedRoute>
  );
}
```

---

## 📊 Matriz de Permissões Atualizada

Conforme implementado em `PermissionService.php`:

```
ADMIN / ADMINISTRADOR / SÍNDICO
├─ Dashboard ✅
├─ Condomínios ✅
├─ Moradores ✅
├─ Funcionários ✅
├─ Prestadores ✅
├─ Financeiro ✅
├─ Documentos ✅
├─ Ocorrências ✅
├─ Relatórios ✅
├─ Configurações ✅
├─ Identidade e Acessos ✅
├─ Notificações ✅
└─ Meu Perfil ✅

MORADOR / VISITANTE / FUNCIONÁRIO / PRESTADOR
├─ Dashboard ✅
├─ Ocorrências ✅
├─ Notificações ✅
└─ Meu Perfil ✅
```

---

## 🔄 Como funciona agora

```
1. Usuário faz login
   ↓
2. Frontend busca GET /user/permissions
   ↓
3. Backend retorna features permitidas
   ↓
4. Sidebar filtra menu automaticamente
   ↓
5. Menu mostra APENAS o que é permitido
   ↓
6. Se tenta acessar URL direta? 
   └─ (Será validado no próximo passo: ProtectedRoute)
```

---

## ✅ Checklist de Teste

### Backend
- [ ] PHP não tem erros de sintaxe
- [ ] Endpoint /user/permissions funciona
- [ ] Retorna features corretas por role

### Frontend
- [ ] permissions.ts compila sem erros
- [ ] Sidebar carrega permissões ao iniciar
- [ ] Menu filtra corretamente
- [ ] Loading aparece enquanto aguarda

### Integração
- [ ] Morador vê apenas 4 itens no sidebar
- [ ] Admin vê todos os 13 itens
- [ ] Não logado vê nada (futuro: middleware)
- [ ] Mudar de perfil atualiza menu

---

## 🎯 Resultado Final

### Antes
```
Todos os usuários veem:
- Dashboard
- Condomínios
- Moradores
- Funcionários
- Prestadores
- Financeiro
- Documentos
- Ocorrências
- Relatórios
- Configurações
- Identidade e Acessos (se manage-access)
- Notificações
- Meu Perfil
```

### Depois
```
Morador vê:
- Dashboard ✅
- Ocorrências ✅
- Notificações ✅
- Meu Perfil ✅
(O resto fica oculto)

Admin vê:
- Tudo (mesmo de antes)
```

---

## 📖 Documentação Adicional

- 📄 [`PERMISSIONS_IMPLEMENTATION.md`](C:\sistema-gestao-condominio\PERMISSIONS_IMPLEMENTATION.md) - Implementação técnica
- 📄 [`PERMISSIONS_SUMMARY.md`](C:\sistema-gestao-condominio\PERMISSIONS_SUMMARY.md) - Sumário executivo
- 📄 [`PROTECTED_ROUTES.md`](C:\sistema-gestao-condominio\PROTECTED_ROUTES.md) - Proteção de rotas (próximo passo)

---

## 🚀 Deploy

```bash
# 1. Backend
cd C:\sistema-gestao-condominio
php artisan config:clear

# 2. Frontend  
cd C:\sistema-condominio-front
npm run build

# 3. Testar
php artisan serve
npm run dev

# 4. Verificar no navegador
http://localhost:3000/admin/dashboard
```

---

## 💡 Troubleshooting

### Menu não filtra (morador vê tudo)
```
1. Verificar se /user/permissions retorna dados corretos
2. Testar: curl http://localhost:8000/api/user/permissions
3. Verificar console.log em sidebar.tsx
4. Limpar cache: npm run build
```

### Erro ao carregar permissões
```
1. Verificar se Laravel está rodando
2. Verificar se /api/user/permissions existe
3. Testar com Postman/Insomnia
4. Verificar logs: tail -f storage/logs/laravel.log
```

### Sidebar em breve vs acesso completo
```
Comportamento esperado:
- comingSoon: true → Mostra desabilitado (Em breve)
- feature permitido → Link normal
- feature não permitido → Não aparece
```

---

## 📞 Suporte

Dúvidas? Verifique:
1. Este guia
2. PERMISSIONS_IMPLEMENTATION.md
3. Arquivo de código (bem comentado)
4. Logs do Laravel/Next.js

---

**Status**: ✅ **BACKEND COMPLETO**  
**Próximo**: Frontend ProtectedRoute  
**Data**: 13/11/2026
